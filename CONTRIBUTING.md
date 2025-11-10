# Contributing to x402-php

Thank you for your interest in contributing to x402-php! This document provides guidelines and instructions for contributing to this project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing Requirements](#testing-requirements)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Release Process](#release-process)

## Code of Conduct

By participating in this project, you agree to maintain a respectful and inclusive environment. Please:

- Use welcoming and inclusive language
- Be respectful of differing viewpoints and experiences
- Gracefully accept constructive criticism
- Focus on what is best for the community
- Show empathy towards other community members

## AI-Assisted Development

If you're using AI coding assistants (GitHub Copilot, Cursor, Claude, GPT, etc.), **please read [AI_GUIDELINES.md](AI_GUIDELINES.md) before contributing**. This document contains critical information about:

- x402 protocol compliance requirements
- Security safeguards and validation rules
- Code quality standards specific to this project
- Common mistakes AI assistants make with this codebase

**Important**: The AI guidelines help ensure that AI-generated code strictly follows the x402 specification and doesn't introduce protocol violations or security issues.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Redis (for testing rate limiting and nonce tracking features)
- Git

### Setting Up Development Environment

1. **Fork and clone the repository:**
   ```bash
   git clone https://github.com/YOUR_USERNAME/x402-php.git
   cd x402-php
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Set up Redis for testing:**
   ```bash
   # macOS (using Homebrew)
   brew install redis
   brew services start redis
   
   # Ubuntu/Debian
   sudo apt-get install redis-server
   sudo systemctl start redis
   ```

4. **Run the test suite to verify setup:**
   ```bash
   ./vendor/bin/phpunit
   ```

5. **Run static analysis:**
   ```bash
   ./vendor/bin/phpstan analyse --level=8
   ```

## Development Workflow

### Branching Strategy

We use **Git Flow** for branch management:

- **`main`** - Production-ready code, tagged with version numbers
- **`develop`** - Integration branch for features (currently we work directly on main, but this is the future direction)
- **`feature/*`** - Feature branches (e.g., `feature/add-range-payment-scheme`)
- **`bugfix/*`** - Bug fix branches (e.g., `bugfix/fix-nonce-validation`)
- **`hotfix/*`** - Emergency production fixes (e.g., `hotfix/security-patch`)

### Creating a Feature Branch

```bash
git checkout main
git pull origin main
git checkout -b feature/your-feature-name
```

### Making Changes

1. Make your changes in the feature branch
2. Add tests for new functionality
3. Update documentation as needed
4. Run tests and static analysis
5. Commit your changes following our [commit guidelines](#commit-guidelines)

## Coding Standards

### PHP Standards

We follow **PSR-12** coding style with additional requirements:

1. **Strict Types:** All PHP files must declare strict types:
   ```php
   <?php
   
   declare(strict_types=1);
   ```

2. **Type Declarations:** Use type hints for all parameters and return types:
   ```php
   public function validateAddress(string $address, string $network): bool
   {
       // ...
   }
   ```

3. **Readonly Properties:** Use readonly properties for immutable data (PHP 8.1+):
   ```php
   public function __construct(
       private readonly string $address,
       private readonly int $amount
   ) {}
   ```

4. **Documentation:** All public methods must have PHPDoc blocks:
   ```php
   /**
    * Validates an Ethereum address format.
    *
    * @param string $address The address to validate (with 0x prefix)
    * @return bool True if valid, false otherwise
    */
   public function validateEthereumAddress(string $address): bool
   ```

### Code Style Enforcement

Run PHP CS Fixer before committing:

```bash
./vendor/bin/php-cs-fixer fix
```

### Static Analysis

All code must pass PHPStan level 8:

```bash
./vendor/bin/phpstan analyse --level=8
```

Fix any errors before submitting a PR.

## Commit Guidelines

We follow **Conventional Commits** specification for clear and structured commit history.

### Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- **feat:** New feature
- **fix:** Bug fix
- **docs:** Documentation changes
- **style:** Code style changes (formatting, missing semicolons, etc.)
- **refactor:** Code refactoring without changing functionality
- **perf:** Performance improvements
- **test:** Adding or updating tests
- **chore:** Maintenance tasks, dependency updates
- **security:** Security fixes or improvements

### Scope (Optional)

The scope specifies what part of the codebase is affected:

- `validation` - Validation logic
- `facilitator` - Facilitator client
- `middleware` - Payment handler middleware
- `security` - Security features (rate limiting, nonce tracking)
- `types` - Type definitions
- `config` - Configuration
- `docs` - Documentation

### Examples

```bash
# Feature
git commit -m "feat(validation): add support for Solana address validation"

# Bug fix
git commit -m "fix(facilitator): handle network timeout gracefully"

# Documentation
git commit -m "docs(readme): update installation instructions"

# Security
git commit -m "security(nonce): prevent timing attack in nonce comparison"

# Breaking change
git commit -m "feat(handler)!: change PaymentHandler constructor signature

BREAKING CHANGE: PaymentHandler now requires NonceTrackerInterface as 
third parameter for replay attack prevention."
```

### Commit Message Rules

1. Use the imperative mood ("add feature" not "added feature")
2. Don't capitalize the first letter of the subject
3. No period at the end of the subject
4. Limit subject line to 72 characters
5. Separate subject from body with a blank line
6. Wrap body at 72 characters
7. Use body to explain **what** and **why**, not **how**

## Pull Request Process

### Before Submitting

1. **Ensure all tests pass:**
   ```bash
   ./vendor/bin/phpunit
   ```

2. **Run static analysis:**
   ```bash
   ./vendor/bin/phpstan analyse --level=8
   ```

3. **Check code style:**
   ```bash
   ./vendor/bin/php-cs-fixer fix --dry-run --diff
   ```

4. **Update CHANGELOG.md:**
   Add your changes under the `[Unreleased]` section following the existing format.

5. **Update documentation:**
   - Update README.md if adding new features
   - Add examples for new functionality
   - Update PHPDoc comments

### PR Template

When creating a PR, please include:

1. **Description:** Clear description of what the PR does
2. **Motivation:** Why this change is needed
3. **Testing:** How you tested the changes
4. **Breaking Changes:** List any breaking changes
5. **Checklist:** Complete the PR checklist

### PR Checklist

- [ ] Tests pass locally (`./vendor/bin/phpunit`)
- [ ] PHPStan level 8 passes (`./vendor/bin/phpstan analyse --level=8`)
- [ ] Code follows PSR-12 style guidelines
- [ ] All public methods have PHPDoc comments
- [ ] New features have tests (minimum 80% coverage for new code)
- [ ] CHANGELOG.md updated
- [ ] Documentation updated (README.md, examples, etc.)
- [ ] No merge conflicts with main branch
- [ ] Commit messages follow conventional commits format

### Review Process

1. At least one maintainer must approve the PR
2. All CI checks must pass
3. No unresolved conversations
4. Up to date with the base branch

### After Approval

- Maintainers will merge using "Squash and merge" for clean history
- Your PR will be included in the next release

## Testing Requirements

### Test Coverage

- **New features:** Minimum 80% code coverage
- **Bug fixes:** Add regression tests
- **Critical paths:** 100% coverage for security features

### Test Structure

```php
<?php

declare(strict_types=1);

namespace X402\Tests\YourNamespace;

use PHPUnit\Framework\TestCase;

class YourTest extends TestCase
{
    public function testDescriptiveTestName(): void
    {
        // Arrange
        $input = 'test-data';
        
        // Act
        $result = $this->subject->method($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Validation/ValidatorTest.php

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

### Integration Tests

For features that interact with external services:

1. Use mocks for unit tests
2. Provide integration tests that can be run with real services
3. Document any required setup in test docblocks

## Security Vulnerabilities

**DO NOT** open public issues for security vulnerabilities.

Instead, please email security concerns to: **security@mondb.dev** (or create a private security advisory on GitHub)

Include:

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We will respond within 48 hours and work with you on a fix.

## Release Process

### Versioning

We follow **Semantic Versioning 2.0.0** (semver.org):

- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (0.X.0): New features, backward compatible
- **PATCH** (0.0.X): Bug fixes, backward compatible

### Release Workflow (Maintainers Only)

1. **Update version number:**
   ```bash
   # Update VERSION file
   echo "2.1.0" > VERSION
   
   # Update composer.json (if needed)
   # Update CHANGELOG.md - move [Unreleased] to [2.1.0] - YYYY-MM-DD
   ```

2. **Commit version bump:**
   ```bash
   git add VERSION CHANGELOG.md composer.json
   git commit -m "chore(release): bump version to 2.1.0"
   ```

3. **Create git tag:**
   ```bash
   git tag -a v2.1.0 -m "Release version 2.1.0"
   git push origin main --tags
   ```

4. **Create GitHub release:**
   - Go to GitHub releases
   - Click "Draft a new release"
   - Select the tag
   - Copy changelog entries
   - Publish release

### Pre-release Versions

For beta/alpha releases:

```bash
# Alpha
git tag -a v2.1.0-alpha.1 -m "Alpha release 2.1.0-alpha.1"

# Beta
git tag -a v2.1.0-beta.1 -m "Beta release 2.1.0-beta.1"

# Release candidate
git tag -a v2.1.0-rc.1 -m "Release candidate 2.1.0-rc.1"
```

## Documentation Guidelines

### README Updates

When adding features, update README.md with:

1. **Feature description** in the features section
2. **Usage examples** with code snippets
3. **Configuration options** if applicable
4. **Security considerations** for security-related features

### Code Examples

- Must be executable PHP code
- Include all necessary use statements
- Show realistic use cases
- Include comments explaining non-obvious code

### API Documentation

- Use PHPDoc for all public APIs
- Include `@param`, `@return`, `@throws` tags
- Add `@example` for complex methods
- Use `@deprecated` tag with migration path

## Questions?

- **Documentation:** Check README.md, QUICKSTART.md, and docs/
- **Issues:** Search existing issues before creating new ones
- **Discussions:** Use GitHub Discussions for questions
- **Security:** Email security@mondb.dev for security concerns

## License

By contributing to x402-php, you agree that your contributions will be licensed under the Apache License 2.0.

---

Thank you for contributing to x402-php! 🎉
