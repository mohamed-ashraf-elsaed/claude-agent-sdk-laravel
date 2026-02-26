# API Reference

> Complete public API surface for the Claude Agent SDK for Laravel. Every class, method, property, and exception is documented here with full signatures and types. For fluent option-builder details, see [[Options Reference]]. For usage patterns, see [[Getting Started]].

---

## ClaudeAgentManager

Primary entry point for all interactions with the Claude Code CLI. Instantiated automatically by the Laravel service container and accessible via the `ClaudeAgent` [[Facade]].

| Method | Returns | Description |
|--------|---------|-------------|
| `__construct(array $config = [])` | — | Create a manager instance. Config keys are read from `config/claude-agent.php` (model, permission_mode, cwd, allowed_tools, max_turns, max_budget_usd, max_thinking_tokens). |
| `query(string $prompt, ClaudeAgentOptions\|array\|null $options = null)` | `QueryResult` | Execute a prompt synchronously and return the complete result after the CLI process exits. |
| `stream(string $prompt, ClaudeAgentOptions\|array\|null $options = null)` | `Generator<Message>` | Execute a prompt and yield each `Message` as soon as the CLI emits it. Ideal for real-time UIs. |
| `streamCollect(string $prompt, ?callable $onMessage = null, ClaudeAgentOptions\|array\|null $options = null)` | `QueryResult` | Stream messages through an optional callback, then return the aggregated `QueryResult`. |
| `withOptions(ClaudeAgentOptions\|array $options)` | `static` | Return a **cloned** manager with default options applied. Original instance is unchanged. |
| `options()` | `ClaudeAgentOptions` | Create a new `ClaudeAgentOptions` builder pre-filled with values from the resolved config. |
| `stop()` | `void` | Send `SIGINT` to the running CLI process, gracefully terminating the current query. |

---

## QueryResult

Immutable value object returned by `query()` and `streamCollect()`. Wraps the full message stream and exposes convenience accessors for the final result, cost, and usage metrics.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$messages` | `readonly Message[]` | Every message emitted during the query (system, user, assistant, result). |
| `$result` | `readonly ?ResultMessage` | The final `result`-type message, or `null` if the stream ended without one. |
| `$sessionId` | `readonly ?string` | Session identifier extracted from the first `SystemMessage` or `ResultMessage`. |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `text()` | `?string` | Final result text from the `ResultMessage`. |
| `structured()` | `?array` | Parsed structured output when an `outputFormat` JSON schema was provided. |
| `isSuccess()` | `bool` | `true` when the result subtype is `'success'`. |
| `isError()` | `bool` | `true` when an error occurred or no result message exists. |
| `costUsd()` | `?float` | Total cost of the query in USD. |
| `turns()` | `int` | Number of conversational turns consumed. |
| `durationMs()` | `int` | Wall-clock duration of the entire query in milliseconds. |
| `assistantMessages()` | `AssistantMessage[]` | All assistant-type messages filtered from the stream. |
| `fullText()` | `string` | Concatenated text content from every assistant message, joined by newlines. |
| `toolUses()` | `ToolUseBlock[]` | Every tool-use block across all assistant messages. |
| `modelUsage()` | `array<string, ModelUsage>` | Per-model token and cost breakdown as typed `ModelUsage` objects. |
| `cacheReadTokens()` | `int` | Sum of cache-read input tokens across all models. |
| `cacheCreationTokens()` | `int` | Sum of cache-creation input tokens across all models. |

---

## ClaudeAgentOptions

Fluent builder for every CLI flag and environment variable. All setter methods return `static` for chaining. See [[Options Reference]] for in-depth usage examples.

### Static Constructors

| Method | Returns | Description |
|--------|---------|-------------|
| `make()` | `static` | Create a blank options instance. |
| `fromArray(array $data)` | `static` | Hydrate from an associative array. Supports both `camelCase` and `snake_case` keys. |

### Fluent Setters (all return `static`)

| Category | Method | Description |
|----------|--------|-------------|
| **Tools** | `tools(array $tools)` | Set the list of allowed tool names. |
| | `disallow(array $tools)` | Set tools that are explicitly disallowed. |
| **Model** | `model(string $model)` | Primary model identifier (e.g. `'claude-sonnet-4-20250514'`). |
| | `fallbackModel(string $model)` | Model to use when the primary is unavailable. |
| | `betas(array $betas)` | Enable beta feature flags. |
| **Prompts** | `systemPrompt(string\|array $prompt)` | Set a custom system prompt (string) or structured prompt (array). |
| | `useClaudeCodePrompt(?string $append = null)` | Use the built-in Claude Code preset prompt, optionally appending extra instructions. |
| **Permissions** | `permission(string $mode)` | Permission mode: `'default'`, `'plan'`, or `'bypassPermissions'`. |
| **Limits** | `maxTurns(int $turns)` | Maximum number of agent turns before stopping. |
| | `maxBudgetUsd(float $amount)` | Spend cap in USD for the entire query. |
| | `maxThinkingTokens(int $tokens)` | Maximum tokens the model may use for extended thinking. |
| **Session** | `resume(string $sessionId, bool $fork = false)` | Resume (or fork) an existing session by ID. |
| | `continueConversation()` | Continue the most recent conversation. |
| **Environment** | `cwd(string $path)` | Working directory for the CLI process. |
| | `env(string $key, string $value)` | Set an environment variable passed to the CLI. |
| | `user(string $userId)` | User identifier for multi-tenant isolation. |
| | `addDir(string $path)` | Add an additional directory the agent may access. |
| | `settings(string $path)` | Path to a custom settings JSON file. |
| | `settingSources(array $sources)` | Override the list of setting-source paths. |
| **Output** | `outputFormat(array $schema)` | Require structured JSON output conforming to the given JSON Schema. |
| | `includePartialMessages(bool $include = true)` | Include partial/streaming messages in the output. |
| **Agents** | `agent(string $name, AgentDefinition\|array $definition)` | Register a named sub-agent definition. |
| **MCP** | `mcpServer(string $name, McpServerConfig\|array $config)` | Register a named MCP server (stdio or SSE). |
| **Hooks** | `hook(HookEvent $event, HookMatcher $matcher)` | Attach a hook matcher to any lifecycle event. |
| | `preToolUse(string $command, ?string $matcher = null, ?int $timeout = null)` | Shorthand: attach a shell command as a `PreToolUse` hook. |
| | `postToolUse(string $command, ?string $matcher = null, ?int $timeout = null)` | Shorthand: attach a shell command as a `PostToolUse` hook. |
| **Advanced** | `sandbox(array $settings)` | Configure sandboxing settings for the CLI process. |
| | `plugin(string $path)` | Register a local plugin by filesystem path. |
| | `enableFileCheckpointing(bool $enable = true)` | Enable or disable file checkpointing. |
| | `extraArg(string $flag, ?string $value = null)` | Pass an arbitrary CLI flag not covered by the fluent API. |

### Serialization

| Method | Returns | Description |
|--------|---------|-------------|
| `toCliArgs()` | `array` | Build the full CLI argument array (includes `--output-format stream-json`). |
| `toEnv(array $defaults = [])` | `array` | Merge user-defined environment variables with the provided defaults. |

---

## Messages

All message types extend the abstract `Message` base class. Use the static factory `Message::fromJson()` to parse raw CLI output into the correct subclass.

### Message (abstract base)

| Member | Type | Description |
|--------|------|-------------|
| `$type` | `readonly string` | Discriminator: `'user'`, `'assistant'`, `'system'`, `'result'`, or other. |
| `$raw` | `readonly array` | The original unmodified JSON array from the CLI. |
| `fromJson(array $data)` | `Message` | Factory method. Routes to `UserMessage`, `AssistantMessage`, `SystemMessage`, `ResultMessage`, or `GenericMessage` based on the `type` field. |

### AssistantMessage

Represents a model response containing text, thinking, and/or tool-use blocks.

| Member | Type | Description |
|--------|------|-------------|
| `parse(array $data)` | `static` | Parse from raw JSON. Handles both wrapped (`message` key) and flat formats. |
| `text()` | `string` | Concatenated text from all `TextBlock` entries in `$content`. |
| `toolUses()` | `ToolUseBlock[]` | All `ToolUseBlock` entries from `$content`. |
| `$content` | `readonly ContentBlock[]` | Ordered array of content blocks (text, thinking, tool_use). |
| `$id` | `readonly ?string` | Unique message identifier. |
| `$model` | `readonly ?string` | Model that generated this response. |
| `$usage` | `readonly ?array` | Raw token-usage data for this single message. |
| `$parentToolUseId` | `readonly ?string` | If this message was produced by a sub-agent, the parent tool-use ID. |

### ResultMessage

Final message emitted when the CLI process completes. Contains aggregate metrics.

| Member | Type | Description |
|--------|------|-------------|
| `parse(array $data)` | `static` | Parse from raw JSON. |
| `isSuccess()` | `bool` | `true` when `$subtype === 'success'`. |
| `parsedModelUsage()` | `array<string, ModelUsage>` | Per-model usage as typed `ModelUsage` objects. |
| `cacheReadTokens()` | `int` | Sum of cache-read tokens across all models. |
| `cacheCreationTokens()` | `int` | Sum of cache-creation tokens across all models. |
| `$subtype` | `readonly string` | `'success'` or `'error'`. |
| `$result` | `readonly ?string` | The final text result. |
| `$sessionId` | `readonly ?string` | Session identifier for resumption. |
| `$durationMs` | `readonly int` | Total wall-clock duration in milliseconds. |
| `$durationApiMs` | `readonly int` | Time spent in API calls only, in milliseconds. |
| `$isError` | `readonly bool` | Error flag. |
| `$numTurns` | `readonly int` | Number of conversational turns. |
| `$totalCostUsd` | `readonly ?float` | Total cost in USD. |
| `$usage` | `readonly ?array` | Raw aggregate token-usage array. |
| `$modelUsage` | `readonly ?array` | Raw per-model usage data (use `parsedModelUsage()` for typed access). |
| `$structuredOutput` | `readonly ?array` | Parsed structured output when a JSON schema was provided. |

### SystemMessage

Emitted during session initialization and other system-level events.

| Member | Type | Description |
|--------|------|-------------|
| `parse(array $data)` | `static` | Parse from raw JSON. |
| `isInit()` | `bool` | `true` when `$subtype === 'init'`. |
| `$subtype` | `readonly string` | Event subtype (e.g. `'init'`). |
| `$sessionId` | `readonly ?string` | Session identifier. |
| `$data` | `readonly array` | Full event payload. |

### UserMessage

Represents a user prompt as echoed back by the CLI.

| Member | Type | Description |
|--------|------|-------------|
| `parse(array $data)` | `static` | Parse from raw JSON. |
| `$content` | `readonly string\|array` | The prompt content. |
| `$uuid` | `readonly ?string` | Unique message identifier. |

### GenericMessage

Catch-all for any message type not explicitly handled.

| Member | Type | Description |
|--------|------|-------------|
| `$type` | `readonly string` | The raw type string. |
| `$raw` | `readonly array` | The complete unparsed JSON payload. |

---

## Content Blocks

Content blocks appear inside `AssistantMessage::$content`. Use the static factory `ContentBlock::fromArray()` to parse raw arrays.

### ContentBlock (abstract base)

| Member | Type | Description |
|--------|------|-------------|
| `$type` | `readonly string` | Block type discriminator. |
| `fromArray(array $data)` | `ContentBlock` | Factory: routes to `TextBlock`, `ThinkingBlock`, `ToolUseBlock`, or `ToolResultBlock`. |

### TextBlock

| Member | Type | Description |
|--------|------|-------------|
| `$text` | `readonly string` | The text content. |
| `__toString()` | `string` | Returns `$text`, enabling direct string interpolation. |

### ThinkingBlock

| Member | Type | Description |
|--------|------|-------------|
| `$thinking` | `readonly string` | The model's extended-thinking content. |
| `$signature` | `readonly string` | Cryptographic signature for the thinking block. |

### ToolUseBlock

| Member | Type | Description |
|--------|------|-------------|
| `$id` | `readonly string` | Unique tool-use invocation identifier. |
| `$name` | `readonly string` | Tool name (e.g. `'Bash'`, `'Read'`, `'Edit'`). |
| `$input` | `readonly array` | Input parameters passed to the tool. |

### ToolResultBlock

| Member | Type | Description |
|--------|------|-------------|
| `$toolUseId` | `readonly string` | The `$id` of the `ToolUseBlock` this result corresponds to. |
| `$content` | `readonly string\|array\|null` | Tool execution output. |
| `$isError` | `readonly bool` | `true` if the tool invocation failed. |

---

## Data Objects

### ModelUsage

Immutable value object representing token consumption and cost for a single model within a query.

| Member | Type | Description |
|--------|------|-------------|
| `fromArray(array $data)` | `static` | Parse from an array. Accepts both `camelCase` and `snake_case` keys. |
| `totalInputTokens()` | `int` | `inputTokens + cacheReadInputTokens + cacheCreationInputTokens`. |
| `cacheHitRate()` | `float` | Ratio of `cacheReadInputTokens` to `totalInputTokens()` (0.0--1.0). Returns 0.0 when total is zero. |
| `$inputTokens` | `readonly int` | Direct (non-cached) input tokens. |
| `$outputTokens` | `readonly int` | Output tokens generated. |
| `$cacheReadInputTokens` | `readonly int` | Input tokens served from cache. |
| `$cacheCreationInputTokens` | `readonly int` | Input tokens used to populate the cache. |
| `$webSearchRequests` | `readonly int` | Number of web-search requests made. |
| `$costUsd` | `readonly float` | Cost attributed to this model in USD. |
| `$contextWindow` | `readonly int` | Context window size used by this model. |

### AgentDefinition

Defines a named sub-agent that Claude Code can delegate to.

| Member | Type | Description |
|--------|------|-------------|
| `fromArray(array $data)` | `static` | Parse from an associative array. |
| `toArray()` | `array` | Serialize to array (null values excluded). |
| `$description` | `readonly string` | Human-readable description of the agent's purpose. |
| `$prompt` | `readonly string` | System prompt for the sub-agent. |
| `$tools` | `readonly ?array` | Tool allowlist for the sub-agent, or `null` for defaults. |
| `$model` | `readonly ?string` | Model override, or `null` to inherit from parent. |

### McpServerConfig

Configuration for a Model Context Protocol server. Implements `JsonSerializable`.

| Member | Type | Description |
|--------|------|-------------|
| `stdio(string $command, array $args = [], array $env = [])` | `static` | Create a stdio-transport MCP server config. |
| `sse(string $url, array $headers = [])` | `static` | Create an SSE-transport MCP server config. |
| `toArray()` | `array` | Serialize to the array format expected by the CLI. |
| `jsonSerialize()` | `array` | JSON serialization (delegates to `toArray()`). |

---

## Hooks

### HookEvent (enum)

Backed string enum defining the lifecycle events that accept hooks. See [[Hooks]] for patterns.

| Case | Value | Description |
|------|-------|-------------|
| `PreToolUse` | `'PreToolUse'` | Fires before a tool is executed. |
| `PostToolUse` | `'PostToolUse'` | Fires after a tool completes. |
| `UserPromptSubmit` | `'UserPromptSubmit'` | Fires when the user prompt is submitted. |
| `Stop` | `'Stop'` | Fires when the agent stops. |
| `SubagentStop` | `'SubagentStop'` | Fires when a sub-agent stops. |
| `PreCompact` | `'PreCompact'` | Fires before context compaction. |

### HookMatcher

Defines which tools a hook matches and what command to run. Implements `JsonSerializable`.

| Member | Type | Description |
|--------|------|-------------|
| `command(string $command, ?string $matcher = null, ?int $timeout = null)` | `static` | Create a matcher that runs a shell command. |
| `phpScript(string $scriptPath, ?string $matcher = null, ?int $timeout = null)` | `static` | Create a matcher that executes a PHP script via `php /path/to/script.php`. |
| `toArray()` | `array` | Serialize for CLI consumption. |
| `jsonSerialize()` | `array` | JSON serialization (delegates to `toArray()`). |
| `$matcher` | `readonly ?string` | Regex pattern to match tool names. `null` matches all tools. |
| `$hooks` | `readonly string[]` | Shell commands the CLI executes when the hook fires. |
| `$timeout` | `readonly ?int` | Per-hook timeout in seconds. Defaults to 60 when `null`. |

---

## Exceptions

All SDK exceptions extend `ClaudeAgentException`, which itself extends PHP's `RuntimeException`. Catch the base class for blanket error handling, or catch specific subclasses for targeted recovery.

| Exception | Extends | Properties | When Thrown |
|-----------|---------|------------|------------|
| `ClaudeAgentException` | `RuntimeException` | -- | Base class; not thrown directly. |
| `CliNotFoundException` | `ClaudeAgentException` | -- | The `claude` binary is not found at the configured path. Message includes install instructions: `npm install -g @anthropic-ai/claude-code`. |
| `ProcessException` | `ClaudeAgentException` | `readonly ?int $exitCode`, `readonly ?string $stderr` | The CLI process exited with a non-zero status. |
| `JsonParseException` | `ClaudeAgentException` | `readonly string $rawLine`, `readonly ?Throwable $originalError` | A line of CLI output could not be parsed as valid JSON. |

---

## Facade

The `ClaudeAgent` facade provides static access to the `ClaudeAgentManager` singleton registered in the Laravel container under the `'claude-agent'` key.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

// All ClaudeAgentManager methods are available statically:
$result = ClaudeAgent::query('Summarize this codebase');
$stream = ClaudeAgent::stream('Explain the architecture');
$agent  = ClaudeAgent::withOptions(['model' => 'claude-sonnet-4-20250514']);
```

The facade proxies every public method on `ClaudeAgentManager`: `query()`, `stream()`, `streamCollect()`, `withOptions()`, `options()`, and `stop()`.
