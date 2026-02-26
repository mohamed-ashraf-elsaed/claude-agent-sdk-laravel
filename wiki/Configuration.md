# Configuration

> Comprehensive reference for every setting in `config/claude-agent.php`. Publish the config file, set environment variables, and fine-tune per-query behaviour with the fluent API.

## Publishing the Config File

```bash
php artisan vendor:publish --tag=claude-agent-config
```

This creates `config/claude-agent.php`. All keys support environment-variable overrides so you never need to touch the file directly in deployed environments.

## Full Config Reference

| Key | Type | Default | Env Variable | Description |
|-----|------|---------|-------------|-------------|
| `cli_path` | `?string` | `null` (auto-detect) | `CLAUDE_AGENT_CLI_PATH` | Absolute path to the `claude` binary |
| `api_key` | `?string` | `null` | `ANTHROPIC_API_KEY` | Anthropic API key for direct API access |
| `model` | `?string` | `null` (CLI default) | `CLAUDE_AGENT_MODEL` | Model identifier (e.g. `claude-sonnet-4-20250514`) |
| `permission_mode` | `string` | `'default'` | `CLAUDE_AGENT_PERMISSION_MODE` | How the CLI handles tool-use confirmations |
| `cwd` | `?string` | `null` (`base_path()`) | `CLAUDE_AGENT_CWD` | Working directory for file operations |
| `allowed_tools` | `array` | `[]` | -- | Pre-approved tool names (e.g. `['Bash', 'Read']`) |
| `max_turns` | `?int` | `null` (unlimited) | `CLAUDE_AGENT_MAX_TURNS` | Cap on conversation turns per query |
| `process_timeout` | `?int` | `null` (no limit) | `CLAUDE_AGENT_TIMEOUT` | Seconds before the CLI process is killed |
| `max_budget_usd` | `?float` | `null` (no limit) | `CLAUDE_AGENT_MAX_BUDGET_USD` | Spend ceiling in USD per query |
| `max_thinking_tokens` | `?int` | `null` (CLI default) | `CLAUDE_AGENT_MAX_THINKING_TOKENS` | Token budget for extended thinking |
| `api_base_url` | `?string` | `null` | `ANTHROPIC_BASE_URL` | Base URL for self-hosted or compatible endpoints |
| `auth_token` | `?string` | `null` | `ANTHROPIC_AUTH_TOKEN` | Bearer token for providers using a separate auth header |
| `providers.bedrock` | `bool` | `false` | `CLAUDE_CODE_USE_BEDROCK` | Route requests through AWS Bedrock |
| `providers.vertex` | `bool` | `false` | `CLAUDE_CODE_USE_VERTEX` | Route requests through Google Vertex AI |
| `providers.foundry` | `bool` | `false` | `CLAUDE_CODE_USE_FOUNDRY` | Route requests through Foundry |

## Authentication

Two credentials serve different purposes:

| Credential | Env Variable | When to Use |
|-----------|-------------|-------------|
| **API Key** | `ANTHROPIC_API_KEY` | Direct Anthropic API access -- the most common setup |
| **Auth Token** | `ANTHROPIC_AUTH_TOKEN` | Proxy servers or providers that require a Bearer token instead of an `x-api-key` header |

> **Warning** -- Never commit API keys to version control. Always use `.env` files or a secrets manager.

```dotenv
# .env
ANTHROPIC_API_KEY=sk-ant-...
```

## Model Selection

Leave `model` as `null` to use the CLI's built-in default. Override it when you need a specific model version or want to pin deployments:

```php
// Config default
'model' => env('CLAUDE_AGENT_MODEL', 'claude-sonnet-4-20250514'),

// Per-query override
ClaudeAgent::query('Summarise this report', model: 'claude-opus-4-20250514');
```

## Permission Control

Permission modes determine how the CLI handles potentially dangerous operations (file writes, shell commands, etc.).

| Mode | Flag Sent | Behaviour | Security Level |
|------|-----------|-----------|----------------|
| `default` | *(none)* | CLI prompts for confirmation on each tool use | Highest -- interactive only |
| `acceptEdits` | `--allowedTools Edit` | Auto-approves file edits; prompts for everything else | High -- safe for code-gen pipelines |
| `dontAsk` | `--allowedTools ...` | Auto-approves tools listed in `allowed_tools`; rejects the rest | Medium -- allowlist-based |
| `bypassPermissions` | `--dangerously-skip-permissions` | Skips all permission checks | **None** -- use only in sandboxed CI |

> **Danger** -- `bypassPermissions` disables every safety gate. Only use it inside disposable containers or CI runners with no access to production data.

```php
// Recommended for production background jobs
'permission_mode' => 'dontAsk',
'allowed_tools'   => ['Read', 'Grep', 'Glob'],
```

## Resource Limits

Protect your budget and infrastructure by setting hard ceilings.

```php
'max_turns'           => 10,       // Prevents runaway conversations
'max_budget_usd'      => 0.50,     // Hard spend cap per query
'max_thinking_tokens' => 16000,    // Limits extended-thinking token usage
'process_timeout'     => 120,      // Kill the process after 2 minutes
```

| Setting | Risk it Mitigates |
|---------|-------------------|
| `max_turns` | Infinite agent loops |
| `max_budget_usd` | Unexpected API charges |
| `max_thinking_tokens` | Excessive token consumption on complex reasoning |
| `process_timeout` | Hung or stalled CLI processes blocking workers |

> **Tip** -- In queue workers, always set `process_timeout` to a value lower than your queue connection's `retry_after` to avoid overlapping retries.

## Paths

| Key | Purpose |
|-----|---------|
| `cli_path` | Set this when the `claude` binary is not on `$PATH` (e.g. `/usr/local/bin/claude`). Leave `null` for auto-detection. |
| `cwd` | The directory the CLI operates in for file reads/writes. Defaults to `base_path()`. Override when agents should work inside a subdirectory or a temporary workspace. |

```php
'cli_path' => env('CLAUDE_AGENT_CLI_PATH', '/opt/claude/bin/claude'),
'cwd'      => storage_path('agent-workspace'),
```

## Tool Defaults

`allowed_tools` pre-approves tools globally. This array is merged with per-query overrides.

```php
'allowed_tools' => ['Read', 'Grep', 'Glob', 'Bash'],
```

When `permission_mode` is `dontAsk`, only the tools in this list will be available. Any tool not listed is rejected outright -- no prompt, no execution.

## API Providers

Route requests through cloud provider endpoints instead of the direct Anthropic API.

```dotenv
# AWS Bedrock
CLAUDE_CODE_USE_BEDROCK=true

# Google Vertex AI
CLAUDE_CODE_USE_VERTEX=true

# Foundry
CLAUDE_CODE_USE_FOUNDRY=true
```

For self-hosted or API-compatible proxies, set the base URL:

```dotenv
ANTHROPIC_BASE_URL=https://claude-proxy.internal.example.com/v1
ANTHROPIC_AUTH_TOKEN=your-proxy-bearer-token
```

> **Note** -- Enable only one provider at a time. The SDK passes these flags directly to the Claude Code CLI, which determines the routing logic.

## Override Priority

Values resolve top-down; the first non-null source wins:

```
1. query() / stream() parameters          (highest -- per-call)
2. withOptions() fluent defaults           (per-instance)
3. config/claude-agent.php values          (application-wide)
4. Claude Code CLI built-in defaults       (lowest)
```

```php
// Priority in action
$agent = ClaudeAgent::make()
    ->withOptions(['model' => 'claude-sonnet-4-20250514'])   // level 2
    ->query('Hello', model: 'claude-opus-4-20250514');        // level 1 wins
```

## Environment-Specific Configuration

Use Laravel's `.env` files to vary settings across environments without changing code.

```dotenv
# .env.production
CLAUDE_AGENT_PERMISSION_MODE=dontAsk
CLAUDE_AGENT_MAX_BUDGET_USD=1.00
CLAUDE_AGENT_TIMEOUT=120
CLAUDE_AGENT_MAX_TURNS=15

# .env.testing
CLAUDE_AGENT_PERMISSION_MODE=bypassPermissions
CLAUDE_AGENT_MAX_BUDGET_USD=0.05
CLAUDE_AGENT_TIMEOUT=30
CLAUDE_AGENT_MAX_TURNS=3
```

For config values that cannot be expressed as scalars (like `allowed_tools`), use `config/claude-agent.php` with environment checks:

```php
'allowed_tools' => app()->environment('production')
    ? ['Read', 'Grep', 'Glob']
    : ['Read', 'Grep', 'Glob', 'Bash', 'Edit'],
```

## Next Steps

- [[Fluent API]] -- Chain `withOptions()`, `withTools()`, and other fluent methods for per-query configuration
- [[Budget & Usage Tracking]] -- Monitor spend and token usage across queries
- [[Streaming]] -- Real-time output with `stream()` and `streamText()`
- [[Lifecycle Hooks]] -- Run callbacks before, during, and after each query
- [[Error Handling]] -- Handle timeouts, budget exhaustion, and CLI failures gracefully
