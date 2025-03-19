#!/bin/bash

# Check if jq is installed
if ! command -v jq &>/dev/null; then
    echo "Error: jq is required but not installed."
    echo "Please install it using:"
    echo "  brew install jq (on macOS)"
    echo "  apt-get install jq (on Ubuntu/Debian)"
    exit 1
fi

# Check if a file was provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <report-file.json>"
    exit 1
fi

# Check if the file exists
if [ ! -f "$1" ]; then
    echo "Error: File '$1' not found"
    exit 1
fi

print_separator() {
    printf "\n%80s\n" | tr " " "="
}

print_section_header() {
    echo -e "\033[1;34m$1\033[0m"
    printf "%80s\n\n" | tr " " "-"
}

# Process the JSON file
echo "Magento Failed Commands Report"
print_separator

# Initialize failure counter
failures=0

# Read and process each command entry
jq -r '.[] | select(.command != null and .output != null) | @text "\(.command)\n\(.output)"' "$1" | while read -r command; do
    read -r output

    # Check if output contains failure indicators
    if echo "$output" | grep -q "Fail\|Finished with the \"1\" exit code"; then
        ((failures++))

        print_section_header "Failed Command"
        echo "$command"

        print_section_header "Error Output"
        # Remove ANSI color codes and format the output
        echo -e "$output" | sed -r "s/\x1B\[([0-9]{1,3}(;[0-9]{1,2})?)?[mGK]//g" | sed '/^$/d'

        print_separator
    fi
done

if [ "$failures" -eq 0 ]; then
    echo "No failures found in the report."
    echo
fi

echo "End of Report"
