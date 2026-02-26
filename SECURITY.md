# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.x     | Yes                |
| < 1.0   | No                 |

## Reporting a Vulnerability

If you discover a security vulnerability in Claude Agent SDK for Laravel, **please report it responsibly**.

### How to Report

Email **[m.ashraf.saed@gmail.com](mailto:m.ashraf.saed@gmail.com)** with:

1. **Description** of the vulnerability
2. **Steps to reproduce** the issue
3. **Impact assessment** — what could be exploited and how
4. **Suggested fix** (if you have one)

### What to Expect

- **Acknowledgment** within 48 hours of your report
- **Initial assessment** within 5 business days
- **Resolution or mitigation plan** within 30 days for confirmed issues
- **Credit** in the release notes (unless you prefer to remain anonymous)

### Please Do NOT

- Open a public GitHub issue for security vulnerabilities
- Share vulnerability details publicly before a fix is released
- Test vulnerabilities against production systems you do not own

## Security Best Practices

When using this SDK in production, we recommend:

- **Never commit API keys** — use environment variables (`ANTHROPIC_API_KEY`)
- **Use `acceptEdits` or `default` permission mode** in production — avoid `bypassPermissions`
- **Set `max_budget_usd`** to prevent unexpected costs
- **Restrict `allowed_tools`** to only what your use case requires
- **Set `max_turns`** to limit agent execution scope
- **Review the [Security Guide](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/wiki/Security-Guide)** in our wiki

## Disclosure Policy

We follow coordinated disclosure. Once a fix is released, we will:

1. Publish a security advisory on GitHub
2. Release a patched version
3. Credit the reporter (with permission)
4. Document the fix in CHANGELOG.md
