<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/** A broken rule of esf/1, and where: a line of text, or a fit and entry, counting from 1. */
final class EsfException extends \Exception
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $lineNumber = null,
        public readonly ?int $fitNumber = null,
        public readonly ?int $entryNumber = null,
    ) {
        parent::__construct(self::where($lineNumber, $fitNumber, $entryNumber) . $reason);
    }

    private static function where(?int $line, ?int $fit, ?int $entry): string
    {
        if ($fit !== null) {
            return $entry === null ? "fit {$fit}: " : "fit {$fit}, entry {$entry}: ";
        }
        return $line === null ? '' : "line {$line}: ";
    }
}
