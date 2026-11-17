<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * Where the library looks up game data. You write this, on top of your copy of the SDE.
 *
 * The library holds no game data itself. Whenever it meets a name or an ID in a fit,
 * it asks the catalog what that is. {@see Sde} has helpers for the parts that take more
 * than a lookup.
 *
 * Implement {@see ModeCatalog} too for tactical modes, and {@see MutaplasmidCatalog}
 * for mutaplasmids. Without them, no hull has tactical modes and no type has mutaplasmids.
 *
 * The `§` numbers point to sections of the esf/1 spec:
 * https://github.com/EVEShipFit/esf-format/blob/main/SPEC.md
 */
interface Catalog
{
    /**
     * Finds a type by its name.
     *
     * The name comes from a fit, so a user may have typed it in any case.
     * The library already case-folded it with {@see Esf::fold()}, so "Rifter", "RIFTER"
     * and "rifter" all reach you as "rifter". Case folding is like lowercase, but
     * gives the same result in every language, so use {@see Esf::fold()}, not `strtolower()`.
     * Store your type names the same way, by calling {@see Esf::fold()} on each one,
     * and look this name up in that store.
     *
     * Include unpublished types too. Some names belong to more than one type.
     * Then return the published one; if that is still more than one, the lowest ID.
     * {@see Sde::nameIndex()} builds this store for you.
     *
     * Return null if no type has this name.
     */
    public function type(string $foldedName): ?TypeRecord;

    /** Finds a type by its ID. Return null if there is no type with this ID. */
    public function typeById(int $typeId): ?TypeRecord;

    /**
     * Finds a dogma attribute by its name, like "speedfactor".
     *
     * Used to read attribute overrides, like the rolls on an abyssal module.
     *
     * This works the same way as {@see Catalog::type()}: the name is already case-folded with
     * {@see Esf::fold()}, unpublished attributes count too, and where names are shared, the
     * published one wins, then the lowest ID. {@see Sde::nameIndex()} builds this store for you.
     *
     * Return null if no attribute has this name.
     */
    public function attribute(string $foldedName): ?AttributeRecord;

    /**
     * Finds a dogma attribute by its ID.
     *
     * Used to write attribute overrides, like the rolls on an abyssal module.
     *
     * Return null if there is no attribute with this ID.
     */
    public function attributeById(int $attributeId): ?AttributeRecord;
}
