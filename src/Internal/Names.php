<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/**
 * Name matching rules (SPEC §6.10, §6.1, §6.6).
 *
 * @internal
 */
final class Names
{
    /** Unicode simple case folding. */
    public static function fold(string $s): string
    {
        if (preg_match('/[\x80-\xFF]/', $s) !== 1) {
            return strtolower($s);
        }
        return mb_convert_case($s, MB_CASE_FOLD_SIMPLE, 'UTF-8');
    }

    /** Whether `$prefix` is `$name` with zero or more whole words dropped from the end. */
    public static function isWordPrefix(string $prefix, string $name): bool
    {
        $words = explode(' ', self::fold($prefix));
        $full = explode(' ', self::fold($name));
        return $words === array_slice($full, 0, count($words));
    }

    /**
     * The fewest leading words of `$name` that are a word prefix of no name in `$others`.
     *
     * @param list<string> $others
     */
    public static function shortestUnique(string $name, array $others): string
    {
        $words = explode(' ', $name);
        for ($n = 1; $n <= count($words); $n++) {
            $candidate = implode(' ', array_slice($words, 0, $n));
            foreach ($others as $other) {
                if (self::isWordPrefix($candidate, $other)) {
                    continue 2;
                }
            }
            return $candidate;
        }
        return $name;
    }
}
