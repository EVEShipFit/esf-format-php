<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

final readonly class TypeRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public Kind $kind,
        public bool $active,
        public float $capacity,
        public float $volume,
        public ?int $squadronSize = null,
    ) {}
}
