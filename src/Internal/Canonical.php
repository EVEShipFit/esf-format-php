<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\Catalog;
use EveShipFit\EsfFormat\Entry;
use EveShipFit\EsfFormat\EsfException;
use EveShipFit\EsfFormat\EsfFit;
use EveShipFit\EsfFormat\Kind;
use EveShipFit\EsfFormat\Location;
use EveShipFit\EsfFormat\State;

/**
 * Canonical form (SPEC §8).
 *
 * @internal
 */
final class Canonical
{
    private const GROUPS = [
        ...Text::RACKS,
        Resolver::DRONES,
        Resolver::FIGHTERS,
        'cargo',
        ...Text::HOLDS,
        ...Resolver::PLUGGED,
    ];

    public static function quote(string $s): string
    {
        return '"' . str_replace('"', '""', $s) . '"';
    }

    public static function needsQuotes(string $name): bool
    {
        $words = explode(' ', $name);
        $first = $words[0][0] ?? '';
        if ($first === '' || $first === '-' || $first === '%' || Text::isCount($words[0])) {
            return true;
        }
        foreach ($words as $word) {
            if ($word === '' || str_contains(Text::SIGILS, $word[0])) {
                return true;
            }
        }
        return false;
    }

    public static function quoteType(string $name): string
    {
        return self::needsQuotes($name) ? self::quote($name) : $name;
    }

    /**
     * @throws EsfException
     */
    public static function fit(Fit $fit, Catalog $catalog): CanonFit
    {
        $groups = [];
        foreach ($fit->items as $item) {
            if ($item instanceof EmptySlot || in_array($item->place, Text::RACKS, true)) {
                continue;
            }
            $group = $item->place;
            if ($group === 'bay') {
                $group = $item->type->kind === Kind::Fighter ? Resolver::FIGHTERS : Resolver::DRONES;
            }
            $groups[$group][] = $item;
        }

        $chunks = [];
        foreach (self::GROUPS as $group) {
            $items = $groups[$group] ?? [];
            $chunk = match (true) {
                in_array($group, Text::RACKS, true) => self::rack($fit, $group, $catalog),
                $group === Resolver::DRONES => self::drones($items, $fit, $catalog),
                $group === Resolver::FIGHTERS => self::fighters($items, $fit, $catalog),
                default => self::sorted($items, $fit, $catalog),
            };
            if ($chunk !== []) {
                $chunks[] = $chunk;
            }
        }

        $modeText = null;
        if ($fit->hull !== null && $fit->mode !== null) {
            $hull = $fit->hull;
            $own = Resolver::modeName($hull, $fit->mode);
            $others = array_map(static fn($m) => Resolver::modeName($hull, $m), Lookup::modes($catalog, $hull));
            $modeText = Names::shortestUnique($own, self::without($others, $own));
        }

        return new CanonFit($fit->hull, $fit->name(), $fit->mode, $modeText, $chunks);
    }

    /**
     * @param list<CanonFit> $fits
     */
    public static function render(array $fits): string
    {
        return implode("\n", array_map(static fn($fit) => $fit->text(), $fits));
    }

    public static function toModel(CanonFit $fit): EsfFit
    {
        $entries = [];
        foreach ($fit->lines() as $line) {
            $overrides = [];
            foreach ($line->overrides as [$attribute, $value]) {
                $overrides[$attribute->id] = $value;
            }
            $entries[] = new Entry(
                $line->type?->id,
                $line->count,
                $line->fitName,
                $line->charge?->id,
                $line->chargeCount,
                $line->mutaplasmid?->type->id,
                $overrides,
                $line->state === null ? null : State::from($line->state),
                $line->location === null ? null : Location::from($line->location),
            );
        }
        return new EsfFit($fit->hull?->id, $fit->name, $fit->mode?->id, $entries);
    }

    /**
     * Drop the first occurrence of a name.
     *
     * @param list<string> $names
     * @return list<string>
     */
    private static function without(array $names, string $name): array
    {
        $at = array_search($name, $names, true);
        if ($at !== false) {
            array_splice($names, $at, 1);
        }
        return $names;
    }

    /** The state an item has when its line names none; null is active. */
    private static function defaultState(Item $item): ?string
    {
        if (in_array($item->place, Resolver::PLUGGED, true) || !$item->type->active) {
            return 'on';
        }
        return null;
    }

    private static function line(Item $item, Fit $fit, Catalog $catalog): CanonLine
    {
        $line = $item->line;
        $out = new CanonLine($item->type);

        if ($item->reference !== null) {
            $out->fitName = $item->reference->name();
        }

        if ($item->charge !== null) {
            $out->charge = $item->charge;
            $full = Lookup::fullLoad($item->type, $item->charge);
            if ($line->chargeCount !== null && $line->chargeCount !== $full) {
                $out->chargeCount = $line->chargeCount;
            }
        }

        $overrides = [];
        foreach ($item->overrides as [$attribute, $value]) {
            $overrides[$attribute->id] = [$attribute, $value == 0.0 ? 0.0 : $value];
        }
        if ($item->mutaplasmid !== null) {
            $out->mutaplasmid = $item->mutaplasmid;
            $own = $item->mutaplasmid->type->name;
            $others = array_map(static fn($m) => $m->type->name, Lookup::mutaplasmids($catalog, $item->type));
            $out->mutaplasmidName = Names::shortestUnique($own, self::without($others, $own));
            foreach ($item->mutaplasmid->rolls as $attributeId => $base) {
                $overrides[$attributeId] ??= [Lookup::attribute($catalog, $attributeId), $base];
            }
        }
        $keys = array_map(static fn($o) => [Names::fold($o[0]->name), $o[0]->name], $overrides);
        uksort($overrides, static fn($a, $b) => strcmp($keys[$a][0], $keys[$b][0]) ?: strcmp($keys[$a][1], $keys[$b][1]));
        $out->overrides = array_values($overrides);

        if ($line->state !== null && $line->state !== self::defaultState($item)) {
            $out->state = $line->state;
        }

        if ($item->place !== Resolver::defaultPlace($item->type->kind, $fit->cargoOnly)
            && !in_array($item->place, Text::RACKS, true)
        ) {
            $out->location = $item->place;
        }
        return $out;
    }

    /**
     * Merge adjacent identical repeatable lines into one count.
     *
     * @param list<array{bool, CanonLine}> $lines
     * @return list<CanonLine>
     * @throws EsfException
     */
    private static function collapse(array $lines): array
    {
        $out = [];
        $last = null;
        foreach ($lines as [$repeatable, $line]) {
            if ($last !== null && $repeatable && $last[0] && $last[1]->body() === $line->body()) {
                $prev = $last[1];
                $prev->count = ($prev->count ?? 1) + ($line->count ?? 1);
                if ($prev->count > Text::UINT32_MAX) {
                    throw new EsfException("lines collapse into a count of {$prev->count}, which is too large");
                }
                continue;
            }
            $last = [$repeatable, $line];
            $out[] = $line;
        }
        foreach ($out as $line) {
            if ($line->count === 1) {
                $line->count = null;
            }
        }
        return $out;
    }

    /**
     * @return list<CanonLine>
     */
    private static function rack(Fit $fit, string $rack, Catalog $catalog): array
    {
        $runs = $fit->racks[$rack] ?? [];
        while ($runs !== [] && end($runs)->item === null) {
            array_pop($runs);
        }
        $lines = [];
        foreach ($runs as $run) {
            $line = $run->item === null
                ? new CanonLine(null, location: $rack)
                : self::line($run->item, $fit, $catalog);
            $line->count = $run->length;
            $lines[] = [true, $line];
        }
        return self::collapse($lines);
    }

    /**
     * @param list<Item> $items
     * @return list<CanonLine>
     */
    private static function sorted(array $items, Fit $fit, Catalog $catalog): array
    {
        $lines = [];
        foreach ($items as $item) {
            $line = self::line($item, $fit, $catalog);
            $repeatable = !$item->stored();
            $count = $item->line->count ?? 1;
            if ($repeatable) {
                $line->count = $count;
                $text = $line->body();
            } else {
                $line->count = $count === 1 ? null : $count;
                $text = $line->text();
            }
            $lines[] = [Names::fold($item->type->name), $text, $repeatable, $line];
        }
        usort($lines, static fn($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
        return self::collapse(array_map(static fn($l) => [$l[2], $l[3]], $lines));
    }

    /**
     * @param list<Item> $items
     * @return list<CanonLine>
     */
    private static function fighters(array $items, Fit $fit, Catalog $catalog): array
    {
        $lines = [];
        $bay = [];
        foreach ($items as $item) {
            if ($item->place !== Resolver::FIGHTERS) {
                $bay[] = $item;
                continue;
            }
            $line = self::line($item, $fit, $catalog);
            $count = $item->line->count;
            if ($count !== null && $count !== $item->type->squadronSize) {
                $line->count = $count;
            }
            $lines[] = $line;
        }
        return [...$lines, ...self::sorted($bay, $fit, $catalog)];
    }

    /**
     * @param list<Item> $items
     * @return list<CanonLine>
     */
    private static function drones(array $items, Fit $fit, Catalog $catalog): array
    {
        $space = array_values(array_filter($items, static fn($i) => $i->place === Resolver::DRONES));
        $bay = array_values(array_filter($items, static fn($i) => $i->place !== Resolver::DRONES));
        return [...self::sorted($space, $fit, $catalog), ...self::sorted($bay, $fit, $catalog)];
    }
}
