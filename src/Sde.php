<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * Helpers to build a {@see Catalog} from the SDE, for the fields that take more than a lookup.
 *
 * Each helper takes plain values from the SDE files, so it works however you load them.
 */
final class Sde
{
    private const CATEGORY_SHIP = 6;
    private const CATEGORY_MODULE = 7;
    private const CATEGORY_CHARGE = 8;
    private const CATEGORY_DRONE = 18;
    private const CATEGORY_IMPLANT = 20;
    private const CATEGORY_SUBSYSTEM = 32;
    private const CATEGORY_STRUCTURE = 65;
    private const CATEGORY_STRUCTURE_MODULE = 66;
    private const CATEGORY_FIGHTER = 87;

    private const GROUP_BOOSTER = 303;
    private const GROUP_SHIP_MODIFIERS = 1306;
    private const GROUP_CONTAINERS = [12, 340, 448, 649];

    private const EFFECT_ONLINE = 16;
    private const EFFECT_CATEGORY_ACTIVE = 1;
    private const EFFECT_CATEGORY_TARGET = 2;
    private const SLOT_EFFECTS = [12 => Kind::High, 13 => Kind::Mid, 11 => Kind::Low, 2663 => Kind::Rig, 6306 => Kind::Svc];

    private const ATTRIBUTE_SQUADRON_SIZE = 2215;

    /**
     * Works out what a type is. See {@see Kind} for each rule.
     *
     * @param int $categoryId The `categoryID` of the type's group, in `groups.jsonl`.
     * @param int $groupId The type's `groupID`, in `types.jsonl`.
     * @param iterable<int> $effectIds The `effectID`s in the type's `dogmaEffects`, in `typeDogma.jsonl`.
     */
    public static function kind(int $categoryId, int $groupId, iterable $effectIds): Kind
    {
        if ($groupId === self::GROUP_SHIP_MODIFIERS) {
            return Kind::Mode;
        }
        if (in_array($groupId, self::GROUP_CONTAINERS, true)) {
            return Kind::Container;
        }
        if ($groupId === self::GROUP_BOOSTER) {
            return Kind::Booster;
        }
        return match ($categoryId) {
            self::CATEGORY_SHIP, self::CATEGORY_STRUCTURE => Kind::Hull,
            self::CATEGORY_MODULE, self::CATEGORY_STRUCTURE_MODULE => self::slot($effectIds),
            self::CATEGORY_SUBSYSTEM => Kind::Sub,
            self::CATEGORY_DRONE => Kind::Drone,
            self::CATEGORY_FIGHTER => Kind::Fighter,
            self::CATEGORY_CHARGE => Kind::Charge,
            self::CATEGORY_IMPLANT => Kind::Implant,
            default => Kind::Other,
        };
    }

    /**
     * Works out whether a type runs when it is turned on (§6.4), for {@see TypeRecord::$active}.
     *
     * A type runs when one of its effects is in category 1 (active) or 2 (target).
     * The SDE puts the `online` effect in category 1 too, but going online is not
     * running, so that one does not count.
     *
     * @param array<int, int> $effects Each `effectID` in the type's `dogmaEffects` (in `typeDogma.jsonl`),
     *     mapped to that effect's `effectCategoryID` (in `dogmaEffects.jsonl`). Use 0 when an effect has no `effectCategoryID`.
     */
    public static function active(array $effects): bool
    {
        foreach ($effects as $effectId => $category) {
            if (
                $effectId !== self::EFFECT_ONLINE
                && ($category === self::EFFECT_CATEGORY_ACTIVE || $category === self::EFFECT_CATEGORY_TARGET)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reads how many fighters make up a full squadron, for {@see TypeRecord::$squadronSize}.
     *
     * Returns null when the type has no `fighterSquadronMaxSize` (2215), which only fighters have.
     *
     * @param array<int, int|float> $attributes Each `attributeID` in the type's `dogmaAttributes`
     *     (in `typeDogma.jsonl`), mapped to its `value`.
     */
    public static function squadronSize(array $attributes): ?int
    {
        $value = $attributes[self::ATTRIBUTE_SQUADRON_SIZE] ?? null;
        return $value === null ? null : (int) $value;
    }

    /**
     * Finds which hull a tactical mode belongs to, for {@see ModeCatalog::modes()}.
     *
     * The SDE does not link a mode to its hull. Instead, a mode's name starts with
     * its hull's name: "Hecate Sharpshooter Mode" belongs to the Hecate. This tries
     * the longest start of `$modeName` first, a whole word at a time.
     *
     * Returns the hull's type ID, or null when no ship name matches.
     *
     * @param string $modeName The mode's English name, like "Hecate Sharpshooter Mode".
     * @param array<array-key, int> $shipNames A {@see Sde::nameIndex()} of every type in category `Ship` (6).
     */
    public static function modeHull(string $modeName, array $shipNames): ?int
    {
        $words = explode(' ', $modeName);
        for ($n = count($words) - 1; $n >= 1; $n--) {
            $hull = $shipNames[Esf::fold(implode(' ', array_slice($words, 0, $n)))] ?? null;
            if ($hull !== null) {
                return $hull;
            }
        }
        return null;
    }

    /**
     * Builds the name store that {@see Catalog::type()} and {@see Catalog::attribute()} look names up in.
     *
     * Give it every type, or every dogma attribute, including unpublished ones. Each name is
     * case-folded with {@see Esf::fold()}, the same way the library does with the names in a fit.
     * Where names are shared, the published one wins, then the lowest ID (§4.2).
     * In the SDE, a type or attribute without `published` counts as not published.
     *
     * Returns an array from case-folded name to ID. PHP stores an all-digit name like "123" as an int key;
     * looking it up by string still works.
     *
     * @template T
     * @param iterable<T> $entries The types or attributes, in any form.
     * @param callable(T): int $id Gives the ID of an entry.
     * @param callable(T): string $name Gives the name of an entry.
     * @param callable(T): bool $published Gives whether an entry is published.
     * @return array<array-key, int>
     */
    public static function nameIndex(iterable $entries, callable $id, callable $name, callable $published): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [!$published($entry), $id($entry), $name($entry)];
        }
        usort($rows, static fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $index = [];
        foreach ($rows as [, $entryId, $entryName]) {
            $index[Esf::fold($entryName)] ??= $entryId;
        }
        return $index;
    }

    /**
     * @param iterable<int> $effectIds
     */
    private static function slot(iterable $effectIds): Kind
    {
        foreach ($effectIds as $effectId) {
            if (isset(self::SLOT_EFFECTS[$effectId])) {
                return self::SLOT_EFFECTS[$effectId];
            }
        }
        return Kind::Other;
    }
}
