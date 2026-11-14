<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * What a type is, which decides where it goes in a fit (esf/1 spec §5).
 *
 * Check the group rules first (Mode, Container, Booster), then the category rules.
 * {@see Sde::kind()} works this out from the SDE for you.
 */
enum Kind: string
{
    /** A ship or a structure: SDE category `Ship` (6) or `Structure` (65). */
    case Hull = 'hull';

    /**
     * A container that can carry another fit, like a Small Secure Container.
     *
     * SDE group `Cargo Container` (12), `Secure Cargo Container` (340),
     * `Audit Log Secure Container` (448) or `Freight Container` (649).
     */
    case Container = 'container';

    /** A tactical mode, like the Hecate's Sharpshooter Mode: SDE group `Ship Modifiers` (1306). */
    case Mode = 'mode';

    /** A subsystem, like on a Tengu: SDE category `Subsystem` (32). */
    case Sub = 'sub';

    /** A high slot module, like a gun: SDE category `Module` (7) or `Structure Module` (66), with effect `hiPower` (12). */
    case High = 'high';

    /** A mid slot module, like a shield extender: category `Module` or `Structure Module`, with effect `medPower` (13). */
    case Mid = 'mid';

    /** A low slot module, like a damage control: category `Module` or `Structure Module`, with effect `loPower` (11). */
    case Low = 'low';

    /** A rig: category `Module` or `Structure Module`, with effect `rigSlot` (2663). */
    case Rig = 'rig';

    /** A structure service module: category `Module` or `Structure Module`, with effect `serviceSlot` (6306). */
    case Svc = 'svc';

    /** A drone: SDE category `Drone` (18). */
    case Drone = 'drone';

    /** A fighter: SDE category `Fighter` (87). */
    case Fighter = 'fighter';

    /** Ammunition, a script or anything else loaded into a module: SDE category `Charge` (8). */
    case Charge = 'charge';

    /** An implant: SDE category `Implant` (20), except the boosters in it. */
    case Implant = 'implant';

    /** A booster, also called a drug: SDE group `Booster` (303), inside category `Implant`. */
    case Booster = 'booster';

    /** Anything else, like ore or a module without a slot. It goes in the cargo. */
    case Other = 'other';
}
