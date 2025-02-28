#!/bin/bash

MODULE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$MODULE_ROOT" || exit 1

# Determine if we're in a standalone module or within a Magento installation
if [ -d "./vendor/bin" ]; then
    # Standalone module development
    PHPCS_BIN="./vendor/bin/phpcs"
    PHPCBF_BIN="./vendor/bin/phpcbf"
    PHPCS_FIXER_BIN="./vendor/bin/php-cs-fixer"
    IS_STANDALONE=true
else
    # Check if we are within a Magento installation
    MAGENTO_ROOT=$(cd ../../../.. && pwd)
    if [ -d "$MAGENTO_ROOT/vendor/bin" ]; then
        PHPCS_BIN="$MAGENTO_ROOT/vendor/bin/phpcs"
        PHPCBF_BIN="$MAGENTO_ROOT/vendor/bin/phpcbf"
        PHPCS_FIXER_BIN="$MAGENTO_ROOT/vendor/bin/php-cs-fixer"
        IS_STANDALONE=false
    else
        # Try to use global binaries
        PHPCS_BIN=$(which phpcs)
        PHPCBF_BIN=$(which phpcbf)
        PHPCS_FIXER_BIN=$(which php-cs-fixer)
        IS_STANDALONE=true
    fi
fi

# Check if tools are available
if [ ! -x "$PHPCS_BIN" ]; then
    echo "PHP_CodeSniffer not found. Please install it with 'composer require --dev squizlabs/php_codesniffer'."
    exit 1
fi

if [ ! -x "$PHPCS_FIXER_BIN" ]; then
    echo "PHP-CS-Fixer not found. Please install it with 'composer require --dev friendsofphp/php-cs-fixer'."
    exit 1
fi

# Check if Magento2 coding standard is available
if [ "$IS_STANDALONE" = "false" ]; then
    # Check if Magento2 coding standard is installed in the Magento root
    if $PHPCS_BIN -i | grep -q "Magento2"; then
        # Create a temporary Magento2 ruleset
        cat > "$MODULE_ROOT/phpcs.magento2.xml" <<EOL
<?xml version="1.0"?>
<ruleset name="Magento2">
    <description>Magento 2 coding standard for MONEI Payment module</description>
    <rule ref="Magento2"/>
    <exclude-pattern>.git/*</exclude-pattern>
    <exclude-pattern>.idea/*</exclude-pattern>
    <exclude-pattern>build/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>vendor/*</exclude-pattern>
    <exclude-pattern>stubs/*</exclude-pattern>
    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="p"/>
    <arg value="s"/>
</ruleset>
EOL
        PHPCS_STANDARD="$MODULE_ROOT/phpcs.magento2.xml"
    else
        PHPCS_STANDARD="$MODULE_ROOT/phpcs.xml"
    fi
else
    PHPCS_STANDARD="$MODULE_ROOT/phpcs.xml"
fi

# Function to handle exit on error
function check_error {
    if [ $? -ne 0 ]; then
        echo -e "\nError: $1 failed. Fix the issues above before continuing."
        exit 1
    fi
}

# Function to clean up temporary files
function cleanup {
    if [ -f "$MODULE_ROOT/phpcs.magento2.xml" ]; then
        rm "$MODULE_ROOT/phpcs.magento2.xml"
    fi
}

# Set trap to ensure cleanup on exit
trap cleanup EXIT

# Display module information
echo "============================================================"
echo "MONEI Payment Module Linting"
echo "Module path: $MODULE_ROOT"
if [ "$IS_STANDALONE" = "true" ]; then
    echo "Mode: Standalone (using PSR-12 standard)"
else
    if [ "$PHPCS_STANDARD" = "$MODULE_ROOT/phpcs.magento2.xml" ]; then
        echo "Mode: Magento Integration (using Magento2 standard)"
    else
        echo "Mode: Magento Integration (using PSR-12 standard, Magento2 not available)"
    fi
fi
echo "============================================================"

# Check if we want to run in errors-only mode
ERRORS_ONLY=${ERRORS_ONLY:-0}

# Run PHPCS with appropriate coding standards
if [ "$ERRORS_ONLY" -eq 1 ]; then
    echo -e "\n📋 Running PHP CodeSniffer (errors only)..."
    $PHPCS_BIN --standard="$PHPCS_STANDARD" --error-severity=1 --warning-severity=0 .
else
    echo -e "\n📋 Running PHP CodeSniffer (includes PHPDoc checks)..."
    $PHPCS_BIN --standard="$PHPCS_STANDARD" .
fi
check_error "PHP CodeSniffer"

# Run PHP-CS-Fixer in dry-run mode
echo -e "\n🔧 Running PHP-CS-Fixer in dry-run mode..."
SKIP_CS_FIXER=${SKIP_CS_FIXER:-0}
if [ "$SKIP_CS_FIXER" -eq 1 ]; then
    echo "Skipping PHP-CS-Fixer check as requested."
else
    # First try with environment variable
    export PHP_CS_FIXER_IGNORE_ENV=1
    $PHPCS_FIXER_BIN fix --config="$MODULE_ROOT/.php-cs-fixer.php" --dry-run --diff --allow-risky=yes

    # If it fails, suggest to the user how to skip it
    if [ $? -ne 0 ]; then
        echo -e "\nNote: If you want to skip PHP-CS-Fixer checks and only run PHP CodeSniffer, use:"
        echo "SKIP_CS_FIXER=1 composer lint"
        exit 1
    fi
fi

# All checks passed
echo -e "\n✅ All checks passed!"
exit 0
