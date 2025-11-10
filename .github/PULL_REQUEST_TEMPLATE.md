## Description

<!-- Provide a clear and concise description of what this PR does -->

## Type of Change

<!-- Mark the relevant option with an 'x' -->

- [ ] 🐛 Bug fix (non-breaking change which fixes an issue)
- [ ] ✨ New feature (non-breaking change which adds functionality)
- [ ] 💥 Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] 📝 Documentation update
- [ ] 🎨 Code style update (formatting, renaming)
- [ ] ♻️ Code refactoring (no functional changes)
- [ ] ⚡ Performance improvement
- [ ] ✅ Test update
- [ ] 🔧 Build/CI update
- [ ] 🔒 Security fix

## Related Issues

<!-- Link to related issues using #issue_number -->

Fixes #
Relates to #

## Motivation and Context

<!-- Why is this change needed? What problem does it solve? -->

## Changes Made

<!-- List the specific changes made in this PR -->

- 
- 
- 

## Breaking Changes

<!-- If this PR introduces breaking changes, describe them here -->

**Breaking changes:**
- 

**Migration guide:**
```php
// Before
$old = new OldWay();

// After
$new = new NewWay();
```

## Testing

### How Has This Been Tested?

<!-- Describe how you tested your changes -->

- [ ] Unit tests
- [ ] Integration tests
- [ ] Manual testing

**Test Configuration:**
- PHP Version:
- Operating System:
- Facilitator (if applicable):

### Test Coverage

```bash
# Paste relevant test output here
```

## Code Quality Checklist

- [ ] My code follows the PSR-12 style guidelines
- [ ] I have run `./vendor/bin/php-cs-fixer fix`
- [ ] I have run `./vendor/bin/phpstan analyse --level=8` with no errors
- [ ] I have added/updated PHPDoc comments for public methods
- [ ] I have used strict type declarations (`declare(strict_types=1)`)
- [ ] I have used readonly properties where appropriate

## Testing Checklist

- [ ] All tests pass locally (`./vendor/bin/phpunit`)
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
- [ ] Test coverage is maintained or improved for changed code

## Documentation Checklist

- [ ] I have updated the CHANGELOG.md file under `[Unreleased]`
- [ ] I have updated the README.md (if adding features or changing usage)
- [ ] I have added/updated code examples in the `examples/` directory
- [ ] I have updated relevant documentation in `docs/`
- [ ] I have added inline code comments for complex logic

## Security Checklist (if applicable)

- [ ] I have considered security implications of my changes
- [ ] I have not introduced any obvious security vulnerabilities
- [ ] I have updated SECURITY.md or security documentation if needed
- [ ] I have added security-related tests if applicable

## Commit Messages

- [ ] My commit messages follow the Conventional Commits specification
- [ ] My commits are logically organized and atomic

## Screenshots (if applicable)

<!-- Add screenshots to help explain your changes -->

## Additional Notes

<!-- Any additional information reviewers should know -->

## Reviewer Notes

<!-- Anything specific you want reviewers to focus on? -->

**Areas for review:**
- 
- 

## Checklist Before Requesting Review

- [ ] I have performed a self-review of my own code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] My changes generate no new warnings
- [ ] I have checked my code and corrected any misspellings
- [ ] I have resolved any merge conflicts
- [ ] I have rebased on the latest main branch
- [ ] The branch is up-to-date with the base branch

---

## For Maintainers

<!-- Maintainers will fill this section -->

**Release Notes:**
<!-- Summary for release notes/changelog -->

**Semver Impact:**
- [ ] Patch (bug fixes)
- [ ] Minor (new features, backward compatible)
- [ ] Major (breaking changes)
