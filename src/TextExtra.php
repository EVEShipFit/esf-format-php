<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * A text block that is not a fit, like `%fleet/1`.
 *
 * A text document is a list of blocks. Each block starts with a header line: `%`, a name,
 * `/` and a version. The library reads `%esf/1` blocks as fits. Any other block is kept
 * as it is, in one of these.
 */
final readonly class TextExtra
{
    /**
     * @param string $name The name in the header, like "fleet" for `%fleet/1`.
     * @param int $version The version in the header, like 1 for `%fleet/1`.
     * @param string $text Everything after the header line, up to the next header or the end of the document.
     *     It is exactly as written: line endings and blank lines are kept. It can be empty.
     */
    public function __construct(
        public string $name,
        public int $version,
        public string $text,
    ) {}
}
