<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * The data is not a well-formed esf/1 document.
 *
 * The text does not follow the grammar, the binary form or base64url is broken, or a fit name
 * cannot be written.
 */
final class EsfFormatException extends EsfException {}
