<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** One line of a fit. */
final class Entry
{
    /**
     * @param array<int, float> $overrides
     */
    public function __construct(
        public ?int $type,
        public ?int $count = null,
        public ?string $fitName = null,
        public ?int $charge = null,
        public ?int $chargeCount = null,
        public ?int $mutaplasmid = null,
        public array $overrides = [],
        public ?State $state = null,
        public ?Location $location = null,
    ) {}
}
