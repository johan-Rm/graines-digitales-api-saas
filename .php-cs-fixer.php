<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->append([
        __FILE__,
        __DIR__.'/rector.php',
        __DIR__.'/tests/phpstan/console-application.php',
    ]);

return (new PhpCsFixer\Config())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setRules([
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_after_namespace' => true,
        'line_ending' => true,
        'no_closing_tag' => true,
        'no_extra_blank_lines' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_blank_line_at_eof' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
    ])
    ->setFinder($finder);
