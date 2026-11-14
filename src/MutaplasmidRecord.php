<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * One mutaplasmid that can be applied to a base type, like "Unstable 5MN Microwarpdrive Mutaplasmid".
 *
 * With the SDE, these come from `dynamicItemAttributes.jsonl`. Each entry there is one mutaplasmid.
 * Its `inputOutputMapping` is a list; the base types it applies to are all the `applicableTypes`
 * across that list.
 */
final readonly class MutaplasmidRecord
{
    /**
     * @param TypeRecord $type The mutaplasmid's own type: the type whose ID is the entry's `_key`.
     * @param array<int, float> $rolls The attributes the mutaplasmid changes, each with the base type's
     *     value before the change, by attribute ID.
     *     The keys are the `_key` of each item in the entry's `attributeIDs`; its `min` and `max`
     *     are not needed. Each value is the base type's value of that attribute in `typeDogma.jsonl`.
     *     When the base type does not have it, use the attribute's `defaultValue` in
     *     `dogmaAttributes.jsonl`, or 0 when that is missing too.
     */
    public function __construct(
        public TypeRecord $type,
        public array $rolls,
    ) {}
}
