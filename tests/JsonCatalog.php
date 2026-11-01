<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\Kind;
use EveShipFit\EsfFormat\ModeCatalog;
use EveShipFit\EsfFormat\MutaplasmidCatalog;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\TypeRecord;

/**
 * A Catalog read from the esf-format test catalog.
 *
 * @phpstan-type Named array{id: int, name: string, published: bool}
 */
final class JsonCatalog implements ModeCatalog, MutaplasmidCatalog
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

    public function __construct(string $path)
    {
        /**
         * @var array{
         *     types: list<array{id: int, name: string, published: bool, kind: string, active: bool,
         *         capacity: float, volume: float, squadron_size?: int}>,
         *     attributes: list<array{id: int, name: string, published: bool}>,
         *     modes: list<array{hull: int, modes: list<int>}>,
         *     mutaplasmids: list<array{base: int, mutaplasmid: int, rolls: list<array{int, float}>}>,
         * } $data
         */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data['types'] as $t) {
            $this->types[$t['id']] = new TypeRecord(
                $t['id'],
                $t['name'],
                Kind::from($t['kind']),
                $t['active'],
                (float) $t['capacity'],
                (float) $t['volume'],
                $t['squadron_size'] ?? null,
            );
        }
        foreach ($data['attributes'] as $a) {
            $this->attributes[$a['id']] = new AttributeRecord($a['id'], $a['name']);
        }
        $this->typeNames = self::byName($data['types']);
        $this->attributeNames = self::byName($data['attributes']);
        foreach ($data['modes'] as $m) {
            $this->modes[$m['hull']] = array_map(fn($id) => $this->types[$id], $m['modes']);
        }
        foreach ($data['mutaplasmids'] as $m) {
            $rolls = [];
            foreach ($m['rolls'] as [$attributeId, $base]) {
                $rolls[$attributeId] = (float) $base;
            }
            $this->mutaplasmids[$m['base']][] = new MutaplasmidRecord($this->types[$m['mutaplasmid']], $rolls);
        }
    }

    public function type(string $folded): ?TypeRecord
    {
        return $this->types[$this->typeNames[$folded] ?? -1] ?? null;
    }

    public function typeById(int $typeId): ?TypeRecord
    {
        return $this->types[$typeId] ?? null;
    }

    public function attribute(string $folded): ?AttributeRecord
    {
        return $this->attributes[$this->attributeNames[$folded] ?? -1] ?? null;
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
     * Where names are shared, published wins, then the lowest ID (§4.2).
     *
     * @param list<Named> $entries
     * @return array<array-key, int>
     */
    private static function byName(array $entries): array
    {
        usort($entries, static fn($a, $b) => [!$a['published'], $a['id']] <=> [!$b['published'], $b['id']]);
        $index = [];
        foreach ($entries as $e) {
            $index[Esf::fold($e['name'])] ??= $e['id'];
        }
        return $index;
    }
}
