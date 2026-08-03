<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->notName('*.blade.php');

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => ['statements' => ['return']],
        'no_extra_blank_lines' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
