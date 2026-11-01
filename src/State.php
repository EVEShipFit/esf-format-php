<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** The state of an item, when not its default (§6.4). */
enum State: string
{
    case Off = 'off';
    case On = 'on';
    case Heat = 'heat';
}
