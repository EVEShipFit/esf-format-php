<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

final readonly class AttributeRecord
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
