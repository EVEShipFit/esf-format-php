<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\TypeRecord;

/** @internal */
final class Item
{
    public ?TypeRecord $charge = null;
    public ?MutaplasmidRecord $mutaplasmid = null;
    /** @var list<array{AttributeRecord, float}> */
    public array $overrides = [];
    public ?Fit $reference = null;

    public function __construct(
        public readonly ItemLine $line,
        public readonly TypeRecord $type,
        public string $place,
    ) {}

    public function stored(): bool
    {
        return in_array($this->place, Text::STORED, true);
    }
}
