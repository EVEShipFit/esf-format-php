<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\EsfError;

/**
 * The Protocol Buffers wire format.
 *
 * @internal
 */
final class Wire
{
    public const VARINT = 0;
    public const I64 = 1;
    public const LEN = 2;
    public const SGROUP = 3;
    public const EGROUP = 4;
    public const I32 = 5;

    public static function varint(int $value): string
    {
        $out = '';
        while ($value > 0x7F) {
            $out .= chr($value & 0x7F | 0x80);
            $value >>= 7;
        }
        return $out . chr($value);
    }

    public static function uint(int $field, int $value): string
    {
        return self::varint($field << 3 | self::VARINT) . self::varint($value);
    }

    public static function record(int $field, string $payload): string
    {
        return self::varint($field << 3 | self::LEN) . self::varint(strlen($payload)) . $payload;
    }

    /**
     * @param list<int> $values
     */
    public static function packedUints(int $field, array $values): string
    {
        return self::record($field, implode('', array_map(self::varint(...), $values)));
    }

    /**
     * @param list<float> $values
     */
    public static function packedDoubles(int $field, array $values): string
    {
        return self::record($field, pack('e*', ...$values));
    }

    /**
     * @return list<int>
     * @throws EsfError
     */
    public static function unpackUints(string $payload): array
    {
        $reader = new Reader($payload);
        $out = [];
        while (!$reader->done()) {
            $out[] = $reader->varint();
        }
        return $out;
    }

    /**
     * @return list<float>
     * @throws EsfError
     */
    public static function unpackDoubles(string $payload): array
    {
        if (strlen($payload) % 8 !== 0) {
            throw new EsfError('binary: not valid Protocol Buffers (packed doubles cut short)');
        }
        /** @var array<int, float> $values */
        $values = unpack('e*', $payload);
        return array_values($values);
    }
}
