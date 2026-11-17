<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/** @internal */
final readonly class TypeRef
{
    public function __construct(
        public string $name,
        public ?int $id = null,
    ) {}
}
