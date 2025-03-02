<?php

/**
 * Helper script to run PHP-CS-Fixer with various options.
 *
 * Usage:
 * php fix-cs.php                  - Check code style without fixing
 * php fix-cs.php fix              - Fix code style issues
 * php fix-cs.php fix --diff       - Show diff of changes
 * php fix-cs.php fix --path=Model - Fix only files in Model directory
 */

$command = 'vendor/bin/php-cs-fixer';
$configFile = '.php-cs-fixer.php';

// Default options
$options = [
    '--config=' . $configFile,
    '--verbose',
];

// Command (default: check only)
$action = isset($argv[1]) ? $argv[1] : 'check';

// Process additional arguments
$targetPath = null;
$showDiff = false;

for ($i = 2; $i < $argc; $i++) {
    if (strpos($argv[$i], '--path=') === 0) {
        $targetPath = substr($argv[$i], 7);
    } elseif ($argv[$i] === '--diff') {
        $showDiff = true;
    }
}

if ($showDiff) {
    $options[] = '--diff';
}

// Build the command
$fullCommand = $command . ' ' . $action . ' ' . implode(' ', $options);

// Add path if specified
if ($targetPath) {
    $fullCommand .= ' ' . $targetPath;
}

// Display and execute the command
echo "Running: $fullCommand\n";
passthru($fullCommand, $returnCode);

exit($returnCode);
