# Options Reference

> Complete reference for the `ClaudeAgentOptions` fluent builder. Every method returns `static` for chaining, letting you compose complex configurations in a single expression.

## Overview

`ClaudeAgentOptions` is the primary way to configure per-query behaviour. You create an instance with `make()`, chain methods to set flags, and pass the result to `query()` or `stream()`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->model('claude-sonnet-4-20250514')
    ->tools(['Read', 'Grep', 'Glob'])
    ->permission('dontAsk')
    ->maxTurns(10)
    ->maxBudgetUsd(1.50)
    ->cwd(base_path());

$result = ClaudeAgent::query('Analyze the codebase structure', $options);
```

---

## Tool Control

Configure which tools the agent is allowed (or forbidden) to use.

- **`tools(array $tools): static`** -- Set the list of allowed tool names. Maps to CLI flag `--allowed-tools`.
- **`disallow(array $tools): static`** -- Set the list of disallowed tool names. Maps to CLI flag `--disallowed-tools`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Bash'])
    ->disallow(['Write', 'Edit']);
```

### Available Tools

| Tool | Description |
|------|-------------|
| `Read` | Read file contents |
| `Write` | Write or create files |
| `Edit` | Edit existing files |
| `Bash` | Run shell commands |
| `Grep` | Search file contents |
| `Glob` | Find files by pattern |
| `WebFetch` | Fetch web URLs |
| `WebSearch` | Search the web |
| `Task` | Delegate to subagents (required for [[Subagents]]) |

MCP tools use the format `mcp__servername__toolname`. See [[MCP Servers]] for details.

---

## Model and Thinking

Control which model is used and how it reasons.

- **`model(string $model): static`** -- Set the model identifier. Maps to CLI flag `--model`.
- **`fallbackModel(string $model): static`** -- Fallback model if the primary is unavailable. Maps to CLI flag `--fallback-model`.
- **`maxThinkingTokens(int $tokens): static`** -- Token budget for extended thinking. Maps to CLI flag `--max-thinking-tokens`.
- **`betas(array $betas): static`** -- Enable beta features by name. Maps to CLI flag `--beta`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->model('claude-opus-4-20250514')
    ->fallbackModel('claude-sonnet-4-20250514')
    ->maxThinkingTokens(16000)
    ->betas(['interleaved-thinking']);
```

> **Tip:** Leave `model` unset to use the CLI's built-in default. Use `fallbackModel` to gracefully degrade when a model is at capacity.

---

## Resource Limits

Protect against runaway conversations and unexpected costs.

- **`maxTurns(int $turns): static`** -- Cap on conversation turns per query. Maps to CLI flag `--max-turns`.
- **`maxBudgetUsd(float $amount): static`** -- Hard spend ceiling in USD per query. Maps to CLI flag `--max-budget-usd`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->maxTurns(15)
    ->maxBudgetUsd(2.00);
```

> **Warning:** In production queue workers, always set both limits. An unbounded agent can consume significant resources before the process timeout kills it.

---

## Permissions

Control how the CLI handles tool-use confirmations.

- **`permission(string $mode): static`** -- Set the permission mode. Maps to CLI flag `--permission-mode`.

| Mode | Behaviour |
|------|-----------|
| `default` | CLI prompts for confirmation on each tool use |
| `acceptEdits` | Auto-approves file edits; prompts for everything else |
| `dontAsk` | Auto-approves allowed tools; rejects the rest |
| `bypassPermissions` | Skips all permission checks |

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->permission('dontAsk')
    ->tools(['Read', 'Grep', 'Glob']);
```

> **Warning:** `bypassPermissions` disables every safety gate. Only use it inside disposable containers or CI runners with no access to production data.

---

## System Prompts

Set custom instructions that shape the agent's behaviour. See [[System Prompts]] for detailed guidance.

- **`systemPrompt(string|array $prompt): static`** -- Set a custom system prompt (string or array). Maps to CLI flag `--system-prompt`.
- **`useClaudeCodePrompt(?string $append = null): static`** -- Use Claude Code's built-in preset. Sends `{"type":"preset","preset":"claude_code"}` as JSON. When `$append` is provided, adds an `append` key.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Custom string prompt
$options = ClaudeAgentOptions::make()
    ->systemPrompt('You are a Laravel expert. Follow PSR-12 coding standards.');

// Claude Code preset with additions
$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('Also follow PSR-12 and write tests for every change.');
```

---

## Session Control

Resume or continue previous conversations.

- **`resume(string $sessionId, bool $fork = false): static`** -- Resume a session by ID. When `$fork` is `true`, creates a branch of the conversation. Maps to CLI flags `--resume` + `--fork-session`.
- **`continueConversation(): static`** -- Continue the most recent conversation. Maps to CLI flag `--continue`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Resume an existing session
$options = ClaudeAgentOptions::make()
    ->resume('sess_abc123');

// Fork from a session (creates a branch)
$options = ClaudeAgentOptions::make()
    ->resume('sess_abc123', fork: true);

// Continue the last conversation
$options = ClaudeAgentOptions::make()
    ->continueConversation();
```

See [[Session Management]] for more patterns.

---

## Output

Control the format and granularity of the agent's response.

- **`outputFormat(array $schema): static`** -- Require structured JSON output matching a JSON Schema. Maps to CLI flag `--output-format-json-schema`.
- **`includePartialMessages(bool $include = true): static`** -- Include partial streaming messages in the result. Maps to CLI flag `--include-partial-messages`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'issues'  => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['summary', 'issues'],
    ])
    ->includePartialMessages();
```

See [[Structured Output]] for full examples.

---

## MCP Servers

Connect external tool servers via the Model Context Protocol.

- **`mcpServer(string $name, McpServerConfig|array $config): static`** -- Register an MCP server by name. Maps to CLI flag `--mcp-servers`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['@modelcontextprotocol/server-database'],
        env: ['DB_URL' => config('database.url')],
    ))
    ->tools(['mcp__database__query', 'Read']);
```

See [[MCP Servers]] for stdio and SSE transport examples.

---

## Subagents

Define named subagents that the main agent can delegate to via the `Task` tool.

- **`agent(string $name, AgentDefinition|array $definition): static`** -- Register a subagent definition. Maps to CLI flag `--agents`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Agents\AgentDefinition;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Task'])
    ->agent('reviewer', AgentDefinition::fromArray([
        'model' => 'claude-sonnet-4-20250514',
        'allowed_tools' => ['Read', 'Grep'],
        'system_prompt' => 'You review code for security issues.',
    ]));
```

See [[Subagents]] for multi-agent patterns.

---

## Hooks

Run shell commands before or after the agent uses tools.

- **`hook(HookEvent $event, HookMatcher $matcher): static`** -- Register a hook for any supported event. Maps to CLI flag `--hooks`.
- **`preToolUse(string $command, ?string $matcher = null, ?int $timeout = null): static`** -- Shorthand to add a pre-tool-use hook.
- **`postToolUse(string $command, ?string $matcher = null, ?int $timeout = null): static`** -- Shorthand to add a post-tool-use hook.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;

$options = ClaudeAgentOptions::make()
    ->preToolUse('php artisan lint:check', '/Edit|Write/', 30)
    ->postToolUse('php artisan test:affected')
    ->hook(HookEvent::Stop, HookMatcher::command('php /hooks/cleanup.php'));
```

See [[Hooks]] for advanced hook patterns including multiple matchers and `HookMatcher::phpScript()`.

---

## Paths and Directories

Control where the agent operates and reads configuration.

- **`cwd(string $path): static`** -- Set the working directory for the CLI process. Passed as the process `cwd`.
- **`addDir(string $path): static`** -- Add an extra directory the agent can access. Stackable -- call multiple times. Maps to CLI flag `--add-dir`.
- **`settings(string $path): static`** -- Path to a settings JSON file. Maps to CLI flag `--settings`.
- **`settingSources(array $sources): static`** -- Load settings from specific sources. Maps to CLI flag `--setting-source`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->cwd(base_path())
    ->addDir(base_path('packages/shared-lib'))
    ->addDir(storage_path('agent-workspace'))
    ->settings(base_path('.claude/settings.json'));
```

> **Tip:** Use `addDir` to give the agent access to monorepo packages or shared libraries without changing the working directory.

---

## Environment and Process

Set environment variables and identify users.

- **`env(string $key, string $value): static`** -- Set an environment variable for the CLI process.
- **`user(string $userId): static`** -- Set a user identifier for tracking and auditing. Maps to CLI flag `--user`.
- **`extraArg(string $flag, ?string $value = null): static`** -- Pass any arbitrary CLI flag not covered by other methods.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->env('NODE_ENV', 'production')
    ->env('CUSTOM_API_KEY', config('services.custom.key'))
    ->user(auth()->id())
    ->extraArg('verbose');
```

---

## Advanced

Additional options for sandboxing, plugins, and checkpointing.

- **`sandbox(array $settings): static`** -- Configure sandbox restrictions. Maps to CLI flag `--sandbox`.
- **`plugin(string $path): static`** -- Register a local plugin by file path. Maps to CLI flag `--plugins`.
- **`enableFileCheckpointing(bool $enable = true): static`** -- Enable file checkpointing for rollback support. Maps to CLI flag `--enable-file-checkpointing`.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->sandbox(['network' => false, 'filesystem' => 'read-only'])
    ->plugin(base_path('claude-plugins/custom-linter.js'))
    ->enableFileCheckpointing();
```

---

## Creation Methods

Two static constructors for creating option instances.

- **`static make(): static`** -- Create a new empty options instance.
- **`static fromArray(array $data): static`** -- Create from an associative array. Keys are automatically converted from `snake_case` to `camelCase` (e.g. `max_turns` becomes `maxTurns`).

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Fluent builder
$options = ClaudeAgentOptions::make()
    ->tools(['Read'])
    ->maxTurns(5);

// From array (useful for config files or request payloads)
$options = ClaudeAgentOptions::fromArray([
    'allowed_tools'  => ['Read', 'Grep'],
    'max_turns'      => 10,
    'max_budget_usd' => 2.00,
    'permission_mode' => 'dontAsk',
]);
```

---

## Serialization Methods

Convert options into formats consumed by the CLI transport.

- **`toCliArgs(): array`** -- Build the full array of CLI arguments. Always includes `--output-format stream-json`.
- **`toEnv(array $defaults = []): array`** -- Build environment variables for the CLI process. Merges custom `env()` values with provided defaults.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->model('claude-sonnet-4-20250514')
    ->tools(['Read', 'Grep'])
    ->env('ANTHROPIC_API_KEY', 'sk-ant-...');

$args = $options->toCliArgs();
// ['--output-format', 'stream-json', '--model', 'claude-sonnet-4-20250514', '--allowed-tools', 'Read,Grep']

$env = $options->toEnv(['HOME' => '/home/app']);
// ['HOME' => '/home/app', 'ANTHROPIC_API_KEY' => 'sk-ant-...']
```

---

## Quick Reference Table

| Method | Description | CLI Flag |
|--------|-------------|----------|
| `tools(array)` | Allowed tool names | `--allowed-tools` |
| `disallow(array)` | Disallowed tool names | `--disallowed-tools` |
| `model(string)` | Model to use | `--model` |
| `fallbackModel(string)` | Fallback model if primary unavailable | `--fallback-model` |
| `maxThinkingTokens(int)` | Token budget for extended thinking | `--max-thinking-tokens` |
| `betas(array)` | Enable beta features | `--beta` |
| `maxTurns(int)` | Max conversation turns | `--max-turns` |
| `maxBudgetUsd(float)` | Spend ceiling in USD per query | `--max-budget-usd` |
| `permission(string)` | Permission mode | `--permission-mode` |
| `systemPrompt(string\|array)` | Custom system prompt | `--system-prompt` |
| `useClaudeCodePrompt(?string)` | Claude Code built-in preset | `--system-prompt` (JSON) |
| `resume(string, bool)` | Resume or fork a session | `--resume` + `--fork-session` |
| `continueConversation()` | Continue last conversation | `--continue` |
| `outputFormat(array)` | JSON schema for structured output | `--output-format-json-schema` |
| `includePartialMessages(bool)` | Include partial streaming messages | `--include-partial-messages` |
| `mcpServer(name, config)` | Add MCP server | `--mcp-servers` |
| `agent(name, definition)` | Add subagent definition | `--agents` |
| `hook(event, matcher)` | Register a hook for a CLI event | `--hooks` |
| `preToolUse(cmd, matcher?, timeout?)` | Shorthand for pre-tool-use hook | `--hooks` |
| `postToolUse(cmd, matcher?, timeout?)` | Shorthand for post-tool-use hook | `--hooks` |
| `cwd(string)` | Working directory | Process cwd |
| `addDir(string)` | Add extra directory (stackable) | `--add-dir` |
| `settings(string)` | Path to settings JSON file | `--settings` |
| `settingSources(array)` | Load settings from sources | `--setting-source` |
| `env(key, value)` | Set environment variable | Process env |
| `user(string)` | Set user ID | `--user` |
| `extraArg(flag, value?)` | Any arbitrary CLI flag | `--{flag}` |
| `sandbox(array)` | Sandbox configuration | `--sandbox` |
| `plugin(string)` | Add a local plugin | `--plugins` |
| `enableFileCheckpointing(bool)` | Enable file checkpointing | `--enable-file-checkpointing` |
| `static make()` | Create new options instance | -- |
| `static fromArray(array)` | Create from array (snake_case keys) | -- |
| `toCliArgs()` | Build CLI argument array | -- |
| `toEnv(array)` | Build environment variable array | -- |
