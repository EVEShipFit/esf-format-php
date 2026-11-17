<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * The fit breaks a rule of esf/1.
 *
 * The document is well formed and the catalog knows every type, but the fit is not valid. For
 * example, a module is in the wrong rack.
 */
final class EsfRuleException extends EsfException {}
