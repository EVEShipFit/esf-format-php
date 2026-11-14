<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\Kind;
use EveShipFit\EsfFormat\Sde;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SdeTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, list<int>, Kind}>
     */
    public static function kinds(): iterable
    {
        yield 'Rifter' => [6, 25, [5779], Kind::Hull];
        yield 'Astrahus' => [65, 1657, [6565], Kind::Hull];
        yield 'Small Secure Container' => [2, 340, [649], Kind::Container];
        yield 'Hecate Sharpshooter Mode' => [7, 1306, [6010], Kind::Mode];
        yield 'Tengu Defensive - Covert Reconfiguration' => [32, 954, [896], Kind::Sub];
        yield '200mm AutoCannon II' => [7, 55, [16, 12, 34], Kind::High];
        yield 'Medium Shield Extender II' => [7, 38, [13, 16], Kind::Mid];
        yield 'Damage Control II' => [7, 60, [11, 16], Kind::Low];
        yield 'Standup Ballistic Control System I' => [66, 1429, [11, 16], Kind::Low];
        yield 'Small Core Defense Field Extender I' => [7, 774, [446, 2663], Kind::Rig];
        yield 'Standup Cloning Center I' => [66, 1321, [16, 6306], Kind::Svc];
        yield 'module without a slot' => [7, 1, [16], Kind::Other];
        yield 'Hobgoblin II' => [18, 100, [10], Kind::Drone];
        yield 'Templar II' => [87, 1652, [6431], Kind::Fighter];
        yield 'Republic Fleet EMP S' => [8, 83, [596], Kind::Charge];
        yield 'Genolution Core Augmentation CA-1' => [20, 1730, [302], Kind::Implant];
        yield 'Strong Blue Pill Booster' => [20, 303, [2737], Kind::Booster];
        yield 'Veldspar' => [25, 462, [], Kind::Other];
    }

    /**
     * @param list<int> $effectIds
     */
    #[DataProvider('kinds')]
    public function testKind(int $categoryId, int $groupId, array $effectIds, Kind $expected): void
    {
        self::assertSame($expected, Sde::kind($categoryId, $groupId, $effectIds));
    }

    public function testActiveNeedsAnActiveOrTargetEffect(): void
    {
        self::assertFalse(Sde::active([]));
        self::assertFalse(Sde::active([16 => 1, 2302 => 0]));
        self::assertTrue(Sde::active([16 => 1, 4 => 1]));
        self::assertTrue(Sde::active([16 => 1, 3174 => 2]));
    }

    public function testSquadronSize(): void
    {
        self::assertSame(6, Sde::squadronSize([9 => 100.0, 2215 => 6.0]));
        self::assertNull(Sde::squadronSize([9 => 240.0]));
    }

    public function testModeHullMatchesTheLongestShipName(): void
    {
        $ships = ['hecate' => 35683, 'hecate navy' => 1];
        self::assertSame(35683, Sde::modeHull('Hecate Sharpshooter Mode', $ships));
        self::assertSame(1, Sde::modeHull('Hecate Navy Defense Mode', $ships));
        self::assertNull(Sde::modeHull('Hecate', $ships));
        self::assertNull(Sde::modeHull('Rifter Defense Mode', $ships));
    }

    public function testNameIndexFoldsNamesAndPicksPublishedThenLowestId(): void
    {
        $index = Sde::nameIndex(
            [
                ['id' => 50097, 'name' => 'Avatar', 'published' => false],
                ['id' => 11567, 'name' => 'Avatar', 'published' => true],
                ['id' => 30, 'name' => 'Twin', 'published' => false],
                ['id' => 20, 'name' => 'Twin', 'published' => false],
                ['id' => 587, 'name' => 'Rifter', 'published' => true],
            ],
            static fn($e) => $e['id'],
            static fn($e) => $e['name'],
            static fn($e) => $e['published'],
        );
        self::assertEquals(['avatar' => 11567, 'twin' => 20, 'rifter' => 587], $index);
    }
}
