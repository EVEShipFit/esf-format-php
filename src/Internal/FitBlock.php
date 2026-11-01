<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/** @internal */
final class FitBlock
{
    /**
     * @param list<EmptyLine|ItemLine> $lines
     */
    public function __construct(
        public readonly int $number,
        public readonly int $hullLine,
        public ?string $hull = null,
        public ?string $fitName = null,
        public ?string $mode = null,
        public array $lines = [],
    ) {}
}
