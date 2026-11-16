<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/** @internal */
final class ItemLine
{
    public ?string $fitName = null;
    public string|int|null $charge = null;
    public ?int $chargeCount = null;
    public ?string $mutaplasmid = null;
    /** @var list<array{string, string}> */
    public array $overrides = [];
    public ?string $state = null;
    public ?string $location = null;
    public ?int $index = null;

    public function __construct(
        public readonly int $number,
        public readonly string|int $name,
        public readonly ?int $count = null,
    ) {}
}
