<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

interface MutaplasmidCatalog extends Catalog
{
    /**
     * @return list<MutaplasmidRecord>
     */
    public function mutaplasmids(int $typeId): array;
}
