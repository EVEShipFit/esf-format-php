<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** Where an entry is, when not where its type goes by default (§5). */
enum Location: string
{
    case High = 'high';
    case Mid = 'mid';
    case Low = 'low';
    case Rig = 'rig';
    case Sub = 'sub';
    case Svc = 'svc';
    case Cargo = 'cargo';
    case Bay = 'bay';
    case Ammo = 'ammo';
    case Booster = 'booster';
    case Command = 'command';
    case Corpse = 'corpse';
    case Depot = 'depot';
    case Expedition = 'expedition';
    case Fleet = 'fleet';
    case Frigate = 'frigate';
    case Fuel = 'fuel';
    case Gas = 'gas';
    case Ice = 'ice';
    case Infrastructure = 'infrastructure';
    case Maintenance = 'maintenance';
    case Mineral = 'mineral';
    case Mining = 'mining';
    case Moon = 'moon';
    case Planetary = 'planetary';
    case Quafe = 'quafe';
    case Subsystem = 'subsystem';
}
