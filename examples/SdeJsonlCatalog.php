<?php

declare(strict_types=1);

namespace Example;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\Kind;
use EveShipFit\EsfFormat\ModeCatalog;
use EveShipFit\EsfFormat\MutaplasmidCatalog;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\Sde;
use EveShipFit\EsfFormat\TypeRecord;

/**
 * A Catalog read from the official SDE in JSONL, unpacked into a directory.
 *
 * @phpstan-type Named array{id: int, name: string, published: bool}
 */
final class SdeJsonlCatalog implements ModeCatalog, MutaplasmidCatalog
{
    /** @var array<int, TypeRecord> */
    private array $types = [];
    /** @var array<int, AttributeRecord> */
    private array $attributes = [];
    /** @var array<array-key, int> */
    private array $typeNames;
    /** @var array<array-key, int> */
    private array $attributeNames;
    /** @var array<int, list<TypeRecord>> */
    private array $modes = [];
    /** @var array<int, list<MutaplasmidRecord>> */
    private array $mutaplasmids = [];

    public function __construct(string $dir)
    {
        $categoryOfGroup = [];
        /** @var array{_key: int, categoryID: int} $g */
        foreach (self::rows("{$dir}/groups.jsonl") as $g) {
            $categoryOfGroup[$g['_key']] = $g['categoryID'];
        }

        $effectCategory = [];
        /** @var array{_key: int, effectCategoryID?: int} $e */
        foreach (self::rows("{$dir}/dogmaEffects.jsonl") as $e) {
            $effectCategory[$e['_key']] = $e['effectCategoryID'] ?? 0;
        }

        $mutaplasmidRows = [];
        $rollable = [];
        /**
         * @var array{
         *     _key: int,
         *     attributeIDs: list<array{_key: int}>,
         *     inputOutputMapping: list<array{applicableTypes: list<int>}>,
         * } $m
         */
        foreach (self::rows("{$dir}/dynamicItemAttributes.jsonl") as $m) {
            $bases = [];
            foreach ($m['inputOutputMapping'] as $mapping) {
                $bases += array_fill_keys($mapping['applicableTypes'], true);
            }
            $attributeIds = array_column($m['attributeIDs'], '_key');
            $mutaplasmidRows[$m['_key']] = [array_keys($bases), $attributeIds];
            $rollable += array_fill_keys($attributeIds, true);
        }

        $dogma = [];
        /**
         * @var array{
         *     _key: int,
         *     dogmaAttributes?: list<array{attributeID: int, value: float|int}>,
         *     dogmaEffects?: list<array{effectID: int}>,
         * } $row
         */
        foreach (self::rows("{$dir}/typeDogma.jsonl") as $row) {
            $effects = array_column($row['dogmaEffects'] ?? [], 'effectID');
            $values = array_map(floatval(...), array_column($row['dogmaAttributes'] ?? [], 'value', 'attributeID'));
            $dogma[$row['_key']] = [$effects, Sde::squadronSize($values), array_intersect_key($values, $rollable)];
        }

        $typeRows = [];
        $ships = [];
        /**
         * @var array{
         *     _key: int,
         *     groupID: int,
         *     name?: array{en?: string},
         *     published?: bool,
         *     capacity?: float|int,
         *     volume?: float|int,
         * } $t
         */
        foreach (self::rows("{$dir}/types.jsonl") as $t) {
            $name = $t['name']['en'] ?? null;
            if ($name === null) {
                continue;
            }
            $id = $t['_key'];
            $groupId = $t['groupID'];
            $categoryId = $categoryOfGroup[$groupId];
            [$effects, $squadronSize] = $dogma[$id] ?? [[], null];
            $categories = [];
            foreach ($effects as $effectId) {
                $categories[$effectId] = $effectCategory[$effectId] ?? 0;
            }
            $this->types[$id] = new TypeRecord(
                $id,
                $name,
                Sde::kind($categoryId, $groupId, $effects),
                Sde::active($categories),
                (float) ($t['capacity'] ?? 0),
                (float) ($t['volume'] ?? 0),
                $squadronSize,
            );
            $named = ['id' => $id, 'name' => $name, 'published' => $t['published'] ?? false];
            $typeRows[] = $named;
            if ($categoryId === 6) {
                $ships[] = $named;
            }
        }
        $this->typeNames = self::nameIndex($typeRows);

        $attributeRows = [];
        $defaults = [];
        /** @var array{_key: int, name: string, published?: bool, defaultValue?: float|int} $a */
        foreach (self::rows("{$dir}/dogmaAttributes.jsonl") as $a) {
            $this->attributes[$a['_key']] = new AttributeRecord($a['_key'], $a['name']);
            $attributeRows[] = ['id' => $a['_key'], 'name' => $a['name'], 'published' => $a['published'] ?? false];
            $defaults[$a['_key']] = (float) ($a['defaultValue'] ?? 0);
        }
        $this->attributeNames = self::nameIndex($attributeRows);

        $shipNames = self::nameIndex($ships);
        foreach ($this->types as $mode) {
            if ($mode->kind === Kind::Mode) {
                $hull = Sde::modeHull($mode->name, $shipNames);
                if ($hull !== null) {
                    $this->modes[$hull][] = $mode;
                }
            }
        }

        ksort($mutaplasmidRows);
        foreach ($mutaplasmidRows as $id => [$bases, $attributeIds]) {
            foreach ($bases as $base) {
                $values = $dogma[$base][2] ?? [];
                $rolls = [];
                foreach ($attributeIds as $attributeId) {
                    $rolls[$attributeId] = $values[$attributeId] ?? $defaults[$attributeId] ?? 0.0;
                }
                $this->mutaplasmids[$base][] = new MutaplasmidRecord($this->types[$id], $rolls);
            }
        }
    }

    public function type(string $foldedName): ?TypeRecord
    {
        return $this->types[$this->typeNames[$foldedName] ?? -1] ?? null;
    }

    public function typeById(int $typeId): ?TypeRecord
    {
        return $this->types[$typeId] ?? null;
    }

    public function attribute(string $foldedName): ?AttributeRecord
    {
        return $this->attributes[$this->attributeNames[$foldedName] ?? -1] ?? null;
    }

    public function attributeById(int $attributeId): ?AttributeRecord
    {
        return $this->attributes[$attributeId] ?? null;
    }

    public function modes(int $hullId): array
    {
        return $this->modes[$hullId] ?? [];
    }

    public function mutaplasmids(int $typeId): array
    {
        return $this->mutaplasmids[$typeId] ?? [];
    }

    /**
     * @return \Generator<mixed>
     */
    private static function rows(string $path): \Generator
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        foreach ($file as $line) {
            if (is_string($line)) {
                yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
        }
    }

    /**
     * @param list<Named> $rows
     * @return array<array-key, int>
     */
    private static function nameIndex(array $rows): array
    {
        return Sde::nameIndex($rows, static fn($r) => $r['id'], static fn($r) => $r['name'], static fn($r) => $r['published']);
    }
}
