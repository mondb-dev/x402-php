---
name: Bug Report
about: Report a bug in x402-php
title: '[BUG] '
labels: bug
assignees: ''
---

## Bug Description

A clear and concise description of what the bug is.

## Environment

- **x402-php Version:** [e.g., 2.0.0]
- **PHP Version:** [e.g., 8.1.0, 8.2.0]
- **Operating System:** [e.g., Ubuntu 22.04, macOS 13.0]
- **Facilitator:** [e.g., Coinbase, PayAI, Custom]
- **Network:** [e.g., Ethereum Mainnet, Sepolia, Solana Mainnet]

## Steps to Reproduce

1. Step one
2. Step two
3. Step three
4. See error

## Expected Behavior

A clear and concise description of what you expected to happen.

## Actual Behavior

A clear and concise description of what actually happened.

## Code Sample

```php
<?php

// Minimal reproducible code example
use X402\Middleware\PaymentHandler;
use X402\Config\X402Config;

$config = X402Config::forCoinbase('YOUR_KEY');
$handler = new PaymentHandler($config);

// ... code that produces the bug
```

## Error Messages

```
Paste any error messages, stack traces, or logs here
```

## Additional Context

Add any other context about the problem here (screenshots, related issues, etc.).

## Possible Solution

If you have ideas on how to fix the bug, please describe them here (optional).

## Checklist

- [ ] I have searched existing issues to ensure this is not a duplicate
- [ ] I have provided all the required information above
- [ ] I can reproduce this issue consistently
- [ ] I have included a minimal code example
