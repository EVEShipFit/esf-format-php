<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * One line of a fit: an item, an empty slot, or another fit carried by this one.
 *
 * Only `$type` is needed; every other field is null or empty when it is the default.
 * Entries can be in any order, except within a rack: there, the order is the slot order,
 * unless an entry sets `$slot`.
 */
final class Entry
{
    /**
     * @param int|null $type The type ID of the item, like 2889 for a 200mm AutoCannon II.
     *     Null for an empty slot. An empty slot must set `$location` to its rack, like Location::High.
     * @param int|null $count How many there are. Null means one.
     *     What a count means depends on where the item goes (esf/1 spec §6.2). For a fitted
     *     module or a deployed drone, it is that many of the same line: 3 guns take 3 slots.
     *     For a stored item, it is the size of one stack. For a fighter, it is how many are
     *     in the squadron; null means a full squadron. A plugged-in implant or booster takes
     *     no count.
     * @param string|null $fitName The name of another fit in the same list, which this entry carries.
     *     Only for a ship, structure or container. The other fit must have this entry's
     *     `$type` as its hull and this as its name, ignoring case. For example, a Heron with
     *     fit name "Scout" and Location::Frigate carries that fit in the Frigate Escape Bay.
     *     Null means an unfitted ship or an empty container.
     * @param int|null $charge The type ID of the charge loaded into this item, like ammunition or a script. Null for none.
     * @param int|null $chargeCount How many of `$charge` are loaded into each item.
     *     Null means a full load: the item's capacity divided by the charge's volume, rounded
     *     down. Only set together with `$charge`.
     * @param int|null $mutaplasmid The type ID of the mutaplasmid applied to this item, which makes it abyssal.
     *     `$type` is then the base type the mutaplasmid applies to, and `$overrides` holds the
     *     rolled values. Null for a normal item.
     * @param array<int, float> $overrides Attribute values that replace the type's own, by attribute ID,
     *     like `[20 => 524.0]` for speedFactor.
     *     Each value is the new base value, not a change on top of it. Attributes not listed
     *     keep the type's value. Empty for none.
     * @param State|null $state Whether the item is offline, online or running. Null means its default; see {@see State}.
     * @param Location|null $location Where the item goes. Null means where its type goes by default; see {@see Location}.
     * @param int|null $slot The slot in the `$location` rack, counting from 1, like 3 for the third high slot.
     *     Set `$location` to the rack too. Null means the next free slot. {@see Esf::load()} never sets
     *     this: it gives the rack in slot order, with empty slots in between.
     *     It can't be combined with `$count`, and the item must go in that rack. Each slot can be
     *     pinned once, and the slot is at most 4294967295.
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
        public ?int $slot = null,
    ) {}
}
