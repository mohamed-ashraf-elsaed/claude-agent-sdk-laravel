# Installation

## Prerequisites

1. **PHP 8.1+**
2. **Laravel 10, 11, or 12**
3. **Claude Code CLI** — the SDK communicates with Claude via the CLI

Install the CLI globally:
```bash
npm install -g @anthropic-ai/claude-code
```

Verify it's installed:
```bash
claude --version
```

4. **Anthropic API Key** — get one from [console.anthropic.com](https://console.anthropic.com)

## Install the Package
```bash
composer require your-vendor/claude-agent-sdk-laravel
```

## Publish Configuration
```bash
php artisan vendor:publish --tag=claude-agent-config
```

This creates `config/claude-agent.php`.

## Environment Setup

Add to your `.env`:
```env
ANTHROPIC_API_KEY=sk-ant-xxxxx
```

Optional environment variables:
```env
CLAUDE_AGENT_MODEL=claude-sonnet-4-5-20250929
CLAUDE_AGENT_PERMISSION_MODE=acceptEdits
CLAUDE_AGENT_MAX_TURNS=10
CLAUDE_AGENT_CWD=/var/www/project
CLAUDE_AGENT_TIMEOUT=300
CLAUDE_AGENT_CLI_PATH=/usr/local/bin/claude
```

## Custom CLI Path

If the `claude` binary isn't in your PATH, specify its location:
```env
CLAUDE_AGENT_CLI_PATH=/home/user/.npm-global/bin/claude
```

The SDK searches these locations automatically:
- `/usr/local/bin/claude`
- `/usr/bin/claude`
- `~/.npm-global/bin/claude`
- `~/.local/bin/claude`
- System PATH via `which claude`

## Third-Party Providers

To use AWS Bedrock, Google Vertex, or Azure Foundry instead of the Anthropic API:
```env
CLAUDE_CODE_USE_BEDROCK=true
# or
CLAUDE_CODE_USE_VERTEX=true
# or
CLAUDE_CODE_USE_FOUNDRY=true
```