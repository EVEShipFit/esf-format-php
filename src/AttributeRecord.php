<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * One dogma attribute, like "speedFactor", as the library needs it.
 *
 * With the SDE, both fields come from `dogmaAttributes.jsonl`.
 */
final readonly class AttributeRecord
{
    /**
     * @param int $id The attribute ID, like 20 for speedFactor. In the SDE this is `_key`.
     * @param string $name The name, like "speedFactor". In the SDE this is `name`; it is not translated.
     */
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
