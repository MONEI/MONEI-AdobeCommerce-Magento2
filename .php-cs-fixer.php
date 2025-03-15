<?php

/** @copyright Copyright © Monei (https://monei.com) */

/**
 * PHP Coding Standards fixer configuration
 * Based on Magento 2 official configuration
 */
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('node_modules')
    ->exclude('var');

$config = new PhpCsFixer\Config();
return $config
    ->setRules([
        // Magento 2 official rules
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'include' => true,
        'new_with_braces' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'object_operator_without_whitespace' => true,
        'ordered_imports' => false,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        // Additional Magento 2 specific rules for docblocks
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_package' => false,  // Keep @package tags for Magento
        'phpdoc_no_useless_inheritdoc' => false,  // Keep @inheritDoc
        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        // Magento 2 specific docblock rules
        'phpdoc_tag_type' => ['tags' => []],  // Disabled inheritDoc formatting
        'general_phpdoc_annotation_remove' => ['annotations' => ['author']],
        // Line length configuration
        'blank_line_before_statement' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'method_chaining_indentation' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)  // Some DocBlock rules are considered risky
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
