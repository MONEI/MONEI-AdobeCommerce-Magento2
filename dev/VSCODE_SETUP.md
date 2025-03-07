# VSCode Setup for Magento 2 Development

This guide will help you set up Visual Studio Code (VSCode) for Magento 2 module development.

## Required Extensions

Install the following VSCode extensions for the best Magento 2 development experience:

1. **PHP Intelephense** (`bmewburn.vscode-intelephense-client`) - PHP code intelligence
2. **PHP Namespace Resolver** (`mehedidracula.php-namespace-resolver`) - Auto-import PHP classes
3. **PHP DocBlocker** (`neilbrayfield.php-docblocker`) - Generate PHP docblocks
4. **PHP CodeSniffer** (`ikappas.phpcs`) - Code style checking
5. **PHP Sniffer & Beautifier** (`valeryanm.vscode-phpsab`) - Code formatting
6. **PHP Debug** (`xdebug.php-debug`) - Xdebug integration
7. **Auto Close Tag** (`formulahendry.auto-close-tag`) - HTML tag completion
8. **Auto Rename Tag** (`formulahendry.auto-rename-tag`) - HTML tag renaming

You can install these extensions from the Extensions view in VSCode or by running the setup script which will show recommended extensions.

## Setup Steps

1. **Run the setup script**:
   ```bash
   cd dev
   ./setup_dev.sh
   ```

2. **Verify VSCode settings**:
   - Check that `.vscode/settings.json` has the correct include paths
   - If the relative paths don't work, update them to absolute paths as shown in the setup script output

3. **Configure PHP CodeSniffer**:
   - Open VSCode settings (File > Preferences > Settings)
   - Search for "phpcs"
   - Set the standard to "Magento2"
   - Set the path to your Magento's coding standard: `../../../../vendor/magento/magento-coding-standard`

4. **Configure Xdebug** (if using):
   - Verify the launch configuration in `.vscode/launch.json`
   - Update the path mappings if your Magento installation is not at `/var/www/html`

## Using VSCode Tasks

We've included several predefined tasks to make Magento development easier. To use them:

1. Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on Mac) to open the Command Palette
2. Type "Tasks: Run Task" and select it
3. Choose from the available tasks:

### Magento Tasks
- **Magento: Clear Cache** - Clears the Magento cache
- **Magento: Flush Cache** - Flushes the Magento cache
- **Magento: Setup Upgrade** - Runs the setup:upgrade command
- **Magento: Deploy Static Content** - Deploys static content
- **Magento: Compile DI** - Compiles dependency injection configuration
- **Magento: Enable Developer Mode** - Switches to developer mode
- **Magento: Enable Production Mode** - Switches to production mode

### Module Tasks
- **Module: Run PHP CodeSniffer** - Checks code style
- **Module: Fix PHP CodeSniffer Issues** - Automatically fixes some code style issues
- **Module: Run PHP-CS-Fixer Check** - Checks code style with PHP-CS-Fixer
- **Module: Fix PHP-CS-Fixer Issues** - Fixes code style issues with PHP-CS-Fixer

## Troubleshooting

### Class Autocompletion Not Working

If VSCode/Intelephense can't find Magento classes:

1. Check that the include paths in `.vscode/settings.json` are correct
2. Try using absolute paths instead of relative paths
3. Restart VSCode after making changes
4. Run the Intelephense command "Index workspace" from the command palette (F1)

### PHP CodeSniffer Errors

If you're getting errors with PHP CodeSniffer:

1. Make sure you have the Magento Coding Standard installed
2. Check that the path to the standard is correct in your settings
3. Try running the coding standards check from the command line:
   ```bash
   vendor/bin/phpcs --standard=Magento2 path/to/your/file.php
   ```

### Xdebug Not Connecting

If Xdebug isn't connecting:

1. Verify Xdebug is installed and configured in your PHP installation
2. Check that the port in `.vscode/launch.json` matches your Xdebug configuration
3. Ensure path mappings are correct for your environment

## Additional Resources

- [Magento 2 DevDocs](https://devdocs.magento.com/)
- [Intelephense Documentation](https://intelephense.com/)
- [VSCode PHP Debug Extension](https://github.com/xdebug/vscode-php-debug)
- [Magento 2 Coding Standard](https://github.com/magento/magento-coding-standard) 
