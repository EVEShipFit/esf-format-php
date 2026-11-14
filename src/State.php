<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * Whether an item is offline, online or running, when that is not its default (esf/1 spec §6.4).
 *
 * By default, a fitted or deployed item runs when its type is active ({@see TypeRecord::$active}),
 * and is online otherwise. An implant or booster is online by default, and only takes Off.
 * A stored item, in cargo, a bay or a hold, has no state at all.
 */
enum State: string
{
    /** Offline: fitted, but not using powergrid or CPU. */
    case Off = 'off';

    /** Online, but not running, like a gun that is not shooting. */
    case On = 'on';

    /** Overloaded, which also means running. */
    case Heat = 'heat';
}
