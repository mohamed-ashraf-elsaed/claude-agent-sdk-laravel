# Installation

> Complete guide to installing and configuring the Claude Agent SDK for Laravel in development, container, and CI environments.

## Overview

The SDK bridges Laravel with Anthropic's Claude Code CLI. You install the CLI (Node.js), pull the Composer package, publish the config, set environment variables, and verify. The entire process takes under five minutes.

## Prerequisites

| Requirement | Supported Versions |
|---|---|
| PHP | 8.1, 8.2, 8.3, 8.4 |
| Laravel | 10.x, 11.x, 12.x |
| Node.js (for CLI) | 18 LTS or later |

**Composer dependencies** (resolved automatically): `illuminate/support`, `illuminate/contracts`, `symfony/process`.

You will also need an **Anthropic API key** from [console.anthropic.com](https://console.anthropic.com).

## Step 1 — Install the Claude Code CLI

```bash
npm install -g @anthropic-ai/claude-code
claude --version
```

> **Tip:** If you use a custom npm prefix (e.g. `~/.npm-global`), make sure that directory is on your `PATH`.

## Step 2 — Require the Composer Package

```bash
composer require mohamed-ashraf-elsaed/claude-agent-sdk-laravel
```

Laravel's package auto-discovery registers the service provider automatically.

## Step 3 — Publish the Configuration

```bash
php artisan vendor:publish --tag=claude-agent-config
```

This creates `config/claude-agent.php`. See [[Configuration]] for every available option.

## Step 4 — Set Environment Variables

Add the required key to `.env`:

```env
ANTHROPIC_API_KEY=sk-ant-xxxxx
```

**Optional variables:**

```env
# SDK behaviour
CLAUDE_AGENT_CLI_PATH=/usr/local/bin/claude
CLAUDE_AGENT_MODEL=claude-sonnet-4-5-20250929
CLAUDE_AGENT_PERMISSION_MODE=acceptEdits
CLAUDE_AGENT_MAX_TURNS=10
CLAUDE_AGENT_MAX_BUDGET_USD=10.00
CLAUDE_AGENT_MAX_THINKING_TOKENS=8000
CLAUDE_AGENT_CWD=/var/www/project
CLAUDE_AGENT_TIMEOUT=300

# Custom API endpoint / auth
ANTHROPIC_BASE_URL=https://your-proxy.example.com
ANTHROPIC_AUTH_TOKEN=custom-token

# Third-party providers (enable ONE)
CLAUDE_CODE_USE_BEDROCK=true
CLAUDE_CODE_USE_VERTEX=true
CLAUDE_CODE_USE_FOUNDRY=true
```

> **Warning:** Never commit `ANTHROPIC_API_KEY` to version control. Add it to `.env` and ensure `.env` is in your `.gitignore`.

## Step 5 — Verify the Installation

```bash
php artisan tinker
```

```php
$agent = app(\MohamedAshrafElsaed\ClaudeAgentSDK\ClaudeAgent::class);
$result = $agent->message('Reply with "Installation successful."')->ask();
echo $result->getResultText();
```

If you see the response, the SDK, CLI, and API key are all wired correctly.

## CLI Auto-Discovery

When `CLAUDE_AGENT_CLI_PATH` is not set, the SDK probes the following paths in order:

1. `/usr/local/bin/claude`
2. `/usr/bin/claude`
3. `~/.npm-global/bin/claude`
4. `~/.local/bin/claude`
5. System `PATH` (via `which claude`)

Set `CLAUDE_AGENT_CLI_PATH` explicitly if the binary lives elsewhere or you want to skip the search.

## Docker / Container Environments

```dockerfile
FROM php:8.3-fpm

# Install Node.js LTS and the Claude Code CLI
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g @anthropic-ai/claude-code

# Install Composer dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

COPY . .

ENV ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
ENV CLAUDE_AGENT_CLI_PATH=/usr/local/bin/claude
```

> **Tip:** Pin the CLI version in production images (`npm install -g @anthropic-ai/claude-code@x.y.z`) to avoid unexpected upgrades.

## CI / GitHub Actions

```yaml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm install -g @anthropic-ai/claude-code
      - run: composer install --no-interaction
      - run: php artisan test
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
```

> **Warning:** Store your API key in **Settings > Secrets and variables > Actions** -- never hard-code it in workflow files.

## Upgrading

```bash
composer update mohamed-ashraf-elsaed/claude-agent-sdk-laravel
npm update -g @anthropic-ai/claude-code
```

After upgrading, re-publish the config to pick up new options (your existing values are preserved):

```bash
php artisan vendor:publish --tag=claude-agent-config --force
```

Review the changelog for breaking changes before upgrading across major versions.

## Next Steps

- [[Configuration]] — Customize models, budgets, timeouts, and permission modes.
- [[Basic-Usage]] — Send your first prompt and handle the response.
- [[Options-Reference]] — Full reference for every fluent option.
- [[Error-Handling]] — Gracefully handle CLI and API failures.
- [[Testing-Your-Integration]] — Mock the SDK in your test suite.
