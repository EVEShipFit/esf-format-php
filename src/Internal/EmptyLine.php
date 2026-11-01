<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/** @internal */
final readonly class EmptyLine
{
    public function __construct(
        public int $number,
        public string $rack,
        public ?int $count = null,
        public ?int $index = null,
    ) {}
}
