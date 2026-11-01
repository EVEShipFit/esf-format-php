<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\Catalog;
use EveShipFit\EsfFormat\ModeCatalog;
use EveShipFit\EsfFormat\MutaplasmidCatalog;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\TypeRecord;

/**
 * What esf/1 asks of a Catalog beyond its lookups by name (SPEC §5, §6).
 *
 * @internal
 */
final class Lookup
{
    /**
     * @return list<TypeRecord>
     */
    public static function modes(Catalog $catalog, TypeRecord $hull): array
    {
        return $catalog instanceof ModeCatalog ? $catalog->modes($hull->id) : [];
    }

    /**
     * @return list<MutaplasmidRecord>
     */
    public static function mutaplasmids(Catalog $catalog, TypeRecord $base): array
    {
        return $catalog instanceof MutaplasmidCatalog ? $catalog->mutaplasmids($base->id) : [];
    }

    public static function attribute(Catalog $catalog, int $attributeId): AttributeRecord
    {
        return $catalog->attributeById($attributeId)
            ?? throw new \OutOfBoundsException("the catalog has no attribute {$attributeId}");
    }

    /** How many charges fit in an item (§6.5). */
    public static function fullLoad(TypeRecord $item, TypeRecord $charge): ?int
    {
        if ($item->capacity <= 0 || $charge->volume <= 0) {
            return null;
        }
        return Number::quotient($item->capacity, $charge->volume);
    }
}
