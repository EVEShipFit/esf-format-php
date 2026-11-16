<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * Helpers to turn an ESI `location_flag` into an entry's location and slot.
 *
 * ESI gives a `location_flag` for each item in a ship, in the assets and fittings
 * endpoints. Use both helpers on the same flag:
 *
 * ```php
 * new Entry($typeId, location: Esi::location($flag) ?? Location::Cargo, slot: Esi::slot($flag));
 * ```
 *
 * Only the flags of things inside a ship are known. For any other flag, like `Hangar`,
 * both return null. The example above puts those items in the cargo hold.
 */
final class Esi
{
    private const RACKS = [
        'HiSlot' => [Location::High, 8],
        'MedSlot' => [Location::Mid, 8],
        'LoSlot' => [Location::Low, 8],
        'RigSlot' => [Location::Rig, 8],
        'SubSystemSlot' => [Location::Sub, 8],
        'ServiceSlot' => [Location::Svc, 8],
        'FighterTube' => [Location::Tube, 5],
    ];

    private const HOLDS = [
        'Cargo' => Location::Cargo,
        'DroneBay' => Location::Bay,
        'FighterBay' => Location::Bay,
        'SpecializedAmmoHold' => Location::Ammo,
        'BoosterBay' => Location::Booster,
        'SpecializedCommandCenterHold' => Location::Command,
        'CorpseBay' => Location::Corpse,
        'MobileDepotHold' => Location::Depot,
        'ExpeditionHold' => Location::Expedition,
        'FleetHangar' => Location::Fleet,
        'FrigateEscapeBay' => Location::Frigate,
        'SpecializedFuelBay' => Location::Fuel,
        'SpecializedGasHold' => Location::Gas,
        'SpecializedIceHold' => Location::Ice,
        'InfrastructureHangar' => Location::Infrastructure,
        'ShipHangar' => Location::Maintenance,
        'SpecializedMineralHold' => Location::Mineral,
        'SpecializedOreHold' => Location::Mining,
        'MoonMaterialBay' => Location::Moon,
        'SpecializedPlanetaryCommoditiesHold' => Location::Planetary,
        'QuafeBay' => Location::Quafe,
        'SubSystemBay' => Location::Subsystem,
    ];

    /**
     * Gives the location for an ESI `location_flag`.
     *
     * A slot flag gives its rack: `HiSlot2` gives Location::High, and `FighterTube0`
     * gives Location::Tube. `DroneBay` and `FighterBay` give Location::Bay. In a fitting
     * from ESI, `DroneBay` also holds the drones that are out in space. Leave the entry's
     * location null for those.
     *
     * Returns null for a flag that is not inside a ship, like `Hangar`.
     */
    public static function location(string $flag): ?Location
    {
        return self::rackSlot($flag)[0] ?? self::HOLDS[$flag] ?? null;
    }

    /**
     * Gives the entry's slot for an ESI `location_flag`, counting from 1.
     *
     * ESI counts slots from 0, so `HiSlot2` is slot 3, and `FighterTube0` is tube 1.
     * Returns null for a flag that is not a slot, like `Cargo` or `Hangar`.
     */
    public static function slot(string $flag): ?int
    {
        return self::rackSlot($flag)[1] ?? null;
    }

    /**
     * @return array{Location, int}|null
     */
    private static function rackSlot(string $flag): ?array
    {
        if (preg_match('/^([A-Za-z]+)(\d)$/D', $flag, $m) !== 1 || !isset(self::RACKS[$m[1]])) {
            return null;
        }
        [$location, $size] = self::RACKS[$m[1]];
        $index = (int) $m[2];

        return $index < $size ? [$location, $index + 1] : null;
    }
}
