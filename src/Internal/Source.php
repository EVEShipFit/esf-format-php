<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\Catalog;
use EveShipFit\EsfFormat\Entry;
use EveShipFit\EsfFormat\EsfError;
use EveShipFit\EsfFormat\EsfFit;

/**
 * Fits as the text they stand for, and the check that their IDs match it (SPEC §10.2).
 *
 * @internal
 */
final class Source
{
    /**
     * The text document the fits stand for, and the [fit, entry] each line is.
     *
     * @param list<EsfFit> $fits
     * @return array{string, list<array{int, ?int}>}
     * @throws EsfError
     */
    public static function source(array $fits, Catalog $catalog): array
    {
        if ($fits === []) {
            throw new EsfError('a document holds at least one fit');
        }
        $out = '';
        $where = [];
        foreach ($fits as $i => $fit) {
            $n = $i + 1;
            try {
                $out .= "%esf/1\n" . self::hull($fit, $catalog) . "\n";
            } catch (EsfError $e) {
                throw new EsfError($e->reason, fitNumber: $n);
            }
            $where[] = [$n, null];
            $where[] = [$n, null];
            foreach ($fit->entries as $j => $entry) {
                try {
                    $out .= self::entry($entry, $catalog) . "\n";
                } catch (EsfError $e) {
                    throw new EsfError($e->reason, fitNumber: $n, entryNumber: $j + 1);
                }
                $where[] = [$n, $j + 1];
            }
        }
        return [$out, $where];
    }

    /**
     * Check that every ID is the one its name resolves to.
     *
     * @param list<Fit> $resolved
     * @param list<EsfFit> $fits
     * @throws EsfError
     */
    public static function verify(array $resolved, array $fits): void
    {
        foreach ($resolved as $i => $fit) {
            $given = $fits[$i];
            try {
                self::same($fit->hull?->id, $given->hull, 'hull');
                self::same($fit->mode?->id, $given->mode, 'tactical mode');
            } catch (EsfError $e) {
                throw new EsfError($e->reason, fitNumber: $i + 1);
            }
            foreach ($fit->items as $j => $item) {
                if ($item instanceof EmptySlot) {
                    continue;
                }
                $entry = $given->entries[$j];
                try {
                    self::same($item->type->id, $entry->type, 'type');
                    self::same($item->charge?->id, $entry->charge, 'charge');
                    self::same($item->mutaplasmid?->type->id, $entry->mutaplasmid, 'mutaplasmid');
                    $got = array_map(static fn($o) => $o[0]->id, $item->overrides);
                    $want = array_keys($entry->overrides);
                    if ($got !== $want) {
                        $want = implode(', ', $want);
                        $got = implode(', ', $got);
                        throw new EsfError("attribute IDs [{$want}] resolve to [{$got}]");
                    }
                } catch (EsfError $e) {
                    throw new EsfError($e->reason, fitNumber: $i + 1, entryNumber: $j + 1);
                }
            }
        }
    }

    private static function name(string $text): string
    {
        if (strpbrk($text, "\r\n") !== false) {
            throw new EsfError('a fit name contains CR or LF');
        }
        return Canonical::quote($text);
    }

    private static function typeName(Catalog $catalog, int $typeId, string $what): string
    {
        $type = $catalog->typeById($typeId) ?? throw new EsfError("unknown {$what} type ID {$typeId}");
        return Canonical::quoteType($type->name);
    }

    private static function number(float $value): string
    {
        if (!is_finite($value)) {
            throw new EsfError('an override value is not finite');
        }
        return Number::format($value);
    }

    private static function entry(Entry $e, Catalog $catalog): string
    {
        if (($e->count !== null && $e->count < 1) || ($e->chargeCount !== null && $e->chargeCount < 1)) {
            throw new EsfError('a count must be at least 1');
        }
        if ($e->type !== null && $e->location !== null && in_array($e->location->value, Text::RACKS, true)) {
            throw new EsfError('a rack location is only for an empty slot');
        }
        if ($e->chargeCount !== null && $e->charge === null) {
            throw new EsfError('a charge count is set without a charge');
        }

        $parts = [];
        if ($e->count !== null) {
            $parts[] = "{$e->count}x";
        }
        $parts[] = $e->type === null ? '-' : self::typeName($catalog, $e->type, 'item');
        if ($e->fitName !== null && $e->fitName !== '') {
            $parts[] = self::name($e->fitName);
        }
        if ($e->charge !== null) {
            $count = $e->chargeCount === null ? '' : "{$e->chargeCount}x ";
            $parts[] = ':' . $count . self::typeName($catalog, $e->charge, 'charge');
        }
        if ($e->mutaplasmid !== null) {
            $parts[] = '+' . self::typeName($catalog, $e->mutaplasmid, 'mutaplasmid');
        }
        if ($e->overrides !== []) {
            $pairs = [];
            foreach ($e->overrides as $attributeId => $value) {
                $attribute = $catalog->attributeById($attributeId)
                    ?? throw new EsfError("unknown attribute ID {$attributeId}");
                $pairs[] = $attribute->name . ' ' . self::number($value);
            }
            $parts[] = '{' . implode(', ', $pairs) . '}';
        }
        if ($e->state !== null) {
            $parts[] = '!' . $e->state->value;
        }
        if ($e->location !== null) {
            $parts[] = '@' . $e->location->value;
        }
        return implode(' ', $parts);
    }

    private static function hull(EsfFit $fit, Catalog $catalog): string
    {
        $hull = [$fit->hull === null ? '-' : self::typeName($catalog, $fit->hull, 'hull')];
        if ($fit->name !== null && $fit->name !== '') {
            $hull[] = self::name($fit->name);
        }
        if ($fit->mode !== null) {
            $hull[] = '/' . self::typeName($catalog, $fit->mode, 'tactical mode');
        }
        return implode(' ', $hull);
    }

    private static function same(?int $got, ?int $want, string $what): void
    {
        if ($got !== $want) {
            $want ??= 'null';
            $got ??= 'null';
            throw new EsfError("{$what} ID {$want} is not what its name resolves to ({$got})");
        }
    }
}
