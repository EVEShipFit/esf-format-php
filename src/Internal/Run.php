<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/**
 * Consecutive slots of a rack holding the same item; null for empty slots.
 *
 * @internal
 */
final readonly class Run
{
    public function __construct(
        public int $start,
        public int $length,
        public ?Item $item,
    ) {}
}
