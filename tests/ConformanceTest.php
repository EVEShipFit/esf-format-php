<?php

declare(strict_types=1);

namespace EveShipFit\EsfFormat\Tests;

use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The esf-format test cases, the way its runner checks them. */
final class ConformanceTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function cases(): iterable
    {
        $root = dirname(getenv('ESF_CATALOG') ?: 'esf-format/tests/catalog.json');
        foreach (['valid', 'invalid'] as $kind) {
            foreach (glob("{$root}/{$kind}/*", GLOB_ONLYDIR) ?: [] as $path) {
                yield "{$kind}/" . basename($path) => [$path];
            }
        }
    }

    private static function input(string $path): string
    {
        if (is_file("{$path}/input.esf")) {
            return (string) file_get_contents("{$path}/input.esf");
        }
        return Esf::fromBase64Url((string) file_get_contents("{$path}/input.b64"));
    }

    #[DataProvider('cases')]
    public function testCase(string $path): void
    {
        $catalog = ApiTest::catalog();
        $input = self::input($path);
        if (str_contains($path, '/invalid/')) {
            $this->expectException(EsfError::class);
            Esf::load($input, $catalog);
            return;
        }

        $canonical = (string) file_get_contents("{$path}/canonical.esf");
        $binary = Esf::fromBase64Url((string) file_get_contents("{$path}/canonical.b64"));
        $fits = Esf::load($input, $catalog);
        self::assertSame($canonical, Esf::save($fits, $catalog), 'canonical of input');
        self::assertSame($canonical, Esf::save(Esf::load($canonical, $catalog), $catalog), 'canonical of canonical.esf');
        self::assertSame(bin2hex($binary), bin2hex(Esf::saveBinary($fits, $catalog)), 'binary of input');
        self::assertSame($canonical, Esf::save(Esf::load($binary, $catalog), $catalog), 'canonical of canonical.b64');
    }
}
