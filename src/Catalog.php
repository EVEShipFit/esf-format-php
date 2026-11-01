<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** Types and attributes, by folded name (§6.10) or by ID. */
interface Catalog
{
    public function type(string $folded): ?TypeRecord;

    public function typeById(int $typeId): ?TypeRecord;

    public function attribute(string $folded): ?AttributeRecord;

    public function attributeById(int $attributeId): ?AttributeRecord;
}
