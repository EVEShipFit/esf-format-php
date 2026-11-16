<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\Esi;
use EveShipFit\EsfFormat\Location;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EsiTest extends TestCase
{
    /**
     * @return iterable<string, array{Location, int}>
     */
    public static function slots(): iterable
    {
        yield 'HiSlot0' => [Location::High, 1];
        yield 'MedSlot7' => [Location::Mid, 8];
        yield 'LoSlot2' => [Location::Low, 3];
        yield 'RigSlot1' => [Location::Rig, 2];
        yield 'SubSystemSlot3' => [Location::Sub, 4];
        yield 'ServiceSlot5' => [Location::Svc, 6];
        yield 'FighterTube4' => [Location::Tube, 5];
    }

    #[DataProvider('slots')]
    public function testSlot(Location $location, int $slot): void
    {
        $flag = $this->dataName();
        self::assertIsString($flag);
        self::assertSame($location, Esi::location($flag));
        self::assertSame($slot, Esi::slot($flag));
    }

    public function testEveryHold(): void
    {
        $flags = [
            'Cargo', 'DroneBay', 'SpecializedAmmoHold', 'BoosterBay', 'SpecializedCommandCenterHold',
            'CorpseBay', 'MobileDepotHold', 'ExpeditionHold', 'FleetHangar', 'FrigateEscapeBay',
            'SpecializedFuelBay', 'SpecializedGasHold', 'SpecializedIceHold', 'InfrastructureHangar',
            'ShipHangar', 'SpecializedMineralHold', 'SpecializedOreHold', 'MoonMaterialBay',
            'SpecializedPlanetaryCommoditiesHold', 'QuafeBay', 'SubSystemBay',
        ];
        $racks = [Location::High, Location::Mid, Location::Low, Location::Rig, Location::Sub, Location::Svc, Location::Tube];
        $holds = array_values(array_filter(Location::cases(), static fn(Location $l): bool => !\in_array($l, $racks, true)));
        self::assertSame($holds, array_map(Esi::location(...), $flags));
        foreach ($flags as $flag) {
            self::assertNull(Esi::slot($flag));
        }
        self::assertSame(Location::Bay, Esi::location('FighterBay'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownFlags(): iterable
    {
        foreach (['Hangar', 'AutoFit', 'HiSlot8', 'FighterTube5', 'hislot0', 'HiSlot01', "HiSlot0\n", 'toString', ''] as $flag) {
            yield json_encode($flag, JSON_THROW_ON_ERROR) => [$flag];
        }
    }

    #[DataProvider('unknownFlags')]
    public function testUnknown(string $flag): void
    {
        self::assertNull(Esi::location($flag));
        self::assertNull(Esi::slot($flag));
    }
}
