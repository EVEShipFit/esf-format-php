<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** A fit: its hull and its entries. */
final class EsfFit
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        public ?int $hull = null,
        public ?string $name = null,
        public ?int $mode = null,
        public array $entries = [],
    ) {}
}
