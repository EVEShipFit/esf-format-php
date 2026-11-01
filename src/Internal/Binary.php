<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\Entry;
use EveShipFit\EsfFormat\EsfException;
use EveShipFit\EsfFormat\EsfFit;
use EveShipFit\EsfFormat\Location;
use EveShipFit\EsfFormat\State;

/**
 * The binary form (SPEC §10).
 *
 * @internal
 */
final class Binary
{
    private const STATES = ['off' => 1, 'on' => 2, 'heat' => 3];
    private const LOCATIONS = [
        'high' => 1,
        'mid' => 2,
        'low' => 3,
        'rig' => 4,
        'sub' => 5,
        'svc' => 6,
        'cargo' => 7,
        'bay' => 8,
        'ammo' => 9,
        'booster' => 10,
        'command' => 11,
        'corpse' => 12,
        'depot' => 13,
        'expedition' => 14,
        'fleet' => 15,
        'frigate' => 16,
        'fuel' => 17,
        'gas' => 18,
        'ice' => 19,
        'infrastructure' => 20,
        'maintenance' => 21,
        'mineral' => 22,
        'mining' => 23,
        'moon' => 24,
        'planetary' => 25,
        'quafe' => 26,
        'subsystem' => 27,
    ];

    /**
     * @param list<EsfFit> $fits
     */
    public static function encode(array $fits): string
    {
        $out = '';
        foreach ($fits as $fit) {
            $out .= Wire::record(1, self::encodeFit($fit));
        }
        return $out;
    }

    /**
     * @return list<EsfFit>
     * @throws EsfException
     */
    public static function decode(string $data): array
    {
        $fits = [];
        $reader = new Reader($data);
        while (!$reader->done()) {
            [$field, $wire] = $reader->key();
            if ($field !== 1) {
                $reader->skip($field, $wire);
                continue;
            }
            if ($wire !== Wire::LEN) {
                throw new EsfException('binary: Document.fits has the wrong wire type');
            }
            $fits[] = self::decodeFit($reader->bytes());
        }
        if ($fits === []) {
            throw new EsfException('binary: a document holds at least one fit');
        }
        return $fits;
    }

    private static function encodeEntry(Entry $entry): string
    {
        $out = '';
        if ($entry->type !== null) {
            $out .= Wire::uint(1, $entry->type);
        }
        if ($entry->count !== null) {
            $out .= Wire::uint(2, $entry->count);
        }
        if ($entry->fitName !== null && $entry->fitName !== '') {
            $out .= Wire::record(3, $entry->fitName);
        }
        if ($entry->charge !== null) {
            $out .= Wire::uint(4, $entry->charge);
        }
        if ($entry->chargeCount !== null) {
            $out .= Wire::uint(5, $entry->chargeCount);
        }
        if ($entry->mutaplasmid !== null) {
            $out .= Wire::uint(6, $entry->mutaplasmid);
        }
        if ($entry->overrides !== []) {
            $out .= Wire::packedUints(7, array_keys($entry->overrides));
            $out .= Wire::packedDoubles(8, array_values($entry->overrides));
        }
        if ($entry->state !== null) {
            $out .= Wire::uint(9, self::STATES[$entry->state->value]);
        }
        if ($entry->location !== null) {
            $out .= Wire::uint(10, self::LOCATIONS[$entry->location->value]);
        }
        return $out;
    }

    private static function encodeFit(EsfFit $fit): string
    {
        $out = Wire::uint(1, 1);
        if ($fit->hull !== null) {
            $out .= Wire::uint(2, $fit->hull);
        }
        if ($fit->name !== null && $fit->name !== '') {
            $out .= Wire::record(3, $fit->name);
        }
        if ($fit->mode !== null) {
            $out .= Wire::uint(4, $fit->mode);
        }
        foreach ($fit->entries as $entry) {
            $out .= Wire::record(5, self::encodeEntry($entry));
        }
        return $out;
    }

    /**
     * @throws EsfException
     */
    private static function decodeEntry(string $data): Entry
    {
        $e = new Entry(null);
        $attributes = [];
        $values = [];
        $reader = new Reader($data);
        while (!$reader->done()) {
            match ($reader->key()) {
                [1, Wire::VARINT] => $e->type = self::uint32($reader->varint(), 'Entry.type'),
                [2, Wire::VARINT] => $e->count = self::uint32($reader->varint(), 'Entry.count'),
                [3, Wire::LEN] => $e->fitName = self::string($reader->bytes(), 'Entry.fit_name'),
                [4, Wire::VARINT] => $e->charge = self::uint32($reader->varint(), 'Entry.charge'),
                [5, Wire::VARINT] => $e->chargeCount = self::uint32($reader->varint(), 'Entry.charge_count'),
                [6, Wire::VARINT] => $e->mutaplasmid = self::uint32($reader->varint(), 'Entry.mutaplasmid'),
                [7, Wire::VARINT] => $attributes[] = self::uint32($reader->varint(), 'Entry.override_attributes'),
                [7, Wire::LEN] => array_push($attributes, ...array_map(
                    static fn($v) => self::uint32($v, 'Entry.override_attributes'),
                    Wire::unpackUints($reader->bytes()),
                )),
                [8, Wire::I64] => $values[] = $reader->double(),
                [8, Wire::LEN] => array_push($values, ...Wire::unpackDoubles($reader->bytes())),
                [9, Wire::VARINT] => $e->state = self::state($reader->varint()),
                [10, Wire::VARINT] => $e->location = self::location($reader->varint()),
                default => throw self::notInSchema('Entry'),
            };
        }
        if (count($attributes) !== count($values)) {
            throw new EsfException('binary: override fields have different lengths');
        }
        $e->overrides = array_combine($attributes, $values);
        if (count($e->overrides) !== count($attributes)) {
            throw new EsfException('binary: an attribute is overridden twice');
        }
        return $e;
    }

    /**
     * @throws EsfException
     */
    private static function decodeFit(string $data): EsfFit
    {
        $fit = new EsfFit();
        $version = 0;
        $reader = new Reader($data);
        while (!$reader->done()) {
            match ($reader->key()) {
                [1, Wire::VARINT] => $version = self::uint32($reader->varint(), 'Fit.version'),
                [2, Wire::VARINT] => $fit->hull = self::uint32($reader->varint(), 'Fit.hull'),
                [3, Wire::LEN] => $fit->name = self::string($reader->bytes(), 'Fit.name'),
                [4, Wire::VARINT] => $fit->mode = self::uint32($reader->varint(), 'Fit.tactical_mode'),
                [5, Wire::LEN] => $fit->entries[] = self::decodeEntry($reader->bytes()),
                default => throw self::notInSchema('Fit'),
            };
        }
        if ($version !== 1) {
            throw new EsfException("binary: unsupported Fit.version {$version}");
        }
        return $fit;
    }

    private static function uint32(int $value, string $what): int
    {
        if ($value < 0 || $value > Text::UINT32_MAX) {
            throw new EsfException("binary: {$what} is out of range");
        }
        return $value;
    }

    private static function string(string $payload, string $what): ?string
    {
        if (preg_match('//u', $payload) !== 1) {
            throw new EsfException("binary: {$what} is not valid UTF-8");
        }
        return $payload === '' ? null : $payload;
    }

    /**
     * @param array<string, int> $numbers
     */
    private static function name(array $numbers, int $value, string $what): ?string
    {
        if ($value === 0) {
            return null;
        }
        $name = array_search($value, $numbers, true);
        if ($name === false) {
            $shown = sprintf('%u', $value);
            throw new EsfException("binary: unknown {$what} value {$shown}");
        }
        return $name;
    }

    private static function state(int $value): ?State
    {
        $name = self::name(self::STATES, $value, 'State');
        return $name === null ? null : State::from($name);
    }

    private static function location(int $value): ?Location
    {
        $name = self::name(self::LOCATIONS, $value, 'Location');
        return $name === null ? null : Location::from($name);
    }

    private static function notInSchema(string $what): EsfException
    {
        return new EsfException("binary: {$what} has a field that is not in the schema");
    }
}
