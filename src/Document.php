<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * A whole document: its fits, and the other data it holds.
 *
 * {@see Esf::loadDocument()} returns it.
 */
final readonly class Document
{
    /**
     * @param list<EsfFit> $fits The fits, in canonical form, like {@see Esf::load()} returns them.
     * @param list<TextExtra|BinaryExtra> $extra The blocks and binary fields that are not fits,
     *     like a `%fleet/1` block, in the order the document has them.
     */
    public function __construct(
        public array $fits,
        public array $extra = [],
    ) {}
}
