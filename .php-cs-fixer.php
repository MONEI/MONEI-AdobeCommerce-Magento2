<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'build',
        'vendor',
        '.git',
        '.idea',
        'stubs',
    ]);

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'declare_strict_types' => true,
        'single_quote' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'no_extra_blank_lines' => ['tokens' => ['extra']],
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'method_public',
                'method_protected',
                'method_private',
                'magic',
            ],
        ],
    ])
    ->setFinder($finder);
