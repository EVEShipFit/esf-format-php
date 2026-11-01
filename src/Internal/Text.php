<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Internal;

use EveShipFit\EsfFormat\EsfError;

/**
 * Reading the text form into lines, without a Catalog (SPEC §4.1, grammar.ebnf).
 *
 * @internal
 */
final class Text
{
    public const SIGILS = ':+{!@/"';
    public const RACKS = ['sub', 'high', 'mid', 'low', 'rig', 'svc'];
    public const HOLDS = [
        'ammo',
        'booster',
        'command',
        'corpse',
        'depot',
        'expedition',
        'fleet',
        'frigate',
        'fuel',
        'gas',
        'ice',
        'infrastructure',
        'maintenance',
        'mineral',
        'mining',
        'moon',
        'planetary',
        'quafe',
        'subsystem',
    ];
    public const STORED = ['cargo', 'bay', ...self::HOLDS];
    public const UINT32_MAX = 0xFFFFFFFF;

    public static function isCount(string $token): bool
    {
        return preg_match('/\A[0-9]+x\z/', $token) === 1;
    }

    /**
     * Split a text document into fits; other blocks are skipped.
     *
     * @return list<FitBlock>
     * @throws EsfError
     */
    public static function parse(string $data): array
    {
        $lines = explode("\n", $data);
        if (end($lines) === '') {
            array_pop($lines);
        }
        if (preg_match('//u', $data) !== 1) {
            foreach ($lines as $n => $raw) {
                if (preg_match('//u', $raw) !== 1) {
                    throw new EsfError('not valid UTF-8', $n + 1);
                }
            }
        }

        $fits = [];
        $current = null;
        $header = null;
        $inBlock = false;
        foreach ($lines as $n => $raw) {
            $number = $n + 1;
            if (str_ends_with($raw, "\r")) {
                $raw = substr($raw, 0, -1);
            }
            if (str_contains($raw, "\r")) {
                throw new EsfError('a CR must be followed by an LF', $number);
            }

            $stripped = ltrim($raw, ' ');
            if (str_starts_with($stripped, '%')) {
                if (preg_match('/\A *%([a-z][a-z0-9-]*)\/([0-9]+) *\z/', $raw, $match) !== 1) {
                    throw new EsfError('malformed header', $number);
                }
                if ($header !== null) {
                    throw new EsfError('a fit needs a hull line', $header);
                }
                $inBlock = true;
                $current = null;
                if ($match[1] === 'esf') {
                    if ($match[2] !== '1') {
                        throw new EsfError("unsupported version esf/{$match[2]}", $number);
                    }
                    $header = $number;
                }
                continue;
            }

            if (!$inBlock) {
                throw new EsfError('a document starts with a header', $number);
            }
            if ($stripped === '' || str_starts_with($stripped, '//')) {
                continue;
            }

            $cursor = new Cursor($number, $raw);
            if ($header !== null) {
                $current = self::hull($header, $cursor);
                $fits[] = $current;
                $header = null;
            } elseif ($current !== null) {
                $current->lines[] = self::entry($cursor);
            }
        }

        if ($header !== null) {
            throw new EsfError('a fit needs a hull line', $header);
        }
        if ($fits === []) {
            throw new EsfError('a document holds at least one fit');
        }
        return $fits;
    }

    private static function hull(int $header, Cursor $cur): FitBlock
    {
        $block = new FitBlock($header, $cur->number);
        $cur->skipSpaces();
        if ($cur->token() === '-') {
            $cur->i++;
            if ($cur->nextElement()) {
                if ($cur->peek() !== '"') {
                    $cur->error("a hull line of '-' takes only a fit name");
                }
                $block->fitName = $cur->quoted();
            }
            $cur->finish();
            return $block;
        }

        if (self::isCount($cur->token())) {
            $cur->error('the hull line cannot have a count');
        }
        $block->hull = $cur->typeName();
        $more = $cur->nextElement();
        if ($more && $cur->peek() === '"') {
            $block->fitName = $cur->quoted();
            $more = $cur->nextElement();
        }
        if ($more) {
            if ($cur->peek() !== '/') {
                $cur->error("unexpected '{$cur->token()}' on the hull line");
            }
            $cur->i++;
            $block->mode = $cur->typeName();
        }
        $cur->finish();
        return $block;
    }

    private static function entry(Cursor $cur): EmptyLine|ItemLine
    {
        $cur->skipSpaces();
        $count = $cur->count();

        if ($cur->token() === '-') {
            $cur->i++;
            if (!$cur->nextElement() || $cur->peek() !== '@') {
                $cur->error('an empty slot must name its rack');
            }
            [$rack, $index] = $cur->location();
            if (!in_array($rack, self::RACKS, true)) {
                $cur->error('an empty slot must name a rack');
            }
            if ($index !== null && $count !== null) {
                $cur->error('a count and a pinned slot are mutually exclusive');
            }
            $cur->finish();
            return new EmptyLine($cur->number, $rack, $count, $index);
        }

        $line = new ItemLine($cur->number, $cur->typeName(), $count);
        $more = $cur->nextElement();

        if ($more && $cur->peek() === '"') {
            $line->fitName = $cur->quoted();
            if ($cur->nextElement()) {
                if ($cur->peek() !== '@') {
                    $cur->error('a reference takes only a count and a location');
                }
                [$line->location, $index] = $cur->location();
                if ($index !== null || !in_array($line->location, self::STORED, true)) {
                    $cur->error('a reference can only be stored');
                }
                $cur->finish();
            }
            return $line;
        }

        $stage = 0;
        while ($more) {
            $c = $cur->peek();
            if ($c === ':' && $stage < 1) {
                $cur->i++;
                $line->chargeCount = $cur->count();
                $line->charge = $cur->typeName();
                $stage = 1;
            } elseif ($c === '+' && $stage < 2) {
                $cur->i++;
                $line->mutaplasmid = $cur->typeName();
                $stage = 2;
            } elseif ($c === '{' && $stage < 3) {
                $line->overrides = $cur->overrides();
                $stage = 3;
            } elseif ($c === '!' && $stage < 4) {
                $line->state = $cur->state();
                $stage = 4;
            } elseif ($c === '@' && $stage < 5) {
                [$line->location, $line->index] = $cur->location();
                if ($line->index === null && !in_array($line->location, self::STORED, true)) {
                    $cur->error('an item names a rack only to pin a slot, which needs an index');
                }
                if ($line->index !== null && $count !== null) {
                    $cur->error('a count and a pinned slot are mutually exclusive');
                }
                $stage = 5;
            } else {
                $cur->error("unexpected '{$cur->token()}'");
            }
            $more = $cur->nextElement();
        }
        return $line;
    }
}
