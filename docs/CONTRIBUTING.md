# Contributing to MONEI Payments for Adobe Commerce

Thank you for your interest in contributing to the MONEI Payments module for Adobe Commerce (Magento 2)! This document provides guidelines and instructions for contributing to this project.

## Code of Conduct

We expect all contributors to adhere to a respectful and collaborative environment. By participating in this project, you agree to:

- Be respectful of differing viewpoints and experiences
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on our [GitHub repository](https://github.com/MONEI/MONEI-AdobeCommerce-Magento2/issues) with:

1. A clear, descriptive title
2. A detailed description of the issue
3. Steps to reproduce the bug
4. Expected and actual behavior
5. Screenshots (if applicable)
6. Environment details (Magento version, PHP version, etc.)

### Suggesting Enhancements

For feature requests or enhancements:

1. Create an issue with a clear title and detailed description
2. Explain why this enhancement would be useful to most users
3. Suggest a possible implementation approach if you have one

### Pull Requests

We welcome pull requests for bug fixes and new features:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes and commit (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

#### Pull Request Guidelines

- Ensure your code follows our coding standards
- Include tests for new functionality
- Update the documentation for significant changes
- Keep changes focused on a single purpose
- Write descriptive commit messages

## Development Workflow

1. Set up your local development environment as described in the [Development Guide](DEVELOPMENT.md)
2. Make your changes and test thoroughly
3. Run code quality checks before submitting:
   ```bash
   composer check:all
   ```
4. Fix any issues found:
   ```bash
   composer fix:all
   ```

## Coding Standards

We follow the Magento 2 coding standards and PSR-12 with some additional requirements:

- Use strict typing with PHP 8.1+ parameter and return types
- Classes use StudlyCaps, methods use camelCase, interfaces have Interface suffix
- Follow the existing code organization patterns
- Create proper PHPDoc comments for all methods and classes
- Ensure backward compatibility when possible

## Commit Message Format

We follow conventional commit messages:

```
type(scope): message
```

Types include:

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, etc.)
- **refactor**: Code changes that neither fix bugs nor add features
- **test**: Adding or updating tests
- **chore**: Changes to the build process or tools

## Testing

All new code should have appropriate test coverage:

- Unit tests for business logic
- Integration tests for complex functionality

## License

By contributing, you agree that your contributions will be licensed under the same [MIT License](../LICENSE) that covers the project.

## Questions?

If you have any questions about contributing, please [contact MONEI Support](https://monei.com/contact).
