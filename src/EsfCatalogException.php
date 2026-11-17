<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * The catalog does not know a name or ID, or a name matches more than one type.
 *
 * This often means the catalog is older than the fit.
 */
final class EsfCatalogException extends EsfException {}
