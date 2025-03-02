<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('stubs');

$config = new PhpCsFixer\Config();
return $config->setRules([
    '@PSR12' => true,
    'array_syntax' => ['syntax' => 'short'],
    'no_unused_imports' => true,
    'ordered_imports' => true,
    'phpdoc_add_missing_param_annotation' => true,
    'phpdoc_order' => true,
    'phpdoc_align' => false,
    'phpdoc_types' => true,
    'phpdoc_scalar' => true,
    'phpdoc_no_empty_return' => false,
    'phpdoc_trim' => true,
    'phpdoc_var_without_name' => true,
    'phpdoc_separation' => true,
    'phpdoc_to_comment' => false, // Keep DocBlocks as DocBlocks, not comments
    'no_superfluous_phpdoc_tags' => false, // Keep @param and @return tags
    'phpdoc_no_alias_tag' => false, // Allow @var for properties
    'phpdoc_no_useless_inheritdoc' => false, // Keep @inheritDoc
])
    ->setFinder($finder);
