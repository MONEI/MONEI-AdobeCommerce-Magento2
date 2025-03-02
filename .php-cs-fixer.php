<?php

/**
 * PHP-CS-Fixer configuration file for comprehensive code style enforcement and DocBlock generation.
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('stubs');

$config = new PhpCsFixer\Config();
return $config->setRules([
    // General code style rules
    '@PSR12' => true,
    'array_syntax' => ['syntax' => 'short'],
    'no_unused_imports' => true,
    'ordered_imports' => true,

    // DocBlock generation and formatting rules - More aggressive settings
    'phpdoc_add_missing_param_annotation' => ['only_untyped' => false], // Add missing @param annotations and also for typed parameters
    'phpdoc_align' => false,
    'phpdoc_annotation_without_dot' => false, // Allow dots in annotations
    'phpdoc_indent' => true,
    'phpdoc_inline_tag_normalizer' => true,
    'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'multi'],
    'phpdoc_no_access' => true, // Remove @access tags
    'phpdoc_no_empty_return' => false, // Keep @return void
    'phpdoc_no_package' => false, // Keep @package tags for Magento
    'phpdoc_order' => true,
    'phpdoc_param_order' => true,
    'phpdoc_return_self_reference' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => true,
    'phpdoc_summary' => true,
    'phpdoc_tag_casing' => true,
    'phpdoc_tag_type' => ['tags' => ['inheritDoc' => 'inline']],
    'phpdoc_to_comment' => false, // Keep DocBlocks as DocBlocks, not comments
    'phpdoc_trim' => true,
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'phpdoc_types' => true,
    'phpdoc_types_order' => ['null_adjustment' => 'always_last', 'sort_algorithm' => 'none'],
    'phpdoc_var_annotation_correct_order' => true,
    'phpdoc_var_without_name' => true,

    // Behavior controls for DocBlocks
    'no_superfluous_phpdoc_tags' => false, // Keep @param and @return tags
    'phpdoc_no_alias_tag' => false, // Allow @var for properties
    'phpdoc_no_useless_inheritdoc' => false, // Keep @inheritDoc
    'general_phpdoc_annotation_remove' => ['annotations' => ['author']], // Removes specific annotations if needed
])
    ->setFinder($finder)
    ->setRiskyAllowed(true); // Some DocBlock rules are considered risky
