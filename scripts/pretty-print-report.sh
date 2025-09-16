#!/bin/bash

# Check if jq is installed
if ! command -v jq &>/dev/null; then
    echo "ERROR: jq not installed. Install with: brew install jq (macOS) or apt-get install jq (Ubuntu)"
    exit 1
fi

if [ $# -eq 0 ]; then
    echo "Usage: $0 <report-file.json>"
    exit 1
fi

if [ ! -f "$1" ]; then
    echo "ERROR: File '$1' not found"
    exit 1
fi

# Check if top level has "reports" array (new format) or is array itself (old format)
if jq -e '.reports' "$1" >/dev/null 2>&1; then
    # New marketplace format
    echo "=== MARKETPLACE SUBMISSION REPORT ==="

    status=$(jq -r '.status // "UNKNOWN"' "$1")
    echo "STATUS: $status"

    # Process each report and find actual failures
    jq -r '.reports[] |
        "PHP: \(.php_version) | Magento: \(.magento_version) | Status: \(.status)"' "$1"

    echo ""
    echo "=== FAILURES ==="

    # Extract only failed commands with meaningful error messages
    jq -r '.reports[].details.commands[] |
        select(.output | type == "array") |
        select(.output | join(" ") | test("exit code.*(1|2)\"")) |
        "\n[FAILED] \(.command)\nERROR: \(.output | join(" ") |
            if test("Your requirements could not be resolved") then
                (split("Problem") | .[1] // . | split("Use the option")[0] // . |
                 gsub("\\s+"; " ") | .[0:200])
            elif test("Class .* not found") then
                (match("Class [^ ]+ not found") | .string)
            elif test("monolog/monolog") then
                (match("monolog/monolog [^ ]+ -> [^,]+") | .string)
            else
                .[0:200]
            end)"' "$1" 2>/dev/null | grep -v "^$"

    # If no failures found in the detailed check, look for the main failure reason
    if [ $? -ne 0 ] || [ -z "$(jq -r '.reports[].details.commands[] | select(.output | type == "array") | select(.output | join(" ") | test("exit code.*(1|2)\""))' "$1" 2>/dev/null)" ]; then
        jq -r '.reports[].details.commands[] |
            select(.output | type == "string") |
            select(.output | test("exit code.*(2)\"")) |
            "\n[FAILED] \(.command)\nERROR: \(.output | .[0:500])"' "$1" 2>/dev/null | grep -v "^$"
    fi

else
    # Old MFTF format
    echo "=== MFTF TEST REPORT ==="

    # Count total and failed
    total=$(jq 'length' "$1")
    failed=$(jq '[.[] | select(.output | test("exit code.*(1|2)\""))] | length' "$1" 2>/dev/null)

    echo "TOTAL: $total | FAILED: $failed"

    if [ "$failed" -gt 0 ]; then
        echo ""
        echo "=== FAILURES ==="

        jq -r '.[] |
            select(.output | test("exit code.*(1|2)\"")) |
            "\n[FAILED] \(.command)\nERROR: \(.output | .[0:200])"' "$1" | grep -v "^$"
    fi
fi

echo ""
echo "=== ANALYSIS ==="

# Check for specific known issues
if grep -q "monolog/monolog" "$1" 2>/dev/null; then
    echo "ISSUE: Monolog version conflict detected"
    echo "FIX: Update composer.json to support both Monolog 2.x and 3.x: \"monolog/monolog\": \"^2.0 || ^3.0\""
fi

if grep -q "Class.*ClientFactory.*not found" "$1" 2>/dev/null; then
    echo "ISSUE: Missing factory class"
    echo "FIX: Remove ClientFactory dependency or regenerate DI compilation"
fi

if grep -q "requires.*magento" "$1" 2>/dev/null; then
    echo "ISSUE: Magento version constraint issue"
    echo "FIX: Check module dependencies match target Magento version"
fi

echo "=== END ==="
