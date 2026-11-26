<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * A field in a binary document that is not a fit.
 *
 * The binary form is a list of numbered fields. Field 1 holds the fits.
 * {@see Esf::loadDocument()} gives back any other field written the way {@see Esf::binaryField()}
 * writes it, in one of these. It skips other kinds of field.
 */
final readonly class BinaryExtra
{
    /**
     * @param int $field The field number, like 2.
     * @param string $bytes The content of the field, as a binary string.
     */
    public function __construct(
        public int $field,
        public string $bytes,
    ) {}
}
