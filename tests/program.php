<?php

// The program the esf-format test runner calls: program.php check|canonical|binary.

declare(strict_types=1);

use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfException;
use EveShipFit\EsfFormat\Tests\JsonCatalog;

require __DIR__ . '/../vendor/autoload.php';

$command = $argv[1] ?? 'check';
$catalog = new JsonCatalog((string) getenv('ESF_CATALOG'));
try {
    $fits = Esf::load((string) stream_get_contents(STDIN), $catalog);
    if ($command === 'canonical') {
        fwrite(STDOUT, Esf::save($fits, $catalog));
    } elseif ($command === 'binary') {
        fwrite(STDOUT, Esf::saveBinary($fits, $catalog));
    }
} catch (EsfException $e) {
    fwrite(STDERR, "invalid: {$e->getMessage()}\n");
    exit(1);
}
