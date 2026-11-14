<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * One type, like "Rifter" or "200mm AutoCannon II", as the library needs it.
 *
 * With the SDE, most fields come from `types.jsonl`. `$kind`, `$active` and `$squadronSize`
 * also need `groups.jsonl`, `typeDogma.jsonl` and `dogmaEffects.jsonl`, through the helpers in {@see Sde}.
 * Include every type in `types.jsonl`. A type with no row in `typeDogma.jsonl` has no effects
 * and no attributes: give the helpers an empty array.
 */
final readonly class TypeRecord
{
    /**
     * @param int $id The type ID, like 587 for the Rifter. In the SDE this is `_key`.
     * @param string $name The English name, like "Rifter". In the SDE this is `name.en`.
     * @param Kind $kind What it is, which decides where it goes in a fit. Use {@see Sde::kind()} to work this out.
     * @param bool $active Whether the type runs when it is turned on, like a gun or a shield booster.
     *     A plate or a damage control only goes online, so it is false.
     *     Use {@see Sde::active()} to work this out from the type's effects.
     * @param float $capacity How much the type holds, in m³; for a module, how much ammunition fits in it.
     *     In the SDE this is `capacity`. Use 0 when the type has none.
     * @param float $volume How much room one of it takes, in m³. In the SDE this is `volume`. Use 0 when the type has none.
     * @param int|null $squadronSize For a fighter, how many make up a full squadron. Null for anything else.
     *     In the SDE this is the attribute `fighterSquadronMaxSize`; {@see Sde::squadronSize()} reads it for you.
     */
    public function __construct(
        public int $id,
        public string $name,
        public Kind $kind,
        public bool $active,
        public float $capacity,
        public float $volume,
        public ?int $squadronSize = null,
    ) {}
}
