#!/bin/bash

# Set the output filename
OUTPUT_FILE="monei-module-monei-payment.zip"

# Create a temporary exclusions file
TMP_EXCLUDE="/tmp/monei-exclude.txt"

# Create list of exclusions
cat >"$TMP_EXCLUDE" <<EOL
*.git*
*.github*
*Test*
.husky/*
.yarn/*
.vscode/*
.cursor/*
vendor/*
scripts/*
node_modules/*
composer.lock
package.json
yarn.lock
.yarnrc.yml
.nvmrc
.prettierrc
.prettierignore
prettyphp.json
pretty-php.phar*
.cursorignore
release-it.json
commitlint.config.js
bin
EOL

# Remove old zip if it exists
if [ -f "$OUTPUT_FILE" ]; then
    rm "$OUTPUT_FILE"
fi

# Create zip archive excluding specified patterns
zip -r "$OUTPUT_FILE" . -x@"$TMP_EXCLUDE"

# Clean up temporary file
rm "$TMP_EXCLUDE"

echo "Created archive: $OUTPUT_FILE"
