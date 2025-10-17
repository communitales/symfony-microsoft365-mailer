<?php

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/Tests',
        __DIR__.'/Transport',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'no_unused_imports' => true,
        'ordered_class_elements' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'ordered_interfaces' => true,
        'protected_to_private' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder)
    ->setParallelConfig(ParallelConfigFactory::detect());
