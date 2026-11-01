<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfException;
use EveShipFit\EsfFormat\Internal\Canonical;
use EveShipFit\EsfFormat\Internal\Names;
use EveShipFit\EsfFormat\Internal\Number;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InternalTest extends TestCase
{
    /**
     * @return iterable<array{float, string}>
     */
    public static function numbers(): iterable
    {
        yield [1e22, '10000000000000000000000'];
        yield [1.5e-7, '0.00000015'];
        yield [-0.0, '0'];
        yield [0.1, '0.1'];
        yield [2 ** -24, '0.00000005960464477539063'];
        yield [0.3, '0.3'];
        yield [123.456, '123.456'];
        yield [-2.5, '-2.5'];
        yield [100.0, '100'];
        yield [9007199254740992.0, '9007199254740992'];
        yield [5e-324, '0.' . str_repeat('0', 323) . '5'];
        yield [1.7976931348623157e308, '17976931348623157' . str_repeat('0', 292)];
    }

    #[DataProvider('numbers')]
    public function testNumberFormat(float $value, string $text): void
    {
        self::assertSame($text, Number::format($value));
    }

    /**
     * @return iterable<array{float, float, int}>
     */
    public static function quotients(): iterable
    {
        yield [0.3, 0.1, 3];
        yield [10.0, 0.1, 100];
        yield [400.0, 0.0125, 32000];
        yield [1.0, 3.0, 0];
        yield [1.0, 2 ** -24, 16777215];
        yield [1000.0, 1e5, 0];
        yield [1e300, 1e-300, PHP_INT_MAX];
    }

    #[DataProvider('quotients')]
    public function testNumberQuotient(float $a, float $b, int $quotient): void
    {
        self::assertSame($quotient, Number::quotient($a, $b));
    }

    public function testNeedsQuotes(): void
    {
        self::assertFalse(Canonical::needsQuotes('Damage Control II'));
        self::assertTrue(Canonical::needsQuotes(''));
        self::assertTrue(Canonical::needsQuotes('-x'));
        self::assertTrue(Canonical::needsQuotes('%x'));
        self::assertTrue(Canonical::needsQuotes('3x Damage Control'));
        self::assertTrue(Canonical::needsQuotes('Damage  Control'));
        self::assertTrue(Canonical::needsQuotes('Damage @Control'));
    }

    public function testShortestUnique(): void
    {
        self::assertSame('Decayed', Names::shortestUnique('Decayed Damage Control', ['Gravid Damage Control']));
        self::assertSame('A B', Names::shortestUnique('A B', ['A B C']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidBinary(): iterable
    {
        yield 'key-cut-short' => ["\x0A", 'cut short'];
        yield 'varint-cut-short' => ["\x0A\x80", 'cut short'];
        yield 'varint-too-long' => ["\x10" . str_repeat("\xFF", 10) . "\x01", 'varint is too long'];
        yield 'key-too-large' => ["\x80\x80\x80\x80\x80\x01", 'bad key 34359738368'];
        yield 'key-negative' => [str_repeat("\xFF", 9) . "\x01", 'bad key 18446744073709551615'];
    }

    #[DataProvider('invalidBinary')]
    public function testInvalidBinary(string $data, string $message): void
    {
        $this->expectException(EsfException::class);
        $this->expectExceptionMessage($message);
        Esf::load($data, ApiTest::catalog());
    }

    public function testBinarySkipsUnknownFixed64(): void
    {
        $fit = Esf::saveBinary(Esf::load("%esf/1\nRifter\n", ApiTest::catalog()), ApiTest::catalog());
        $fits = Esf::load("\x11" . str_repeat("\x00", 8) . $fit, ApiTest::catalog());
        self::assertSame(587, $fits[0]->hull);
    }
}
