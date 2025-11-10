# Maintainers Guide

This document provides guidance for maintainers of the x402-php project.

## Responsibilities

### Core Maintainers

Core maintainers have the following responsibilities:

1. **Code Review**: Review and merge pull requests
2. **Issue Triage**: Label and prioritize issues
3. **Release Management**: Create and publish releases
4. **Security**: Respond to security vulnerabilities
5. **Community**: Foster a welcoming community
6. **Documentation**: Ensure documentation stays current

## Current Maintainers

- **@mondb-dev** - Project Lead

## Becoming a Maintainer

Contributors who have:
- Made significant code contributions
- Demonstrated understanding of the x402 protocol
- Helped with code reviews
- Been active in the community

May be invited to become maintainers.

## Release Process

### Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- MAJOR.MINOR.PATCH (e.g., 2.1.3)
- MAJOR: Breaking changes
- MINOR: New features, backward compatible
- PATCH: Bug fixes, backward compatible

### Creating a Release

1. **Update CHANGELOG.md**
   ```bash
   # Move [Unreleased] section to new version
   # Add date: ## [2.1.0] - 2025-11-10
   ```

2. **Update VERSION file**
   ```bash
   echo "2.1.0" > VERSION
   ```

3. **Update composer.json if needed**
   ```json
   {
     "version": "2.1.0"
   }
   ```

4. **Commit version bump**
   ```bash
   git add VERSION CHANGELOG.md composer.json
   git commit -m "chore(release): bump version to 2.1.0"
   git push origin main
   ```

5. **Create and push tag**
   ```bash
   git tag -a v2.1.0 -m "Release version 2.1.0

   Changes:
   - Feature 1
   - Feature 2
   - Bug fix 1"
   
   git push origin v2.1.0
   ```

6. **GitHub Actions will automatically:**
   - Run CI tests
   - Create GitHub release
   - Extract changelog
   - Trigger Packagist update (if webhook configured)

7. **Verify release**
   - Check GitHub releases page
   - Check Packagist.org updates
   - Test installation: `composer require mondb-dev/x402-php:^2.1`

### Pre-release Versions

For testing before official release:

```bash
# Alpha release
git tag -a v2.1.0-alpha.1 -m "Alpha release for testing"
git push origin v2.1.0-alpha.1

# Beta release
git tag -a v2.1.0-beta.1 -m "Beta release for testing"
git push origin v2.1.0-beta.1

# Release candidate
git tag -a v2.1.0-rc.1 -m "Release candidate"
git push origin v2.1.0-rc.1
```

### Hotfix Process

For critical bugs in production:

```bash
# Create hotfix branch from latest tag
git checkout -b hotfix/2.0.1 v2.0.0

# Fix the bug
# ... make changes ...

# Commit and tag
git commit -am "fix: critical security patch"
git tag -a v2.0.1 -m "Hotfix release 2.0.1"

# Merge to main
git checkout main
git merge hotfix/2.0.1
git push origin main v2.0.1

# Clean up
git branch -d hotfix/2.0.1
```

## PR Review Guidelines

### Before Merging

Check that the PR:

- [ ] Has a clear description
- [ ] Follows conventional commit format
- [ ] Includes tests for new features
- [ ] All CI checks pass
- [ ] Code coverage is maintained
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated
- [ ] No merge conflicts
- [ ] At least one approval from maintainer

### Review Checklist

**Code Quality:**
- [ ] Follows PSR-12 coding standards
- [ ] Uses strict types
- [ ] Has proper type hints
- [ ] Includes PHPDoc comments
- [ ] No obvious bugs or security issues

**Testing:**
- [ ] Tests are comprehensive
- [ ] Edge cases are covered
- [ ] Tests are readable and maintainable

**Documentation:**
- [ ] Public APIs are documented
- [ ] Complex logic has comments
- [ ] Examples are provided for new features

**Protocol Compliance:**
- [ ] Changes align with x402 specification
- [ ] No violations of protocol requirements

### Feedback Style

- Be constructive and respectful
- Explain the "why" behind requested changes
- Suggest solutions, not just problems
- Acknowledge good work
- Use GitHub's suggestion feature for small fixes

## Issue Triage

### Labels

Apply appropriate labels to issues:

**Type:**
- `bug` - Something isn't working
- `enhancement` - New feature request
- `documentation` - Documentation improvements
- `question` - Further information requested
- `security` - Security-related issue

**Priority:**
- `priority: critical` - Needs immediate attention
- `priority: high` - Important, address soon
- `priority: medium` - Normal priority
- `priority: low` - Nice to have

**Status:**
- `status: needs-triage` - Needs initial review
- `status: accepted` - Confirmed, ready to work on
- `status: in-progress` - Someone is working on it
- `status: blocked` - Waiting on something
- `status: wontfix` - Will not be fixed

**Complexity:**
- `good first issue` - Good for newcomers
- `help wanted` - Community contributions welcome
- `difficulty: easy` - Simple changes
- `difficulty: medium` - Moderate complexity
- `difficulty: hard` - Significant effort required

### Issue Response Times

- **Critical Security Issues**: Within 24 hours
- **Bugs**: Within 3 days
- **Feature Requests**: Within 1 week
- **Questions**: Within 2 days

## Security

### Vulnerability Response

1. **Acknowledge receipt** within 24 hours
2. **Assess severity** (Critical, High, Medium, Low)
3. **Create private fork** for fix development
4. **Develop and test fix**
5. **Coordinate disclosure** with reporter
6. **Publish security advisory** on GitHub
7. **Release patch** across affected versions
8. **Notify users** via GitHub releases and security advisories

### Security Release Process

```bash
# Create security patch
git checkout -b security/CVE-2025-XXXXX

# Fix vulnerability
# ... make changes ...

# Test thoroughly
./vendor/bin/phpunit
./vendor/bin/phpstan analyse

# Commit with security prefix
git commit -m "security: fix CVE-2025-XXXXX SQL injection

Details of the vulnerability and fix."

# Create patch release
echo "2.0.2" > VERSION
git add VERSION CHANGELOG.md
git commit -m "chore(release): security patch 2.0.2"
git tag -a v2.0.2 -m "Security release 2.0.2"
git push origin main v2.0.2

# Publish security advisory on GitHub
# - Go to Security > Advisories
# - Publish advisory with CVE details
# - Reference the patch version
```

## Community Management

### Code of Conduct Enforcement

If code of conduct violations occur:

1. **Document the incident** privately
2. **Discuss with other maintainers**
3. **Decide on appropriate action**:
   - Warning
   - Temporary ban
   - Permanent ban
4. **Communicate decision** to involved parties
5. **Document the outcome**

### Encouraging Contributors

- Thank contributors for their PRs
- Highlight good contributions in release notes
- Add contributors to acknowledgments
- Be patient with new contributors
- Provide constructive feedback

## Maintenance Tasks

### Regular Tasks

**Weekly:**
- Triage new issues
- Review open PRs
- Check for security advisories

**Monthly:**
- Review dependencies for updates
- Check for outdated dependencies: `composer outdated`
- Update dependencies if needed
- Review test coverage
- Check documentation accuracy

**Quarterly:**
- Review roadmap
- Update long-term plans
- Clean up stale issues/PRs

### Dependency Updates

```bash
# Check for outdated packages
composer outdated --direct

# Update dependencies
composer update

# Run tests
./vendor/bin/phpunit

# Check for breaking changes
./vendor/bin/phpstan analyse

# Commit if all passes
git commit -am "chore(deps): update dependencies"
```

### Stale Issues/PRs

Issues/PRs with no activity for 60 days:
1. Add `stale` label
2. Comment asking for update
3. Close after 14 days if no response
4. Can be reopened if activity resumes

## Communication

### GitHub Discussions

Monitor and participate in:
- General discussions
- Feature proposals
- Help requests
- Protocol discussions

### Social Media

Share:
- Release announcements
- Major features
- Community highlights
- x402 protocol news

## Tools and Resources

### Useful Commands

```bash
# Run full test suite with coverage
./vendor/bin/phpunit --coverage-html coverage/

# Static analysis
./vendor/bin/phpstan analyse --level=8

# Code style check
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Code style fix
./vendor/bin/php-cs-fixer fix

# Security audit
composer audit

# Generate API docs
./vendor/bin/phpdoc
```

### External Services

- **Packagist**: https://packagist.org/packages/mondb-dev/x402-php
- **GitHub Actions**: CI/CD automation
- **Codecov**: Code coverage tracking (if configured)

## Emergency Contacts

For critical issues when primary maintainer is unavailable:

- Project Lead: @mondb-dev
- Security Team: security@mondb.dev

## Questions?

If you have questions about maintaining this project, reach out to the project lead or create a discussion.

---

Thank you for helping maintain x402-php! 🙏
