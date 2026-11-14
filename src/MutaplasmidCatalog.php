<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * A {@see Catalog} that also knows which mutaplasmids apply to each type.
 *
 * Implement this instead of {@see Catalog} when you want mutaplasmids. Without it,
 * no type has mutaplasmids.
 */
interface MutaplasmidCatalog extends Catalog
{
    /**
     * The mutaplasmids that can be applied to a type. See {@see MutaplasmidRecord} for where they come from.
     *
     * Return an empty list for a type no mutaplasmid applies to.
     *
     * @return list<MutaplasmidRecord>
     */
    public function mutaplasmids(int $typeId): array;
}
