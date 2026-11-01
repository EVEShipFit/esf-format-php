<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS2.0' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
