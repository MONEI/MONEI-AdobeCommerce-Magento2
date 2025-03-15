#!/bin/bash

# Get the absolute path of the current directory
CURRENT_DIR=$(pwd)
ROOT_DIR=$(cd ../../../../../ && pwd)

# Function to run commands that need path argument
run_with_path() {
    local command=$1
    shift
    cd $ROOT_DIR
    case "$command" in
    "phpcs" | "phpcbf")
        bin/$command --standard=Magento2 app/code/Monei/MoneiPayment "$@"
        ;;
    "phpstan")
        bin/analyse app/code/Monei/MoneiPayment "$@"
        ;;
    "i18n:collect-phrases")
        bin/magento i18n:collect-phrases -o app/code/Monei/MoneiPayment/i18n/en_US.csv app/code/Monei/MoneiPayment
        ;;
    *)
        bin/$command app/code/Monei/MoneiPayment "$@"
        ;;
    esac
    local status=$?
    cd "$CURRENT_DIR"
    return $status
}

# Function to run commands without path argument
run_command() {
    local command=$1
    shift
    cd $ROOT_DIR && bin/$command "$@"
    local status=$?
    cd "$CURRENT_DIR"
    return $status
}

# Handle different commands
case "$1" in
"analyse" | "phpcs" | "phpcbf" | "i18n:collect-phrases")
    run_with_path "$@"
    ;;
*)
    run_command "$@"
    ;;
esac
