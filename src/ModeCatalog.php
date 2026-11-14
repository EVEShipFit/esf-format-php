<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * A {@see Catalog} that also knows the tactical modes of each hull.
 *
 * Implement this instead of {@see Catalog} when you want tactical modes. Without it,
 * no hull has tactical modes.
 */
interface ModeCatalog extends Catalog
{
    /**
     * The tactical modes a hull can be in, like the Hecate's Defense, Propulsion and Sharpshooter Mode.
     *
     * Return an empty list for a hull without tactical modes. In the SDE, modes are the types
     * of kind {@see Kind::Mode}; {@see Sde::modeHull()} tells you which hull each one belongs to.
     *
     * @return list<TypeRecord>
     */
    public function modes(int $hullId): array;
}
