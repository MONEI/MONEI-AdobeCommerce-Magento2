#!/bin/bash

MODULE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$MODULE_ROOT" || exit 1

# Set up color codes
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Determine if we're in a standalone module or within a Magento installation
if [ -d "./vendor/bin" ]; then
    # Standalone module development
    PHPCS_BIN="./vendor/bin/phpcs"
    IS_STANDALONE=true
else
    # Check if we are within a Magento installation
    MAGENTO_ROOT=$(cd ../../../.. && pwd)
    if [ -d "$MAGENTO_ROOT/vendor/bin" ]; then
        PHPCS_BIN="$MAGENTO_ROOT/vendor/bin/phpcs"
        IS_STANDALONE=false
    else
        # Try to use global binaries
        PHPCS_BIN=$(which phpcs)
        IS_STANDALONE=true
    fi
fi

# Check if tools are available
if [ ! -x "$PHPCS_BIN" ]; then
    echo -e "${RED}PHP_CodeSniffer not found. Please install it with 'composer require --dev squizlabs/php_codesniffer'.${NC}"
    exit 1
fi

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}MONEI Payment Module Critical Issues Check${NC}"
echo -e "${BLUE}============================================================${NC}"

# Function to check PHP files for insecure functions and patterns
check_security_issues() {
    echo -e "\n${YELLOW}🔒 Checking for security issues...${NC}"

    # Define critical patterns to search for
    PATTERNS=(
        # Insecure functions and methods
        "eval\s*\("
        "exec\s*\("
        "passthru\s*\("
        "shell_exec\s*\("
        "system\s*\("
        "proc_open\s*\("
        "popen\s*\("
        "curl_exec\s*\("
        "include\s*\(\s*\\\$_"
        "include_once\s*\(\s*\\\$_"
        "require\s*\(\s*\\\$_"
        "require_once\s*\(\s*\\\$_"
        "mysql_query"
        "mysqli_query"
        # SQL Injection
        "->query\s*\(\s*['\"]SELECT.*\\\$_"
        "->query\s*\(\s*['\"]INSERT.*\\\$_"
        "->query\s*\(\s*['\"]UPDATE.*\\\$_"
        "->query\s*\(\s*['\"]DELETE.*\\\$_"
        # XSS
        "echo\s+\\\$_"
        "print\s+\\\$_"
        "->setHtml\s*\(\s*\\\$_"
        # CSRF
        "TOKEN"
        # Sensitive data exposure
        "password"
        "credentials"
        "secret"
        "api.*key"
        # Potential logic issues
        "true\s*==\s*"
        "==\s*true"
        "false\s*==\s*"
        "==\s*false"
        # Deprecated functions
        "create_function\s*\("
    )

    found_issues=0

    echo -e "${YELLOW}Scanning PHP files for potential security issues...${NC}"

    for pattern in "${PATTERNS[@]}"; do
        echo -e "\n${YELLOW}Checking pattern: ${pattern}${NC}"

        # Find PHP files and grep for the pattern, exclude vendor directory
        result=$(find . -type f -name "*.php" -not -path "./vendor/*" | xargs grep -l -E "$pattern" 2>/dev/null)

        if [ -n "$result" ]; then
            echo -e "${RED}Potentially insecure pattern found: ${pattern}${NC}"
            echo "$result" | while read -r file; do
                echo -e "${RED}File: ${file}${NC}"
                grep -n -E --color=always "$pattern" "$file"
                echo ""
                ((found_issues++))
            done
        else
            echo -e "${GREEN}No issues found for this pattern.${NC}"
        fi
    done

    if [ $found_issues -eq 0 ]; then
        echo -e "\n${GREEN}✅ No common security issues detected.${NC}"
    else
        echo -e "\n${RED}⚠️ Found $found_issues potential security concerns. Please review them carefully.${NC}"
    fi
}

# Check for critical coding standards issues
check_critical_coding_standards() {
    echo -e "\n${YELLOW}📋 Checking for critical coding standards issues...${NC}"

    # Create a temporary ruleset with only critical rules
    cat > "$MODULE_ROOT/phpcs.critical.xml" <<EOL
<?xml version="1.0"?>
<ruleset name="Magento2Critical">
    <description>Critical coding standard checks for MONEI Payment module</description>

    <!-- Include critical security rules -->
    <rule ref="Generic.PHP.ForbiddenFunctions"/>
    <rule ref="Squiz.PHP.Eval"/>
    <rule ref="Generic.PHP.NoSilencedErrors"/>
    <rule ref="Zend.Files.ClosingTag"/>

    <!-- PHP compatibility rules -->
    <rule ref="PHPCompatibility.FunctionUse.RemovedFunctions"/>
    <rule ref="PHPCompatibility.IniDirectives.RemovedIniDirectives"/>
    <rule ref="PHPCompatibility.Constants.RemovedConstants"/>
    <rule ref="PHPCompatibility.Classes.NewClasses"/>

    <!-- Type safety -->
    <rule ref="Squiz.Scope.StaticThisUsage"/>
    <rule ref="Squiz.PHP.DisallowSizeFunctionsInLoops"/>

    <exclude-pattern>.git/*</exclude-pattern>
    <exclude-pattern>.idea/*</exclude-pattern>
    <exclude-pattern>build/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>vendor/*</exclude-pattern>

    <arg name="extensions" value="php"/>
    <arg name="colors"/>
    <arg value="p"/>
    <arg value="s"/>
</ruleset>
EOL

    # Run PHPCS with critical ruleset
    $PHPCS_BIN --standard="$MODULE_ROOT/phpcs.critical.xml" .

    # Clean up
    rm "$MODULE_ROOT/phpcs.critical.xml"
}

# Check for unprotected admin routes/controllers
check_admin_routes() {
    echo -e "\n${YELLOW}🔍 Checking for unprotected admin routes...${NC}"

    # Look for adminhtml route files
    admin_routes=$(find . -type f -name "routes.xml" -exec grep -l "adminhtml" {} \;)

    if [ -n "$admin_routes" ]; then
        echo -e "${YELLOW}Found admin routes in:${NC}"
        echo "$admin_routes"

        # Check for ACL requirements
        for route_file in $admin_routes; do
            echo -e "\n${YELLOW}Analyzing: $route_file${NC}"

            # Check if related controllers have ACL checks
            route_dir=$(dirname "$route_file")
            controller_dir="${route_dir}/../Controller/Adminhtml"

            if [ -d "$controller_dir" ]; then
                echo -e "${YELLOW}Checking controllers in: $controller_dir${NC}"

                # Check each controller file for _isAllowed method
                controllers=$(find "$controller_dir" -type f -name "*.php")

                for controller in $controllers; do
                    if grep -q "_isAllowed" "$controller"; then
                        echo -e "${GREEN}✅ Controller has ACL check: $controller${NC}"
                    else
                        echo -e "${RED}⚠️ Controller may not have proper ACL check: $controller${NC}"
                        echo -e "${RED}   Missing _isAllowed() method, which is required for admin controllers${NC}"
                    fi
                done
            else
                echo -e "${BLUE}No Adminhtml controllers found for this route.${NC}"
            fi
        done
    else
        echo -e "${GREEN}✅ No admin routes found.${NC}"
    fi
}

# Check for proper validation in forms
check_form_validation() {
    echo -e "\n${YELLOW}📝 Checking for proper form validation...${NC}"

    # Look for form fields without validation
    form_files=$(find . -type f -name "*.phtml" -o -name "*.php" | xargs grep -l "form" | grep -v "vendor")

    if [ -n "$form_files" ]; then
        echo -e "${YELLOW}Analyzing form files:${NC}"

        for file in $form_files; do
            echo -e "\n${YELLOW}Checking: $file${NC}"

            # Check for input fields without validation
            if grep -q "<input" "$file" && ! grep -q "validate\|data-validate" "$file"; then
                echo -e "${RED}⚠️ Form inputs without validation found in: $file${NC}"
            else
                echo -e "${GREEN}✅ Form validation appears to be present in: $file${NC}"
            fi
        done
    else
        echo -e "${GREEN}✅ No forms found or all forms have validation.${NC}"
    fi
}

# Run all checks
check_security_issues
check_critical_coding_standards
check_admin_routes
check_form_validation

echo -e "\n${BLUE}============================================================${NC}"
echo -e "${GREEN}✅ Critical issues check completed!${NC}"
echo -e "${BLUE}============================================================${NC}"

# Set executable permissions for this script
chmod +x "$0"

exit 0
