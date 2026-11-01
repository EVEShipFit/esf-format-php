<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\TypeRecord;

/** @internal */
final readonly class CanonFit
{
    /**
     * @param list<list<CanonLine>> $groups
     */
    public function __construct(
        public ?TypeRecord $hull,
        public ?string $name,
        public ?TypeRecord $mode,
        public ?string $modeText,
        public array $groups,
    ) {}

    /**
     * @return list<CanonLine>
     */
    public function lines(): array
    {
        return array_merge(...$this->groups);
    }

    public function text(): string
    {
        $hull = [$this->hull === null ? '-' : Canonical::quoteType($this->hull->name)];
        if ($this->name !== null) {
            $hull[] = Canonical::quote($this->name);
        }
        if ($this->modeText !== null) {
            $hull[] = '/' . Canonical::quoteType($this->modeText);
        }
        $out = "%esf/1\n" . implode(' ', $hull) . "\n";
        foreach ($this->groups as $group) {
            $out .= "\n";
            foreach ($group as $line) {
                $out .= $line->text() . "\n";
            }
        }
        return $out;
    }
}
