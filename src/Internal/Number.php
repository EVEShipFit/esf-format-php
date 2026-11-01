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
        $precision = ini_set('serialize_precision', '-1');
        try {
            $text = var_export($value, true);
        } finally {
            ini_set('serialize_precision', (string) $precision);
        }
        [$mantissa, $exponent] = explode('E', $text) + [1 => '0'];
        [$whole, $fraction] = explode('.', $mantissa) + [1 => ''];
        $all = ltrim($whole . $fraction, '0');
        $digits = rtrim($all, '0');
        return [$digits, (int) $exponent - strlen($fraction) + strlen($all) - strlen($digits)];
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
