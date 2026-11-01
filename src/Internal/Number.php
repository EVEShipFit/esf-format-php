<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

/**
 * Floats as decimals.
 *
 * @internal
 */
final class Number
{
    /** The shortest decimal, without exponent, that reads back as the same float. */
    public static function format(float $value): string
    {
        if ($value == 0.0) {
            return '0';
        }
        [$digits, $exponent] = self::shortest(abs($value));
        $sign = $value < 0 ? '-' : '';
        if ($exponent >= 0) {
            return $sign . $digits . str_repeat('0', $exponent);
        }
        $point = strlen($digits) + $exponent;
        if ($point > 0) {
            return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
        }
        return $sign . '0.' . str_repeat('0', -$point) . $digits;
    }

    /**
     * The fewest digits that read back as a positive, finite float: value = digits × 10^exponent.
     *
     * @return array{string, int}
     */
    public static function shortest(float $value): array
    {
        $precision = 0;
        do {
            $text = sprintf("%.{$precision}e", $value);
        } while ((float) $text !== $value && ++$precision < 17);
        [$mantissa, $exponent] = explode('e', $text);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');
        return [$digits, (int) $exponent - strlen($digits) + 1];
    }

    /** floor(a / b) of the shortest decimals of two positive floats, at most PHP_INT_MAX. */
    public static function quotient(float $a, float $b): int
    {
        [$digitsA, $exponentA] = self::shortest($a);
        [$digitsB, $exponentB] = self::shortest($b);
        $divisor = (int) $digitsB;
        $quotient = intdiv((int) $digitsA, $divisor);
        $rest = (int) $digitsA % $divisor;
        for ($shift = $exponentA - $exponentB; $shift > 0; $shift--) {
            if ($quotient >= intdiv(PHP_INT_MAX, 10)) {
                return PHP_INT_MAX;
            }
            $rest *= 10;
            $quotient = $quotient * 10 + intdiv($rest, $divisor);
            $rest %= $divisor;
        }
        for (; $shift < 0 && $quotient > 0; $shift++) {
            $quotient = intdiv($quotient, 10);
        }
        return $quotient;
    }
}
