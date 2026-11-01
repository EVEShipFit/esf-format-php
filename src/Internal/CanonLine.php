<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\AttributeRecord;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\TypeRecord;

/** @internal */
final class CanonLine
{
    public ?string $fitName = null;
    public ?TypeRecord $charge = null;
    public ?int $chargeCount = null;
    public ?MutaplasmidRecord $mutaplasmid = null;
    public ?string $mutaplasmidName = null;
    /** @var list<array{AttributeRecord, float}> */
    public array $overrides = [];
    public ?string $state = null;

    public function __construct(
        public readonly ?TypeRecord $type,
        public ?int $count = null,
        public ?string $location = null,
    ) {}

    public function body(): string
    {
        $parts = [$this->type === null ? '-' : Canonical::quoteType($this->type->name)];
        if ($this->fitName !== null) {
            $parts[] = Canonical::quote($this->fitName);
        }
        if ($this->charge !== null) {
            $count = $this->chargeCount === null ? '' : "{$this->chargeCount}x ";
            $parts[] = ':' . $count . Canonical::quoteType($this->charge->name);
        }
        if ($this->mutaplasmidName !== null) {
            $parts[] = '+' . Canonical::quoteType($this->mutaplasmidName);
        }
        if ($this->overrides !== []) {
            $pairs = array_map(static fn($o) => $o[0]->name . ' ' . Number::format($o[1]), $this->overrides);
            $parts[] = '{' . implode(', ', $pairs) . '}';
        }
        if ($this->state !== null) {
            $parts[] = '!' . $this->state;
        }
        if ($this->location !== null) {
            $parts[] = '@' . $this->location;
        }
        return implode(' ', $parts);
    }

    public function text(): string
    {
        return $this->count === null ? $this->body() : "{$this->count}x {$this->body()}";
    }
}
