<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\EsfException;

/**
 * A reader of Protocol Buffers records.
 *
 * @internal
 */
final class Reader
{
    private int $pos = 0;
    private readonly int $length;

    public function __construct(private readonly string $data)
    {
        $this->length = strlen($data);
    }

    public function done(): bool
    {
        return $this->pos >= $this->length;
    }

    /**
     * @throws EsfException
     */
    public function take(int $n): string
    {
        if ($n < 0 || $n > $this->length - $this->pos) {
            throw new EsfException('binary: not valid Protocol Buffers (cut short)');
        }
        $out = substr($this->data, $this->pos, $n);
        $this->pos += $n;
        return $out;
    }

    /**
     * A varint, as the signed 64-bit int with the same bits.
     *
     * @throws EsfException
     */
    public function varint(): int
    {
        $value = 0;
        for ($shift = 0; $shift < 64; $shift += 7) {
            if ($this->pos >= $this->length) {
                throw new EsfException('binary: not valid Protocol Buffers (cut short)');
            }
            $byte = ord($this->data[$this->pos++]);
            $value |= ($byte & 0x7F) << $shift;
            if ($byte < 0x80) {
                return $value;
            }
        }
        throw new EsfException('binary: not valid Protocol Buffers (varint is too long)');
    }

    /**
     * @return array{int, int} field and wire type
     * @throws EsfException
     */
    public function key(): array
    {
        $key = $this->varint();
        $field = $key >> 3;
        $wire = $key & 7;
        if ($field === 0 || $key < 0 || $key > 0xFFFFFFFF || $wire > Wire::I32) {
            $shown = sprintf('%u', $key);
            throw new EsfException("binary: not valid Protocol Buffers (bad key {$shown})");
        }
        return [$field, $wire];
    }

    /**
     * @throws EsfException
     */
    public function bytes(): string
    {
        return $this->take($this->varint());
    }

    /**
     * @throws EsfException
     */
    public function double(): float
    {
        return Wire::unpackDoubles($this->take(8))[0];
    }

    /**
     * Step over the value of a record that is not read.
     *
     * @throws EsfException
     */
    public function skip(int $field, int $wire): void
    {
        if ($wire === Wire::VARINT) {
            $this->varint();
        } elseif ($wire === Wire::I64) {
            $this->take(8);
        } elseif ($wire === Wire::I32) {
            $this->take(4);
        } elseif ($wire === Wire::LEN) {
            $this->bytes();
        } elseif ($wire === Wire::SGROUP) {
            $this->skipGroup($field);
        } else {
            throw new EsfException('binary: not valid Protocol Buffers (unmatched end group)');
        }
    }

    /**
     * @throws EsfException
     */
    private function skipGroup(int $field): void
    {
        $open = [$field];
        while ($open !== []) {
            [$inner, $wire] = $this->key();
            if ($wire === Wire::SGROUP) {
                $open[] = $inner;
            } elseif ($wire === Wire::EGROUP) {
                if (array_pop($open) !== $inner) {
                    throw new EsfException('binary: not valid Protocol Buffers (unmatched end group)');
                }
            } else {
                $this->skip($inner, $wire);
            }
        }
    }
}
