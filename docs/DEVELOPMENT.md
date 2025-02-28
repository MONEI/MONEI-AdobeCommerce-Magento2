# MONEI Payment Module - Developer Documentation

## Table of Contents
1. [Introduction](#introduction)
2. [Common Issues and Solutions](#common-issues-and-solutions)
3. [Code Quality Standards](#code-quality-standards)
4. [Security Best Practices](#security-best-practices)
5. [Performance Considerations](#performance-considerations)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

## Introduction

This document is intended for developers working on the MONEI Payment module for Adobe Commerce (Magento 2). It provides guidelines for addressing common issues, maintaining code quality, and following best practices.

## Common Issues and Solutions

### 1. Missing PHPDoc Blocks

**Issue**: Classes, methods, and properties should have proper PHPDoc blocks. These are automatically checked by PHP_CodeSniffer.

**Solution**:
```php
/**
 * Class description
 *
 * @api
 * @since 1.0.0
 */
class Example
{
    /**
     * Property description
     *
     * @var string
     */
    private $property;

    /**
     * Method description
     *
     * @param string $param Parameter description
     * @return bool Return value description
     */
    public function method($param)
    {
        // Method implementation
    }
}
```

To check for missing PHPDoc blocks, run:
```bash
composer phpcs
```

To automatically fix some PHPDoc issues, run:
```bash
composer phpcbf
```

### 2. Missing Type Hints

**Issue**: PHP 8.1 supports type hints, which should be used for parameters and return types.

**Solution**:
```php
/**
 * @param string $param Parameter description
 * @return bool Return value description
 */
public function method(string $param): bool
{
    // Method implementation
}
```

### 3. Incorrect Access Modifiers

**Issue**: Class properties and methods should have appropriate access modifiers.

**Solution**: Use `public`, `protected`, or `private` as appropriate:
```php
class Example
{
    private string $internalProperty;
    protected string $inheritableProperty;
    public string $publicProperty;
    
    private function internalMethod(): void
    {
        // Internal method logic
    }
    
    protected function inheritableMethod(): void
    {
        // Method that subclasses may override
    }
    
    public function publicMethod(): void
    {
        // Public API method
    }
}
```

### 4. Magic Methods Usage

**Issue**: Magic methods (`__get`, `__set`, etc.) should be used carefully.

**Solution**: Prefer explicit getters and setters over magic methods when possible:
```php
// Instead of using __get and __set
public function getProperty(): string
{
    return $this->property;
}

public function setProperty(string $value): self
{
    $this->property = $value;
    return $this;
}
```

### 5. Array Type Declaration

**Issue**: Using the generic `array` type hint does not specify the array structure.

**Solution**: Use PHPDoc to specify array structure:
```php
/**
 * @param string[] $items Array of strings
 * @return array<int, bool> Array of booleans indexed by integers
 */
public function processItems(array $items): array
{
    // Method implementation
}
```

### 6. Hard-coded Values

**Issue**: Hard-coded values create maintenance issues.

**Solution**: Use constants or configuration:
```php
// Instead of this:
public function getUrl()
{
    return 'https://api.monei.com/v1/payments';
}

// Do this:
const API_ENDPOINT = 'https://api.monei.com/v1/payments';

public function getUrl()
{
    return self::API_ENDPOINT;
}
```

## Code Quality Standards

### Coding Standards

The MONEI Payment module follows PSR-12 coding standards with Magento 2 specific requirements. The main rules include:

1. PHP files must use only UTF-8 without BOM
2. Class names must be declared in `StudlyCaps`
3. Method names must be declared in `camelCase`
4. Class constants must be declared in all upper case with underscore separators
5. Opening braces for classes and methods should go on the next line
6. Closing braces must go on the next line after the body
7. Visibility must be declared on all properties and methods
8. Use 4 spaces for indentation, not tabs

### Namespaces

The module uses PSR-4 autoloading with the `Monei\MoneiPayment` namespace. Follow these guidelines:

1. Each PHP file should have a namespace declaration
2. Import classes with the `use` operator
3. Group use declarations by type (classes, interfaces, traits)
4. Avoid deep nesting of namespaces

### Dependencies

1. Use dependency injection instead of direct object instantiation
2. Declare dependencies in the constructor
3. Use interfaces as type hints when possible
4. Avoid using the ObjectManager directly

Example:
```php
class PaymentProcessor
{
    private \Monei\MoneiPayment\Api\GatewayInterface $gateway;
    private \Psr\Log\LoggerInterface $logger;

    /**
     * @param \Monei\MoneiPayment\Api\GatewayInterface $gateway
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Monei\MoneiPayment\Api\GatewayInterface $gateway,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->logger = $logger;
    }
}
```

## Security Best Practices

### Input Validation

1. **Always validate user input**: Use appropriate validation methods based on the expected input type.
2. **Sanitize data before use**: Escape or sanitize data when used in SQL queries, HTML output, or other contexts.
3. **Use form validation**: Add validation rules to all form fields.

### Admin Controllers

Admin controllers should implement proper ACL checks:

```php
protected function _isAllowed(): bool
{
    return $this->_authorization->isAllowed('Monei_MoneiPayment::config');
}
```

### API Keys and Secrets

1. Never hardcode API keys or secrets
2. Use the Magento configuration system to store sensitive data
3. Use the Magento encryption mechanism for sensitive data

Example:
```php
public function getApiKey()
{
    return $this->encryptor->decrypt($this->scopeConfig->getValue('payment/monei/api_key'));
}
```

### Payment Processing

1. Always validate payment data before sending to the payment gateway
2. Implement proper error handling for API calls
3. Log payment processing errors but avoid exposing sensitive data in logs
4. Use the Magento payment interface methods correctly

## Performance Considerations

### Database Operations

1. Use repositories and collections instead of direct database queries
2. Avoid loading full collection when only a subset or count is needed
3. Use batch processing for operations on large data sets

### API Calls

1. Implement caching for responses that don't change frequently
2. Use asynchronous operations where appropriate
3. Implement proper error handling and retries for API calls

### Frontend Performance

1. Minimize JavaScript and CSS
2. Use Magento's built-in optimization features
3. Follow Magento best practices for frontend development

## Testing

### Unit Testing

1. Write unit tests for all public methods
2. Mock dependencies to isolate the unit under test
3. Use data providers to test with different inputs

Example:
```php
/**
 * @covers ::validatePayment
 * @dataProvider paymentDataProvider
 */
public function testValidatePayment($paymentData, $expectedResult)
{
    $validator = $this->getValidator();
    $this->assertEquals($expectedResult, $validator->validatePayment($paymentData));
}

public function paymentDataProvider()
{
    return [
        'valid payment' => [
            ['amount' => 100, 'currency' => 'EUR'], 
            true
        ],
        'invalid amount' => [
            ['amount' => -100, 'currency' => 'EUR'], 
            false
        ],
        // More test cases
    ];
}
```

### Integration Testing

1. Test how the module integrates with other Magento components
2. Test the module's configuration system
3. Verify the module works correctly within the Magento environment

## Troubleshooting

### Common Errors

#### 1. "Class does not exist" errors

**Possible causes:**
- Incorrect namespace
- Missing autoloader configuration
- Incorrect case in class name

**Solution:**
- Verify the namespace matches the directory structure
- Check if the class is imported correctly with `use` statements
- Verify the class name case matches the file name

#### 2. Payment Gateway Connection Issues

**Possible causes:**
- Incorrect API credentials
- Network issues
- API endpoint unavailable

**Solution:**
- Verify API credentials in the module configuration
- Check network connectivity to the API endpoints
- Review API logs for error details

#### 3. Missing PHPDoc Comments

Run the following command to generate a list of files with missing PHPDoc:

```bash
composer phpcs
```

#### 4. Fixing Code Style Issues

To automatically fix code style issues:

```bash
composer phpcbf
```

### Debugging Tools

1. **Magento Logging**:
   Use the Magento logger for debugging:
   ```php
   $this->logger->debug('Debug message');
   ```

2. **Xdebug**:
   Configure Xdebug for step-by-step debugging of the module.

3. **Custom Logging**:
   For payment-specific debugging, use the module's custom logger:
   ```php
   $this->paymentLogger->logTransaction('transaction_id', $data);
   ```

---

## Additional Resources

- [Magento 2 Developer Documentation](https://devdocs.magento.com/)
- [PHP-FIG Standards](https://www.php-fig.org/psr/)
- [MONEI API Documentation](https://docs.monei.com/api) 
