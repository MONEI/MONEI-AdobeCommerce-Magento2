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

    // Line length configuration
    'single_line_comment_style' => true,
    'align_multiline_comment' => true,
    'binary_operator_spaces' => true,
    'concat_space' => ['spacing' => 'one'],
    'blank_line_before_statement' => true,
    'list_syntax' => ['syntax' => 'short'],
    'linebreak_after_opening_tag' => true,
    'lowercase_cast' => true,
    'magic_constant_casing' => true,
    'magic_method_casing' => true,
    'no_extra_blank_lines' => true,
    'native_function_casing' => true,
    'native_function_type_declaration_casing' => true,
    'no_alternative_syntax' => true,
    'no_blank_lines_after_class_opening' => true,
    'no_leading_import_slash' => true,
    'no_trailing_comma_in_singleline_array' => true,
    'no_unneeded_control_parentheses' => true,
    'no_useless_else' => true,
    'no_whitespace_in_blank_line' => true,
    'normalize_index_brace' => true,

    // The following rule fixes line length issues
    'heredoc_indentation' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
    'method_chaining_indentation' => true,
    'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_spaces_around_offset' => true,
    'return_assignment' => true,
    'whitespace_after_comma_in_array' => true,

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
