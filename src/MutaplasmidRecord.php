<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

final readonly class MutaplasmidRecord
{
    /**
     * @param array<int, float> $rolls base value by attribute ID
     */
    public function __construct(
        public TypeRecord $type,
        public array $rolls,
    ) {}
}
