<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__.'/Tests',
        __DIR__.'/Transport',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'ordered_class_elements' => true,
        'ordered_interfaces' => true,
        'protected_to_private' => true,
    ])
    ->setFinder($finder);
