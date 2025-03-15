# MONEI Payment Module Development Guide

## Build & Testing Commands

- Lint: `composer lint` - Run code style checks
- Fix code style: `composer fix` - Auto-fix coding standards issues
- Fix all styles: `composer fix:all` - Run all fixers
- Type check: `composer phpstan` - Run static analysis
- Single file analysis: `composer phpstan:single-file [file]` - Analyze a specific file
- Run tests: `composer test` - Run all unit tests
- Run specific tests: `composer test:unit -- --filter=PaymentDTOTest` - Run single test class
- Generate coverage: `composer test:coverage` - Generate HTML test coverage report

## Magento Commands

- Enable module: `php bin/magento module:enable Monei_MoneiPayment`
- Update module: `php bin/magento setup:upgrade`
- Deploy static content: `php bin/magento setup:static-content:deploy`
- Clear cache: `php bin/magento cache:clean`
- Run specific command: `php bin/magento monei:verify-apple-pay-domain <domain>`

## Code Style Guidelines

- Follow PSR-12 and Magento 2 coding standards
- Class names: PascalCase (e.g., `PaymentProcessor`)
- Methods/variables: camelCase (e.g., `processPayment()`)
- Constants: UPPER_SNAKE_CASE (e.g., `PAYMENT_STATUS_COMPLETED`)
- Use type hints and return types for all methods
- Handle exceptions with try/catch blocks and proper logging
- Use dependency injection for class dependencies
- Follow Magento 2 design patterns (factories, repositories, etc.)
- Document all methods with PHPDoc comments

## Testing Guidelines

- Unit tests for all Model and Service classes
- Use mocks for external dependencies
- Follow naming convention: `ClassNameTest` in matching directories
- Test all public methods with various scenarios
- Test edge cases and error conditions
- Write integration tests for critical processes
- Keep test coverage above 80% for core payment classes

### Key Test Classes and Their Purpose

1. `PaymentDTOTest`: Validates the data transfer object functionality and payment status checks
2. `PaymentProcessingResultTest`: Tests the payment processing result object
3. `MoneiApiClientTest`: Tests API client initialization and communication with MONEI API
4. `CreatePaymentTest`: Tests payment creation service with different parameter combinations
5. `GetPaymentTest`: Tests payment retrieval service
6. `RefundPaymentTest`: Tests payment refund service
7. `PaymentProcessorTest`: Tests the core payment processing logic for different payment states
8. `LockManagerTest`: Tests the locking mechanisms for concurrent payment processing
9. `PaymentFlowTest`: Integration tests for full payment processing flow

### Stubs and Mocking

- `MoneiStubs.php`: Contains stub implementations for the MONEI SDK classes
- `MagentoStubs.php`: Contains stub implementations for Magento framework classes
- Use PHPUnit's `createMock()` for creating test doubles
- For complex dependencies, create custom stub classes

### Running Tests

- Run all tests: `composer test`
- Run with detailed output: `vendor/bin/phpunit -c Test/phpunit.xml --testdox`
- Run a specific test suite: `vendor/bin/phpunit -c Test/phpunit.xml --testsuite="MONEI Payment Module Unit Tests"`
- Run a specific test class: `vendor/bin/phpunit -c Test/phpunit.xml --filter=PaymentDTOTest`
- Run a specific test method: `vendor/bin/phpunit -c Test/phpunit.xml --filter=PaymentDTOTest::testGettersAndSetters`
