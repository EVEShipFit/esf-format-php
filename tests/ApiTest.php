<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\Catalog;
use EveShipFit\EsfFormat\Entry;
use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfError;
use EveShipFit\EsfFormat\EsfFit;
use EveShipFit\EsfFormat\Location;
use EveShipFit\EsfFormat\MutaplasmidCatalog;
use EveShipFit\EsfFormat\State;
use EveShipFit\EsfFormat\TypeRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    private const RIFTER = 587;
    private const AUTOCANNON = 2889;
    private const EMP = 21898;
    private const SHIELD_EXTENDER = 3831;
    private const SCRAMBLER = 448;
    private const DAMAGE_CONTROL = 2048;
    private const HOBGOBLIN = 2456;
    private const DECAYED_DAMAGE_CONTROL_MUTAPLASMID = 52224;
    private const AVATAR = 11567;
    private const AVATAR_UNPUBLISHED = 50097;
    private const SMALL_SECURE_CONTAINER = 3467;

    private static ?JsonCatalog $catalog = null;

    public static function catalog(): JsonCatalog
    {
        return self::$catalog ??= new JsonCatalog(getenv('ESF_CATALOG') ?: 'esf-format/tests/catalog.json');
    }

    private static function fit(Entry ...$entries): EsfFit
    {
        return new EsfFit(self::RIFTER, entries: array_values($entries));
    }

    private static function named(string $name, Entry ...$entries): EsfFit
    {
        return new EsfFit(self::RIFTER, $name, entries: array_values($entries));
    }

    public function testSaveCanonical(): void
    {
        $rifter = self::named(
            'Shield Buffer',
            new Entry(self::DAMAGE_CONTROL),
            new Entry(self::AUTOCANNON, charge: self::EMP),
            new Entry(self::SHIELD_EXTENDER),
            new Entry(self::AUTOCANNON, charge: self::EMP),
            new Entry(null, location: Location::High),
            new Entry(self::SCRAMBLER, state: State::Off),
            new Entry(self::HOBGOBLIN, count: 2),
            new Entry(self::EMP, count: 100, location: Location::Cargo),
        );
        self::assertSame(
            "%esf/1\n"
            . "Rifter \"Shield Buffer\"\n"
            . "\n"
            . "2x 200mm AutoCannon II :Republic Fleet EMP S\n"
            . "\n"
            . "Medium Shield Extender II\n"
            . "Warp Scrambler II !off\n"
            . "\n"
            . "Damage Control II\n"
            . "\n"
            . "2x Hobgoblin II\n"
            . "\n"
            . "100x Republic Fleet EMP S\n",
            Esf::save([$rifter], self::catalog()),
        );
    }

    public function testLoadCanonical(): void
    {
        $rifter = self::fit(
            new Entry(self::AUTOCANNON),
            new Entry(self::AUTOCANNON),
            new Entry(self::EMP, location: Location::Cargo),
        );
        self::assertEquals(
            [self::fit(new Entry(self::AUTOCANNON, count: 2), new Entry(self::EMP))],
            Esf::load(Esf::save([$rifter], self::catalog()), self::catalog()),
        );
    }

    public function testLoadText(): void
    {
        self::assertEquals([new EsfFit(self::RIFTER)], Esf::load("%esf/1\nRifter\n", self::catalog()));
    }

    public function testMutaplasmidRolls(): void
    {
        $entry = new Entry(
            self::DAMAGE_CONTROL,
            mutaplasmid: self::DECAYED_DAMAGE_CONTROL_MUTAPLASMID,
            overrides: [50 => 31.5],
        );
        [$loaded] = Esf::load(Esf::save([self::fit($entry)], self::catalog()), self::catalog());
        self::assertSame(
            [50 => 31.5, 974 => 0.6, 975 => 0.6, 976 => 0.6, 977 => 0.6],
            $loaded->entries[0]->overrides,
        );
    }

    public function testBinaryRoundTrip(): void
    {
        $fits = [
            self::fit(new Entry(self::SMALL_SECURE_CONTAINER, fitName: 'Loot', location: Location::Cargo)),
            new EsfFit(self::SMALL_SECURE_CONTAINER, 'Loot', entries: [new Entry(self::EMP, count: 10)]),
        ];
        self::assertEquals(
            Esf::load(Esf::save($fits, self::catalog()), self::catalog()),
            Esf::load(Esf::saveBinary($fits, self::catalog()), self::catalog()),
        );
    }

    /**
     * @return iterable<string, array{list<EsfFit>, string}>
     */
    public static function invalidFits(): iterable
    {
        yield 'no-fits' => [[], 'at least one fit'];
        yield 'empty-no-rack' => [[self::fit(new Entry(null))], 'an empty slot must name its rack'];
        yield 'negative-count' => [[self::fit(new Entry(self::AUTOCANNON, count: -1))], 'at least 1'];
        yield 'zero-charge-count' => [
            [self::fit(new Entry(self::AUTOCANNON, charge: self::EMP, chargeCount: 0))],
            'at least 1',
        ];
        yield 'unknown-type' => [[self::fit(new Entry(999_999_999))], 'unknown item type ID'];
        yield 'unknown-hull' => [[new EsfFit(999_999_999)], 'unknown hull type ID'];
        yield 'unknown-attribute' => [
            [self::fit(new Entry(self::DAMAGE_CONTROL, overrides: [999_999 => 1.0]))],
            'unknown attribute ID',
        ];
        yield 'rack-on-item' => [
            [self::fit(new Entry(self::AUTOCANNON, location: Location::High))],
            'only for an empty slot',
        ];
        yield 'charge-count-alone' => [
            [self::fit(new Entry(self::AUTOCANNON, chargeCount: 10))],
            'without a charge',
        ];
        yield 'override-nan' => [
            [self::fit(new Entry(self::DAMAGE_CONTROL, overrides: [50 => NAN]))],
            'not finite',
        ];
        yield 'override-infinite' => [
            [self::fit(new Entry(self::DAMAGE_CONTROL, overrides: [50 => INF]))],
            'not finite',
        ];
        yield 'id-shadowed' => [
            [self::fit(new Entry(self::AVATAR_UNPUBLISHED, location: Location::Cargo))],
            'not what its name resolves to',
        ];
        yield 'contains-itself' => [
            [self::named('Buffer', new Entry(self::RIFTER, fitName: 'Buffer'))],
            'cannot contain itself',
        ];
        yield 'name-newline' => [[self::named("Two\nlines")], 'CR or LF'];
        yield 'name-not-utf8' => [[self::named("\xFF")], 'not valid UTF-8'];
    }

    /**
     * @param list<EsfFit> $fits
     */
    #[DataProvider('invalidFits')]
    public function testSaveInvalid(array $fits, string $message): void
    {
        $this->expectException(EsfError::class);
        $this->expectExceptionMessage($message);
        Esf::save($fits, self::catalog());
    }

    public function testErrorLocation(): void
    {
        try {
            Esf::save([self::fit(), self::fit(new Entry(self::AUTOCANNON), new Entry(999_999_999))], self::catalog());
            self::fail('no error');
        } catch (EsfError $e) {
            self::assertSame([2, 2, null], [$e->fitNumber, $e->entryNumber, $e->lineNumber]);
            self::assertStringStartsWith('fit 2, entry 2: ', $e->getMessage());
        }
    }

    public function testErrorLocationOfAFit(): void
    {
        try {
            Esf::save([self::fit(), new EsfFit(self::RIFTER, mode: 999_999_999)], self::catalog());
            self::fail('no error');
        } catch (EsfError $e) {
            self::assertSame([2, null], [$e->fitNumber, $e->entryNumber]);
            self::assertStringStartsWith('fit 2: ', $e->getMessage());
        }
    }

    public function testErrorLocationOfALine(): void
    {
        try {
            Esf::load("%esf/1\nRifter\nNot A Type\n", self::catalog());
            self::fail('no error');
        } catch (EsfError $e) {
            self::assertSame(3, $e->lineNumber);
            self::assertSame("line 3: unknown type 'Not A Type'", $e->getMessage());
        }
    }

    public function testEmptyNameIsNoName(): void
    {
        self::assertSame("%esf/1\nRifter\n", Esf::save([self::named('')], self::catalog()));
    }

    public function testNameZeroIsAName(): void
    {
        self::assertSame("%esf/1\nRifter \"0\"\n", Esf::save([self::named('0')], self::catalog()));
    }

    public function testSharedNamePublishedWins(): void
    {
        $text = Esf::save([self::fit(new Entry(self::AVATAR, location: Location::Cargo))], self::catalog());
        self::assertStringEndsWith("Avatar\n", $text);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'empty' => ['', 'the document is empty'];
        yield 'lone-surrogate' => ["%esf/1\nRifter\xED\xA0\x80\n", 'line 2: not valid UTF-8'];
        yield 'binary-without-fits' => ["\x10\x01", 'at least one fit'];
        yield 'merged-count-too-large' => [
            "%esf/1\nRifter\n4294967295x Damage Control II\n4294967295x Damage Control II\n",
            'too large',
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function testLoadInvalid(string $data, string $message): void
    {
        $this->expectException(EsfError::class);
        $this->expectExceptionMessage($message);
        Esf::load($data, self::catalog());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function optionalLookups(): iterable
    {
        yield 'mode' => ['Hecate /Defense', 'no tactical modes'];
        yield 'mutaplasmid' => ["Rifter\nDamage Control II +Decayed", 'no mutaplasmid'];
    }

    #[DataProvider('optionalLookups')]
    public function testCatalogWithoutOptionalLookups(string $line, string $message): void
    {
        $this->expectException(EsfError::class);
        $this->expectExceptionMessage($message);
        Esf::load("%esf/1\n{$line}\n", new MinimalCatalog(self::catalog()));
    }

    public function testBase64Url(): void
    {
        $data = Esf::saveBinary([self::fit(new Entry(self::AUTOCANNON))], self::catalog());
        $url = Esf::toBase64Url($data);
        self::assertStringNotContainsString('=', $url);
        self::assertSame($data, Esf::fromBase64Url($url));
        self::assertSame("\xFB\xFF", Esf::fromBase64Url(" -_8\n"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBase64Url(): iterable
    {
        yield 'alphabet' => ['not base64url!'];
        yield 'padding' => ['AA=='];
        yield 'length' => ['AAAAA'];
    }

    #[DataProvider('invalidBase64Url')]
    public function testInvalidBase64Url(string $encoded): void
    {
        $this->expectException(EsfError::class);
        Esf::fromBase64Url($encoded);
    }

    public function testIsText(): void
    {
        self::assertTrue(Esf::isText("%esf/1\n"));
        self::assertTrue(Esf::isText(" %esf/1\n"));
        self::assertFalse(Esf::isText("\x0a\x02"));
        self::assertFalse(Esf::isText(''));
    }

    public function testFold(): void
    {
        self::assertSame('damage control ii', Esf::fold('Damage Control II'));
        self::assertSame('ärger σσ', Esf::fold('ÄRGER Σς'));
    }

    public function testCatalogMissingRollAttribute(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('no attribute');
        Esf::load("%esf/1\nRifter\nDamage Control II +Decayed\n", new CatalogWithoutRollAttributes(self::catalog()));
    }
}

class MinimalCatalog implements Catalog
{
    public function __construct(protected readonly JsonCatalog $catalog) {}

    public function type(string $folded): ?TypeRecord
    {
        return $this->catalog->type($folded);
    }

    public function typeById(int $typeId): ?TypeRecord
    {
        return $this->catalog->typeById($typeId);
    }

    public function attribute(string $folded): ?AttributeRecord
    {
        return $this->catalog->attribute($folded);
    }

    public function attributeById(int $attributeId): ?AttributeRecord
    {
        return $this->catalog->attributeById($attributeId);
    }
}

final class CatalogWithoutRollAttributes extends MinimalCatalog implements MutaplasmidCatalog
{
    public function attributeById(int $attributeId): ?AttributeRecord
    {
        return null;
    }

    public function mutaplasmids(int $typeId): array
    {
        return $this->catalog->mutaplasmids($typeId);
    }
}
