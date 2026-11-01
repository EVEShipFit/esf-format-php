<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\EsfException;

/**
 * A position in one line of text.
 *
 * @internal
 */
final class Cursor
{
    public int $i = 0;
    private readonly int $length;

    public function __construct(
        public readonly int $number,
        public readonly string $s,
    ) {
        $this->length = strlen($s);
    }

    public function error(string $message): never
    {
        throw new EsfException($message, $this->number);
    }

    public function peek(): string
    {
        return $this->s[$this->i] ?? '';
    }

    public function token(): string
    {
        $end = strpos($this->s, ' ', $this->i);
        return substr($this->s, $this->i, ($end === false ? $this->length : $end) - $this->i);
    }

    public function skipSpaces(): bool
    {
        $spaces = strspn($this->s, ' ', $this->i);
        $this->i += $spaces;
        return $spaces > 0;
    }

    /** Step over the separating space; false at the end of the line or a comment. */
    public function nextElement(): bool
    {
        $spaced = $this->skipSpaces();
        if ($this->i >= $this->length) {
            return false;
        }
        if (substr($this->s, $this->i, 2) === '//') {
            if (!$spaced) {
                $this->error('a comment needs a space before it');
            }
            $this->i = $this->length;
            return false;
        }
        if (!$spaced) {
            $this->error("expected a space before '{$this->token()}'");
        }
        return true;
    }

    public function positive(string $digits, string $what): int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $this->error("{$what} must be at least 1");
        }
        if (strlen($digits) > 10 || (int) $digits > Text::UINT32_MAX) {
            $this->error("{$what} is too large");
        }
        return (int) $digits;
    }

    public function finish(): void
    {
        if ($this->nextElement()) {
            $this->error("unexpected '{$this->token()}'");
        }
    }

    public function count(): ?int
    {
        $token = $this->token();
        if (!Text::isCount($token)) {
            return null;
        }
        $this->i += strlen($token);
        if (!$this->nextElement()) {
            $this->error('a count must be followed by a type name');
        }
        return $this->positive(substr($token, 0, -1), 'a count');
    }

    public function quoted(): string
    {
        if (preg_match('/\G"((?:[^"]|"")*+)"/', $this->s, $match, 0, $this->i) !== 1) {
            $this->error('unterminated quoted string');
        }
        if ($match[1] === '') {
            $this->error('a quoted string cannot be empty');
        }
        $this->i += strlen($match[0]);
        return str_replace('""', '"', $match[1]);
    }

    public function word(): string
    {
        $token = $this->token();
        $this->i += strlen($token);
        return $token;
    }

    public function typeName(): string
    {
        $c = $this->peek();
        if ($c === '"') {
            return $this->quoted();
        }
        if (self::endsName($c)) {
            $this->error('expected a type name');
        }
        if ($c === '-' || $c === '%') {
            $this->error("a name starting with '{$c}' must be quoted");
        }
        $words = [$this->word()];
        while (true) {
            $start = $this->i;
            if (!$this->skipSpaces() || self::endsName($this->peek())) {
                $this->i = $start;
                return implode(' ', $words);
            }
            $words[] = $this->word();
        }
    }

    /**
     * @return array{string, ?int}
     */
    public function location(): array
    {
        if (preg_match('/\G@([a-z]+)([0-9]*)/', $this->s, $match, 0, $this->i) !== 1) {
            $this->error("unknown location '{$this->token()}'");
        }
        $this->i += strlen($match[0]);
        [, $name, $index] = $match;
        if ($index !== '') {
            if (!in_array($name, Text::RACKS, true)) {
                $this->error("unknown rack '{$name}'");
            }
            return [$name, $this->positive($index, 'a slot number')];
        }
        if (!in_array($name, Text::STORED, true) && !in_array($name, Text::RACKS, true)) {
            $this->error("unknown location '{$name}'");
        }
        return [$name, null];
    }

    public function state(): string
    {
        if (preg_match('/\G!(off|on|heat)/', $this->s, $match, 0, $this->i) !== 1) {
            $this->error("unknown state '{$this->token()}'");
        }
        $this->i += strlen($match[0]);
        return $match[1];
    }

    /**
     * @return list<array{string, string}>
     */
    public function overrides(): array
    {
        $this->i++;
        $this->skipSpaces();
        $pairs = [];
        while (true) {
            $pattern = '/\G([A-Za-z][A-Za-z0-9]*) +(-?[0-9]+(?:\.[0-9]+)?)/';
            if (preg_match($pattern, $this->s, $match, 0, $this->i) !== 1) {
                $this->error("malformed override; expected 'attribute value'");
            }
            $pairs[] = [$match[1], $match[2]];
            $this->i += strlen($match[0]);
            $this->skipSpaces();
            if ($this->peek() === ',') {
                $this->i++;
                $this->skipSpaces();
                continue;
            }
            if ($this->peek() === '}') {
                $this->i++;
                return $pairs;
            }
            $this->error("malformed overrides; expected ',' or '}'");
        }
    }

    private static function endsName(string $c): bool
    {
        return $c === '' || $c === ' ' || str_contains(Text::SIGILS, $c);
    }
}
