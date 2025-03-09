#!/bin/bash
# Simple script to run Magento commands from the module directory

# Get the absolute path to the Magento root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAGENTO_ROOT="$(cd "$SCRIPT_DIR/../../../../.." && pwd)"
MODULE_VENDOR_DIR="$SCRIPT_DIR/vendor"

# Go to Magento root
cd "$MAGENTO_ROOT" || {
    echo "Failed to change to Magento root directory"
    exit 1
}

# Run the Magento command with all arguments passed to this script
bin/magento "$@"

# Return to the module directory
cd "$SCRIPT_DIR" || {
    echo "Failed to return to module directory"
    exit 1
}
