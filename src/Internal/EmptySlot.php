<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/** @internal */
final readonly class EmptySlot
{
    public string $place;

    public function __construct(public EmptyLine $line)
    {
        $this->place = $line->rack;
    }
}
