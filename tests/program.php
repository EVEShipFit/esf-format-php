<?php

// The program the esf-format test runner calls: program.php check|canonical|binary|extra.

declare(strict_types=1);

use EveShipFit\EsfFormat\BinaryExtra;
use EveShipFit\EsfFormat\Esf;
use EveShipFit\EsfFormat\EsfException;
use EveShipFit\EsfFormat\Tests\JsonCatalog;

require __DIR__ . '/../vendor/autoload.php';

$command = $argv[1] ?? 'check';
$catalog = new JsonCatalog((string) getenv('ESF_CATALOG'));
try {
    $data = (string) stream_get_contents(STDIN);
    if ($command === 'extra') {
        foreach (Esf::loadDocument($data, $catalog)->extra as $extra) {
            $line = $extra instanceof BinaryExtra
                ? ['field' => $extra->field, 'bytes' => Esf::toBase64Url($extra->bytes)]
                : ['name' => $extra->name, 'version' => $extra->version, 'text' => $extra->text];
            fwrite(STDOUT, json_encode($line, JSON_THROW_ON_ERROR) . "\n");
        }
        exit(0);
    }
    $fits = Esf::load($data, $catalog);
    if ($command === 'canonical') {
        fwrite(STDOUT, Esf::save($fits, $catalog));
    } elseif ($command === 'binary') {
        fwrite(STDOUT, Esf::saveBinary($fits, $catalog));
    }
} catch (EsfException $e) {
    fwrite(STDERR, "invalid: {$e->getMessage()}\n");
    exit(1);
}
