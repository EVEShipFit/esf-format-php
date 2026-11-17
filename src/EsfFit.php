<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * One fit: a ship and everything on it, or a plain list of items.
 *
 * A list of fits is one document. A fit that another fit carries, through {@see Entry::$fitName},
 * exists only there. Any other fit stands on its own.
 */
final class EsfFit
{
    /**
     * @param int|null $hull The type ID of the ship, structure or container, like 587 for the Rifter.
     *     Only a type whose {@see Kind::canBeHull()} is true can be a hull.
     *     Null for a fit without a ship, like the items in a contract: then every entry is
     *     stored in cargo, and the fit has no slots and no mode.
     * @param string|null $name The name of the fit, like "Shield Buffer". Null for no name.
     * @param int|null $mode The type ID of the tactical mode the ship is in, like the Hecate's Sharpshooter Mode.
     *     Only for a hull that has tactical modes; see {@see ModeCatalog::modes()}. Null for none.
     * @param list<Entry> $entries The lines of the fit: items, empty slots and fits it carries.
     */
    public function __construct(
        public ?int $hull = null,
        public ?string $name = null,
        public ?int $mode = null,
        public array $entries = [],
    ) {}
}
