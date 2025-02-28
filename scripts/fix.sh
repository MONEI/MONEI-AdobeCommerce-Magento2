#!/bin/bash

MODULE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$MODULE_ROOT" || exit 1

# Determine if we're in a standalone module or within a Magento installation
if [ -d "./vendor/bin" ]; then
    # Standalone module development
    PHPCBF_BIN="./vendor/bin/phpcbf"
    PHPCS_FIXER_BIN="./vendor/bin/php-cs-fixer"
    PHPCS_BIN="./vendor/bin/phpcs"
    IS_STANDALONE=true
else
    # Check if we are within a Magento installation
    MAGENTO_ROOT=$(cd ../../../.. && pwd)
    if [ -d "$MAGENTO_ROOT/vendor/bin" ]; then
        PHPCBF_BIN="$MAGENTO_ROOT/vendor/bin/phpcbf"
        PHPCS_FIXER_BIN="$MAGENTO_ROOT/vendor/bin/php-cs-fixer"
        PHPCS_BIN="$MAGENTO_ROOT/vendor/bin/phpcs"
        IS_STANDALONE=false
    else
        # Try to use global binaries
        PHPCBF_BIN=$(which phpcbf)
        PHPCS_FIXER_BIN=$(which php-cs-fixer)
        PHPCS_BIN=$(which phpcs)
        IS_STANDALONE=true
    fi
fi

# Check if tools are available
if [ ! -x "$PHPCBF_BIN" ]; then
    echo "PHP Code Beautifier and Fixer not found. Please install it with 'composer require --dev squizlabs/php_codesniffer'."
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
echo "MONEI Payment Module Code Fixer"
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

# Check if we want to run only PHP-CS-Fixer
FIX_CS_ONLY=${FIX_CS_ONLY:-0}

if [ "$FIX_CS_ONLY" -eq 1 ]; then
    echo -e "\n🔧 Running only PHP-CS-Fixer..."
    # Set environment variable to ignore PHP version requirements
    export PHP_CS_FIXER_IGNORE_ENV=1
    $PHPCS_FIXER_BIN fix --config="$MODULE_ROOT/.php-cs-fixer.php" --allow-risky=yes

    if [ $? -ne 0 ]; then
        echo -e "\nError: PHP-CS-Fixer failed."
        exit 1
    fi

    echo -e "\n✅ PHP-CS-Fixer completed successfully!"
    exit 0
fi

# Run PHPCBF to fix coding standard violations
echo -e "\n🔧 Running PHP Code Beautifier and Fixer..."
$PHPCBF_BIN --standard="$PHPCS_STANDARD" .

# Run PHP-CS-Fixer to fix code style issues
echo -e "\n🔧 Running PHP-CS-Fixer..."
SKIP_CS_FIXER=${SKIP_CS_FIXER:-0}
if [ "$SKIP_CS_FIXER" -eq 1 ]; then
    echo "Skipping PHP-CS-Fixer as requested."
else
    # First try with environment variable
    export PHP_CS_FIXER_IGNORE_ENV=1
    $PHPCS_FIXER_BIN fix --config="$MODULE_ROOT/.php-cs-fixer.php" --allow-risky=yes

    # If it fails, suggest to the user how to skip it
    if [ $? -ne 0 ]; then
        echo -e "\nNote: If you want to skip PHP-CS-Fixer and only run PHP Code Beautifier, use:"
        echo "SKIP_CS_FIXER=1 composer fix"
        exit 1
    fi
fi

echo -e "\n✅ Fix completed! Run ./scripts/lint.sh to verify the changes."
exit 0
