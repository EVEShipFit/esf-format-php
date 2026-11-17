<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

/**
 * An esf/1 error, and where: a line of text, or a fit and entry, counting from 1.
 *
 * Every error is one of three kinds. Catch {@see EsfFormatException}, {@see EsfCatalogException}
 * or {@see EsfRuleException} to handle one kind, or this class to handle them all.
 */
abstract class EsfException extends \Exception
{
    final public function __construct(
        public readonly string $reason,
        public readonly ?int $lineNumber = null,
        public readonly ?int $fitNumber = null,
        public readonly ?int $entryNumber = null,
    ) {
        parent::__construct(self::where($lineNumber, $fitNumber, $entryNumber) . $reason);
    }

    /** @internal */
    public function at(int $fit, ?int $entry): static
    {
        return new static($this->reason, fitNumber: $fit, entryNumber: $entry);
    }

    private static function where(?int $line, ?int $fit, ?int $entry): string
    {
        if ($fit !== null) {
            return $entry === null ? "fit {$fit}: " : "fit {$fit}, entry {$entry}: ";
        }
        return $line === null ? '' : "line {$line}: ";
    }
}
