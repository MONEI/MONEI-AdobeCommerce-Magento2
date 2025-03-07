#!/bin/bash

# This script helps set up the development environment for the Monei_MoneiPayment module in VSCode

# Define the Magento root directory (4 levels up from the module)
MAGENTO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
MODULE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Setting up VSCode development environment for Monei_MoneiPayment module"
echo "Magento root: $MAGENTO_ROOT"
echo "Module root: $MODULE_ROOT"

# Create dev directory if it doesn't exist
mkdir -p "$MODULE_ROOT/dev"

# Create symlinks to Magento vendor directory
if [ ! -L "$MODULE_ROOT/dev/vendor_symlink" ]; then
    ln -s "$MAGENTO_ROOT/vendor" "$MODULE_ROOT/dev/vendor_symlink"
    echo "Created symlink to vendor directory"
else
    echo "Vendor symlink already exists"
fi

# Create symlink to Magento root
if [ ! -L "$MODULE_ROOT/dev/magento_symlink" ]; then
    ln -s "$MAGENTO_ROOT" "$MODULE_ROOT/dev/magento_symlink"
    echo "Created symlink to Magento root"
else
    echo "Magento root symlink already exists"
fi

# Ensure VSCode settings directory exists
mkdir -p "$MODULE_ROOT/.vscode"

echo "Development environment setup complete!"
echo ""
echo "For detailed VSCode setup instructions, please read:"
echo "dev/VSCODE_SETUP.md"
echo ""
echo "Quick setup:"
echo "1. Install recommended extensions (View > Extensions > Show Recommended Extensions)"
echo "2. Restart VSCode to apply all settings"
echo ""
echo "If you need to use absolute paths instead of relative paths, update .vscode/settings.json with:"
echo "\"$MAGENTO_ROOT/vendor\","
echo "\"$MAGENTO_ROOT/lib\","
echo "\"$MAGENTO_ROOT/generated\","
echo "\"$MAGENTO_ROOT/app/code\","
echo "\"$MAGENTO_ROOT/app/design\""

# Make the script executable
chmod +x "$MODULE_ROOT/dev/setup_dev.sh"
