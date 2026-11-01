<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\Catalog;
use EveShipFit\EsfFormat\EsfError;
use EveShipFit\EsfFormat\Kind;
use EveShipFit\EsfFormat\MutaplasmidRecord;
use EveShipFit\EsfFormat\TypeRecord;

/**
 * Resolving names against the Catalog, and the semantic rules (SPEC §4.2, §5, §6).
 *
 * @internal
 */
final class Resolver
{
    public const DRONES = 'drones';
    public const FIGHTERS = 'fighters';
    public const IMPLANTS = 'implants';
    public const BOOSTERS = 'boosters';
    public const PLUGGED = [self::IMPLANTS, self::BOOSTERS];

    /**
     * @param list<FitBlock> $blocks
     * @return list<Fit>
     * @throws EsfError
     */
    public static function resolve(array $blocks, Catalog $catalog): array
    {
        $fits = array_map(static fn($block) => self::hull($block, $catalog), $blocks);
        $named = [];
        foreach ($fits as $fit) {
            $name = $fit->name();
            if ($fit->hull !== null && $name !== null) {
                $named[$fit->hull->id][Names::fold($name)][] = $fit;
            }
        }
        foreach ($fits as $fit) {
            $plugged = [];
            foreach ($fit->block->lines as $line) {
                $fit->items[] = self::line($line, $fit, $named, $catalog);
            }
            foreach ($fit->items as $item) {
                if (!$item instanceof Item || !in_array($item->place, self::PLUGGED, true)) {
                    continue;
                }
                if (isset($plugged[$item->type->id])) {
                    throw new EsfError("'{$item->type->name}' is plugged in twice", $item->line->number);
                }
                $plugged[$item->type->id] = true;
            }
        }
        self::checkCycles($fits);
        foreach ($fits as $fit) {
            $byRack = [];
            foreach ($fit->items as $item) {
                if (in_array($item->place, Text::RACKS, true)) {
                    $byRack[$item->place][] = $item;
                }
            }
            foreach (Text::RACKS as $rack) {
                if (isset($byRack[$rack])) {
                    $fit->racks[$rack] = self::layout($byRack[$rack]);
                }
            }
        }
        return $fits;
    }

    /** Where a type goes in a ship, or null for cargo. */
    public static function shipPlace(Kind $kind): ?string
    {
        return match ($kind) {
            Kind::Sub, Kind::High, Kind::Mid, Kind::Low, Kind::Rig, Kind::Svc => $kind->value,
            Kind::Drone => self::DRONES,
            Kind::Fighter => self::FIGHTERS,
            Kind::Implant => self::IMPLANTS,
            Kind::Booster => self::BOOSTERS,
            default => null,
        };
    }

    public static function defaultPlace(Kind $kind, bool $cargoOnly): string
    {
        return $cargoOnly ? 'cargo' : (self::shipPlace($kind) ?? 'cargo');
    }

    /** A tactical mode's name without the hull's name. */
    public static function modeName(TypeRecord $hull, TypeRecord $mode): string
    {
        return ltrim(mb_substr($mode->name, mb_strlen($hull->name, 'UTF-8'), null, 'UTF-8'), ' ');
    }

    private static function type(Catalog $catalog, string $name, int $line): TypeRecord
    {
        return $catalog->type(Names::fold($name))
            ?? throw new EsfError("unknown type '{$name}'", $line);
    }

    /**
     * @template T of TypeRecord|MutaplasmidRecord
     * @param list<T> $candidates
     * @return T
     */
    private static function pick(array $candidates, string $given, string $what, int $line): TypeRecord|MutaplasmidRecord
    {
        if ($candidates === []) {
            throw new EsfError("no {$what} matches '{$given}'", $line);
        }
        if (count($candidates) > 1) {
            $names = implode(', ', array_map(
                static fn($c) => "'" . ($c instanceof MutaplasmidRecord ? $c->type->name : $c->name) . "'",
                $candidates,
            ));
            throw new EsfError("'{$given}' matches more than one {$what}: {$names}", $line);
        }
        return $candidates[0];
    }

    private static function hull(FitBlock $block, Catalog $catalog): Fit
    {
        if ($block->hull === null) {
            return new Fit($block, null, null, true);
        }

        $hull = self::type($catalog, $block->hull, $block->hullLine);
        if ($hull->kind !== Kind::Hull && $hull->kind !== Kind::Container) {
            throw new EsfError("'{$hull->name}' is not a ship, structure or container", $block->hullLine);
        }

        $mode = null;
        if ($block->mode !== null) {
            $modes = Lookup::modes($catalog, $hull);
            if ($modes === []) {
                throw new EsfError("'{$hull->name}' has no tactical modes", $block->hullLine);
            }
            $matches = array_values(array_filter(
                $modes,
                static fn($m) => Names::isWordPrefix($block->mode, $m->name)
                    || Names::isWordPrefix($block->mode, self::modeName($hull, $m)),
            ));
            $mode = self::pick($matches, $block->mode, "tactical mode of '{$hull->name}'", $block->hullLine);
        }

        return new Fit($block, $hull, $mode, $hull->kind === Kind::Container);
    }

    /**
     * @param array<int, array<string, list<Fit>>> $named
     */
    private static function line(EmptyLine|ItemLine $line, Fit $fit, array $named, Catalog $catalog): Item|EmptySlot
    {
        $n = $line->number;
        if ($line instanceof EmptyLine) {
            if ($fit->cargoOnly) {
                throw new EsfError('a fit without a ship has no slots to keep empty', $n);
            }
            return new EmptySlot($line);
        }

        $t = self::type($catalog, $line->name, $n);
        $kind = $t->kind;
        if ($kind === Kind::Mode) {
            throw new EsfError('a tactical mode is only written on the hull line', $n);
        }

        $item = new Item($line, $t, self::defaultPlace($kind, $fit->cargoOnly));

        if ($line->fitName !== null) {
            if ($kind !== Kind::Hull && $kind !== Kind::Container) {
                throw new EsfError("'{$t->name}' cannot take a fit name", $n);
            }
            $matches = $named[$t->id][Names::fold($line->fitName)] ?? [];
            if (count($matches) !== 1) {
                $what = $matches === [] ? 'no fit' : 'more than one fit';
                throw new EsfError("{$what} matches {$t->name} '{$line->fitName}'", $n);
            }
            $item->reference = $matches[0];
        }

        if ($line->location !== null) {
            if ($fit->cargoOnly && $line->location !== 'cargo') {
                throw new EsfError('a fit without a ship stores everything in cargo', $n);
            }
            if ($line->index !== null && self::shipPlace($kind) !== $line->location) {
                throw new EsfError("'{$t->name}' does not go in the {$line->location} rack", $n);
            }
            if ($line->location === 'bay' && $kind !== Kind::Drone && $kind !== Kind::Fighter) {
                throw new EsfError('@bay is only for drones and fighters', $n);
            }
            $item->place = $line->location;
        }

        $plugged = in_array($item->place, self::PLUGGED, true);
        if ($plugged && $line->count !== null) {
            throw new EsfError('a plugged-in implant or booster takes no count', $n);
        }

        if ($line->state !== null) {
            if ($item->stored()) {
                throw new EsfError('a stored item has no state', $n);
            }
            if ($plugged && $line->state !== 'off') {
                throw new EsfError('an implant or booster takes !off only', $n);
            }
        }

        if ($line->charge !== null) {
            $item->charge = self::type($catalog, $line->charge, $n);
            if ($item->charge->kind !== Kind::Charge) {
                throw new EsfError("'{$item->charge->name}' is not a charge", $n);
            }
        }

        if ($line->mutaplasmid !== null) {
            $given = $line->mutaplasmid;
            $mutaplasmids = array_values(array_filter(
                Lookup::mutaplasmids($catalog, $t),
                static fn($m) => Names::isWordPrefix($given, $m->type->name),
            ));
            $item->mutaplasmid = self::pick($mutaplasmids, $given, "mutaplasmid for '{$t->name}'", $n);
        }

        $seen = [];
        foreach ($line->overrides as [$name, $value]) {
            $attribute = $catalog->attribute(Names::fold($name))
                ?? throw new EsfError("unknown attribute '{$name}'", $n);
            if (isset($seen[$attribute->id])) {
                throw new EsfError("attribute '{$attribute->name}' is overridden twice", $n);
            }
            $seen[$attribute->id] = true;
            $number = (float) $value;
            if (!is_finite($number)) {
                throw new EsfError("value '{$value}' is out of range", $n);
            }
            $item->overrides[] = [$attribute, $number];
        }

        return $item;
    }

    /**
     * A fit cannot contain itself, directly or through other fits.
     *
     * @param list<Fit> $fits
     */
    private static function checkCycles(array $fits): void
    {
        $done = [];
        foreach ($fits as $start) {
            if (isset($done[spl_object_id($start)])) {
                continue;
            }
            $path = [spl_object_id($start) => true];
            $stack = [[$start, $start->references(), 0]];
            while ($stack !== []) {
                $top = count($stack) - 1;
                [$fit, $items, $at] = $stack[$top];
                if ($at === count($items)) {
                    array_pop($stack);
                    unset($path[spl_object_id($fit)]);
                    $done[spl_object_id($fit)] = true;
                    continue;
                }
                $stack[$top][2]++;
                $item = $items[$at];
                $ref = $item->reference;
                assert($ref !== null);
                if (isset($path[spl_object_id($ref)])) {
                    throw new EsfError('a fit cannot contain itself', $item->line->number);
                }
                if (!isset($done[spl_object_id($ref)])) {
                    $path[spl_object_id($ref)] = true;
                    $stack[] = [$ref, $ref->references(), 0];
                }
            }
        }
    }

    /**
     * Assign slots in one rack (§6.3).
     *
     * @param list<Item|EmptySlot> $items
     * @return list<Run>
     */
    private static function layout(array $items): array
    {
        $pinned = [];
        foreach ($items as $item) {
            $index = $item->line->index;
            if ($index === null) {
                continue;
            }
            if (isset($pinned[$index])) {
                throw new EsfError("slot {$index} is pinned twice", $item->line->number);
            }
            $pinned[$index] = $item;
        }

        $runs = [];
        foreach ($pinned as $index => $item) {
            $runs[] = new Run($index, 1, $item instanceof Item ? $item : null);
        }
        $pins = array_keys($pinned);
        sort($pins);
        $nextPin = 0;
        $pos = 1;
        foreach ($items as $item) {
            if ($item->line->index !== null) {
                continue;
            }
            $remaining = $item->line->count ?? 1;
            while ($remaining > 0) {
                while (isset($pinned[$pos])) {
                    $pos++;
                }
                while ($nextPin < count($pins) && $pins[$nextPin] < $pos) {
                    $nextPin++;
                }
                $room = $nextPin < count($pins) ? $pins[$nextPin] - $pos : $remaining;
                $take = min($remaining, $room);
                $runs[] = new Run($pos, $take, $item instanceof Item ? $item : null);
                $pos += $take;
                $remaining -= $take;
            }
        }
        usort($runs, static fn($a, $b) => $a->start <=> $b->start);

        $gapless = [];
        $pos = 1;
        foreach ($runs as $run) {
            if ($run->start > $pos) {
                $gapless[] = new Run($pos, $run->start - $pos, null);
            }
            $gapless[] = $run;
            $pos = $run->start + $run->length;
        }
        return $gapless;
    }
}
