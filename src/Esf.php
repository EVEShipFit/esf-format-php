<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat;

use EveShipFit\EsfFormat\Internal\Binary;
use EveShipFit\EsfFormat\Internal\CanonFit;
use EveShipFit\EsfFormat\Internal\Canonical;
use EveShipFit\EsfFormat\Internal\FitBlock;
use EveShipFit\EsfFormat\Internal\Names;
use EveShipFit\EsfFormat\Internal\Resolver;
use EveShipFit\EsfFormat\Internal\Source;
use EveShipFit\EsfFormat\Internal\Text;

/** Read and write esf/1 (https://github.com/EVEShipFit/esf-format). */
final class Esf
{
    /** Whether a document is text, by its first character (§10). */
    public static function isText(string $data): bool
    {
        return $data !== '' && ($data[0] === '%' || $data[0] === ' ');
    }

    /**
     * Read a text or binary document, in canonical form.
     *
     * @return list<EsfFit>
     * @throws EsfError
     */
    public static function load(string $data, Catalog $catalog): array
    {
        if ($data === '') {
            throw new EsfError('the document is empty');
        }
        $fits = self::isText($data)
            ? self::canonical(Text::parse($data), $catalog)
            : self::checked(Binary::decode($data), $catalog);
        return array_map(Canonical::toModel(...), $fits);
    }

    /**
     * Write fits as canonical text (§8).
     *
     * @param list<EsfFit> $fits
     * @throws EsfError
     */
    public static function save(array $fits, Catalog $catalog): string
    {
        return Canonical::render(self::checked($fits, $catalog));
    }

    /**
     * Write fits in the binary form (§10).
     *
     * @param list<EsfFit> $fits
     * @throws EsfError
     */
    public static function saveBinary(array $fits, Catalog $catalog): string
    {
        return Binary::encode(array_map(Canonical::toModel(...), self::checked($fits, $catalog)));
    }

    /** The binary form as base64url without padding, for URLs (§10.4). */
    public static function toBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * The binary form from base64url without padding (§10.4).
     *
     * @throws EsfError
     */
    public static function fromBase64Url(string $encoded): string
    {
        $encoded = trim($encoded);
        if (preg_match('/\A[A-Za-z0-9_-]*\z/', $encoded) !== 1 || strlen($encoded) % 4 === 1) {
            throw new EsfError('not valid base64url');
        }
        $padded = str_pad($encoded, (strlen($encoded) + 3) & ~3, '=');
        return (string) base64_decode(strtr($padded, '-_', '+/'), true);
    }

    /** Unicode simple case folding, for indexing a Catalog by name (§6.10). */
    public static function fold(string $name): string
    {
        return Names::fold($name);
    }

    /**
     * @param list<FitBlock> $blocks
     * @param list<EsfFit>|null $given
     * @return list<CanonFit>
     */
    private static function canonical(array $blocks, Catalog $catalog, ?array $given = null): array
    {
        $fits = Resolver::resolve($blocks, $catalog);
        if ($given !== null) {
            Source::verify($fits, $given);
        }
        return array_map(static fn($fit) => Canonical::fit($fit, $catalog), $fits);
    }

    /**
     * @param list<EsfFit> $fits
     * @return list<CanonFit>
     */
    private static function checked(array $fits, Catalog $catalog): array
    {
        [$data, $where] = Source::source($fits, $catalog);
        try {
            return self::canonical(Text::parse($data), $catalog, $fits);
        } catch (EsfError $e) {
            if ($e->lineNumber === null) {
                throw $e;
            }
            [$fit, $entry] = $where[$e->lineNumber - 1];
            throw new EsfError($e->reason, fitNumber: $fit, entryNumber: $entry);
        }
    }
}
