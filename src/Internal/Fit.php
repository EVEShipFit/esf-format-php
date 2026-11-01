<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\TypeRecord;

/** @internal */
final class Fit
{
    /** @var list<Item|EmptySlot> */
    public array $items = [];
    /** @var array<string, list<Run>> */
    public array $racks = [];

    public function __construct(
        public readonly FitBlock $block,
        public readonly ?TypeRecord $hull,
        public readonly ?TypeRecord $mode,
        public readonly bool $cargoOnly,
    ) {}

    public function name(): ?string
    {
        return $this->block->fitName;
    }

    /**
     * Each item that carries a fit.
     *
     * @return list<Item>
     */
    public function references(): array
    {
        $out = [];
        foreach ($this->items as $item) {
            if ($item instanceof Item && $item->reference !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }
}
