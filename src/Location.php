<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * Where an entry goes, when not where its type goes by default (esf/1 spec §5).
 *
 * An item goes where its {@see Kind} says, so most entries leave this null. Set it to store an
 * item somewhere else, like a spare Damage Control II in Cargo.
 * So a drone with no location is out in space, and a fighter is in a launch tube. Set
 * Bay to keep either one in its bay.
 *
 * The racks, High to Svc, are for empty slots, and for an item put in a given
 * slot ({@see Entry::$slot}). Otherwise, an item never names its rack.
 * The holds, Ammo to Subsystem, only make sense on a hull that has that hold, but the
 * library does not check that.
 */
enum Location: string
{
    /** The high slot rack. */
    case High = 'high';

    /** The mid slot rack. */
    case Mid = 'mid';

    /** The low slot rack. */
    case Low = 'low';

    /** The rig rack. */
    case Rig = 'rig';

    /** The subsystem rack. Not the same as Subsystem, which is a hold. */
    case Sub = 'sub';

    /** The structure service rack. */
    case Svc = 'svc';

    /** The cargo hold. */
    case Cargo = 'cargo';

    /** The drone bay or fighter bay, for drones and fighters that are not out in space or in a tube. */
    case Bay = 'bay';

    /** The Ammo Hold. */
    case Ammo = 'ammo';

    /** The Booster Hold. */
    case Booster = 'booster';

    /** The Command Center Hold. */
    case Command = 'command';

    /** The Corpse Hold. */
    case Corpse = 'corpse';

    /** The Mobile Depot Hold. */
    case Depot = 'depot';

    /** The Expedition Hold. */
    case Expedition = 'expedition';

    /** The Fleet Hangar. */
    case Fleet = 'fleet';

    /** The Frigate Escape Bay. */
    case Frigate = 'frigate';

    /** The Fuel Bay. */
    case Fuel = 'fuel';

    /** The Gas Hold. */
    case Gas = 'gas';

    /** The Ice Hold. */
    case Ice = 'ice';

    /** The Infrastructure Hold. */
    case Infrastructure = 'infrastructure';

    /** The Ship Maintenance Bay. */
    case Maintenance = 'maintenance';

    /** The Mineral Hold. */
    case Mineral = 'mineral';

    /** The Mining Hold. */
    case Mining = 'mining';

    /** The Moon Material Output Bay. */
    case Moon = 'moon';

    /** The Planetary Commodities Hold. */
    case Planetary = 'planetary';

    /** The Quafe Hold. */
    case Quafe = 'quafe';

    /** The Subsystem Hold, for spare subsystems. Not the same as Sub, which is the rack. */
    case Subsystem = 'subsystem';
}
