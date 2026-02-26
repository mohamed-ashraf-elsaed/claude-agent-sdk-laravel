# Architecture

> The Claude Agent SDK for Laravel wraps the Claude Code CLI as a Symfony Process subprocess, translating fluent PHP method calls into CLI arguments and streaming JSON output back into strongly-typed message objects.

## Overview

The SDK follows a layered architecture with clear separation of concerns. At the top sits the **manager**, which exposes the public API. Beneath it, a **transport** layer spawns and communicates with the Claude Code CLI. Options flow downward as CLI arguments and environment variables; responses flow upward as typed PHP objects.

```
┌─────────────────────────────────────────────────────┐
│                  Your Laravel App                    │
│          ClaudeAgent facade / DI injection           │
└────────────────────┬────────────────────────────────┘
                     │  query() / stream() / streamCollect()
                     ▼
┌─────────────────────────────────────────────────────┐
│              ClaudeAgentManager                      │
│  - Resolves & merges options (config → defaults →   │
│    per-query)                                        │
│  - Delegates execution to ProcessTransport          │
│  - Wraps messages into QueryResult                  │
└────────────────────┬────────────────────────────────┘
                     │  run() / stream()
                     ▼
┌─────────────────────────────────────────────────────┐
│              ProcessTransport                        │
│  - Builds CLI command array from ClaudeAgentOptions │
│  - Spawns Symfony Process (subprocess)              │
│  - Reads stdout line-by-line (streaming JSON)       │
│  - Parses each line via Message::fromJson()         │
└────────────────────┬────────────────────────────────┘
                     │  claude --output-format stream-json --verbose --print "prompt"
                     ▼
┌─────────────────────────────────────────────────────┐
│              Claude Code CLI (subprocess)            │
│  - Executes the agent loop                          │
│  - Emits one JSON object per line to stdout         │
└─────────────────────────────────────────────────────┘
```

## Service Container Wiring

The `ClaudeAgentServiceProvider` registers the manager as a singleton, seeded with values from `config/claude-agent.php`:

```php
use ClaudeAgentSDK\ClaudeAgentManager;

// Registered as a singleton — one manager instance per request lifecycle
$this->app->singleton(ClaudeAgentManager::class, function ($app) {
    return new ClaudeAgentManager($app['config']['claude-agent']);
});

$this->app->alias(ClaudeAgentManager::class, 'claude-agent');
```

The `ClaudeAgent` facade proxies to this singleton, providing a static API (`ClaudeAgent::query(...)`, `ClaudeAgent::stream(...)`, etc.).

## Options Resolution & Merging

`ClaudeAgentOptions` is a fluent builder that accumulates settings and converts them into two outputs: CLI arguments (`toCliArgs()`) and environment variables (`toEnv()`).

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->model('claude-sonnet-4-20250514')
    ->tools(['Read', 'Grep', 'Glob'])
    ->permission('acceptEdits')
    ->maxTurns(5)
    ->maxBudgetUsd(2.00)
    ->systemPrompt('You are a code reviewer.')
    ->cwd(base_path());
```

When a query executes, the manager resolves the final options by merging three layers in priority order:

| Priority | Source | Example |
|----------|--------|---------|
| 1 (highest) | Per-query options passed to `query()` / `stream()` | `->query('...', $opts)` |
| 2 | Default options set via `withOptions()` | `->withOptions($opts)->query('...')` |
| 3 (lowest) | Config file values (`config/claude-agent.php`) | `'model' => 'claude-sonnet-4-20250514'` |

> **Tip:** Use `withOptions()` to set shared defaults (e.g., tool permissions) across multiple queries, then override only what differs per call.

## Transport Layer

`ProcessTransport` is the bridge between PHP and the CLI. It performs three jobs:

1. **Command assembly** -- Combines the CLI path, options from `toCliArgs()`, and the prompt into an argument array. Every invocation includes `--output-format stream-json`, `--verbose`, and `--print`.
2. **Process execution** -- Spawns the CLI via `Symfony\Component\Process\Process`, passing environment variables (API keys, provider flags) from `toEnv()`.
3. **Output parsing** -- Reads stdout line-by-line. Each line is decoded from JSON and routed through `Message::fromJson()`. Non-JSON lines (CLI startup text, progress indicators) are silently discarded.

> **Note:** The transport sets no timeout by default. Configure `process_timeout` in your config file to guard against runaway queries in production.

## Message Type System

The CLI emits one JSON object per line. The `Message::fromJson()` factory inspects the `type` field and dispatches to the appropriate class:

| `type` value | PHP class | Purpose |
|--------------|-----------|---------|
| `system` | `SystemMessage` | Session init, session ID |
| `assistant` | `AssistantMessage` | Model output with content blocks |
| `user` | `UserMessage` | Tool results fed back to the model |
| `result` | `ResultMessage` | Final summary: cost, turns, duration, session ID |
| _(other)_ | `GenericMessage` | Catch-all for unknown types |

Each `AssistantMessage` contains an array of **content blocks**, parsed by `ContentBlock::fromArray()`:

| Block type | PHP class | Contains |
|------------|-----------|----------|
| `text` | `TextBlock` | The model's written output |
| `thinking` | `ThinkingBlock` | Extended thinking content and signature |
| `tool_use` | `ToolUseBlock` | Tool name, ID, and input payload |
| `tool_result` | `ToolResultBlock` | Tool execution result or error |

## QueryResult Convenience Layer

After all messages are collected (either synchronously via `run()` or after streaming completes), they are wrapped in a `QueryResult` object:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Analyze the User model');

$result->text();              // Final text from ResultMessage
$result->structured();        // Decoded JSON when using outputFormat()
$result->isSuccess();         // true if no error
$result->costUsd();           // Total cost in USD
$result->turns();             // Number of conversation turns
$result->durationMs();        // Execution time in milliseconds
$result->sessionId;           // Session ID for resumption
$result->assistantMessages(); // All AssistantMessage instances
$result->fullText();          // Concatenated text from every assistant turn
$result->toolUses();          // All ToolUseBlock instances across turns
$result->modelUsage();        // Per-model token usage breakdown
$result->cacheReadTokens();   // Total prompt-cache read tokens
$result->cacheCreationTokens(); // Total prompt-cache creation tokens
```

> **Warning:** `text()` returns only the final `ResultMessage` text. If you need the full multi-turn conversation output, use `fullText()` instead.

## Data Flow: End-to-End

The complete lifecycle of a single `query()` call:

```
ClaudeAgent::query('Find bugs in app/Models/')
       │
       ▼
ClaudeAgentManager::resolveOptions()
  ── merge config → defaults → per-query
       │
       ▼
ProcessTransport::run(prompt, options)
  ── options.toCliArgs()  → ['--model', 'claude-sonnet-4-20250514', '--max-turns', '5', ...]
  ── options.toEnv()      → ['ANTHROPIC_API_KEY' => 'sk-...']
       │
       ▼
Symfony Process::run()
  ── spawns: claude --output-format stream-json --verbose --print "Find bugs in app/Models/"
       │
       ▼
stdout (line-by-line JSON):
  {"type":"system","session_id":"abc-123", ...}
  {"type":"assistant","content":[{"type":"text","text":"I found..."}], ...}
  {"type":"result","result":"Analysis complete.","total_cost_usd":0.042, ...}
       │
       ▼
ProcessTransport::parseOutput()
  ── json_decode each line → Message::fromJson() → typed Message objects
       │
       ▼
new QueryResult(messages)
  ── extracts ResultMessage, sessionId
  ── exposes text(), costUsd(), turns(), etc.
```

## Streaming vs. Synchronous Execution

The SDK offers three execution modes, all sharing the same options resolution and transport pipeline:

| Method | Behavior | Return type |
|--------|----------|-------------|
| `query()` | Blocks until the CLI exits, then parses all output at once | `QueryResult` |
| `stream()` | Yields each `Message` as it arrives via a PHP `Generator` | `Generator<Message>` |
| `streamCollect()` | Streams messages through a callback, then returns the collected result | `QueryResult` |

> **Tip:** For long-running agents, prefer `streamCollect()` so you can broadcast progress to a WebSocket or update a job's status while still receiving a complete `QueryResult` at the end.

## Next Steps

- [[Basic Usage]] -- Get started with your first query
- [[Options Reference]] -- Full list of fluent builder methods
- [[Streaming]] -- Real-time message processing and broadcasting
- [[Working with Messages]] -- Inspect content blocks, tool calls, and thinking
- [[Configuration]] -- Config file reference and override priority
- [[Error Handling]] -- Exception types and recovery strategies
- [[Session Management]] -- Resume and fork conversations
