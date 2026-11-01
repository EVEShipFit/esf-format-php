<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

interface ModeCatalog extends Catalog
{
    /**
     * @return list<TypeRecord>
     */
    public function modes(int $hullId): array;
}
