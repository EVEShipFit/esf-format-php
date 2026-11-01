<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** What a type is (§5). */
enum Kind: string
{
    case Hull = 'hull';
    case Container = 'container';
    case Mode = 'mode';
    case Sub = 'sub';
    case High = 'high';
    case Mid = 'mid';
    case Low = 'low';
    case Rig = 'rig';
    case Svc = 'svc';
    case Drone = 'drone';
    case Fighter = 'fighter';
    case Charge = 'charge';
    case Implant = 'implant';
    case Booster = 'booster';
    case Other = 'other';
}
