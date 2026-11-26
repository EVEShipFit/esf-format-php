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
use EveShipFit\EsfFormat\Internal\Wire;
use InvalidArgumentException;

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
     * @throws EsfException
     */
    public static function load(string $data, Catalog $catalog): array
    {
        if ($data === '') {
            throw new EsfFormatException('the document is empty');
        }
        $fits = self::isText($data)
            ? self::canonical(Text::parse($data), $catalog)
            : self::checked(Binary::decode($data), $catalog);
        return array_map(Canonical::toModel(...), $fits);
    }

    /**
     * Read a text or binary document, and keep what is not a fit.
     *
     * The fits are the same as {@see Esf::load()} returns. The extra data is every text block
     * that is not a fit, like `%fleet/1`, and every field of a binary document that is not a fit
     * and is written the way {@see Esf::binaryField()} writes it. Other kinds of binary field are
     * skipped. The extra data comes in the order the document has it.
     * To write it back, add it to what {@see Esf::save()} or {@see Esf::saveBinary()} returns:
     * text blocks after the text, and binary fields, through {@see Esf::binaryField()}, after the binary.
     *
     * @throws EsfException Like {@see Esf::load()}, and also when a block's version is above 4294967295.
     */
    public static function loadDocument(string $data, Catalog $catalog): Document
    {
        if ($data === '') {
            throw new EsfFormatException('the document is empty');
        }
        if (self::isText($data)) {
            [$blocks, $extra] = Text::document($data, true);
            $fits = self::canonical($blocks, $catalog);
        } else {
            [$decoded, $extra] = Binary::document($data, true);
            $fits = self::checked($decoded, $catalog);
        }
        return new Document(array_map(Canonical::toModel(...), $fits), $extra);
    }

    /**
     * Write fits as canonical text (§8).
     *
     * @param list<EsfFit> $fits
     * @throws EsfException
     */
    public static function save(array $fits, Catalog $catalog): string
    {
        return Canonical::render(self::checked($fits, $catalog));
    }

    /**
     * Write fits in the binary form (§10).
     *
     * @param list<EsfFit> $fits
     * @throws EsfException
     */
    public static function saveBinary(array $fits, Catalog $catalog): string
    {
        return Binary::encode(array_map(Canonical::toModel(...), self::checked($fits, $catalog)));
    }

    /**
     * Write one field of a binary document.
     *
     * Add the result to what {@see Esf::saveBinary()} returns, to store more data next to the fits.
     * {@see Esf::loadDocument()} gives it back as a {@see BinaryExtra}.
     *
     * @param int $field The field number, from 2 to 536870911. Field 1 holds the fits.
     * @param string $bytes The content of the field, as a binary string.
     * @throws InvalidArgumentException When the field number is not in that range.
     */
    public static function binaryField(int $field, string $bytes): string
    {
        if ($field < 2 || $field > Wire::FIELD_MAX) {
            throw new InvalidArgumentException("field {$field} is not a field number from 2 to " . Wire::FIELD_MAX);
        }
        return Wire::record($field, $bytes);
    }

    /** The binary form as base64url without padding, for URLs (§10.4). */
    public static function toBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * The binary form from base64url without padding (§10.4).
     *
     * @throws EsfException
     */
    public static function fromBase64Url(string $encoded): string
    {
        $encoded = trim($encoded, " \t\n\r\v\f");
        if (preg_match('/\A[A-Za-z0-9_-]*\z/', $encoded) !== 1 || strlen($encoded) % 4 === 1) {
            throw new EsfFormatException('not valid base64url');
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
        } catch (EsfException $e) {
            if ($e->lineNumber === null) {
                throw $e;
            }
            [$fit, $entry] = $where[$e->lineNumber - 1];
            throw $e->at($fit, $entry);
        }
    }
}
