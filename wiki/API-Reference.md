# API Reference

## ClaudeAgentManager

| Method | Returns | Description |
|--------|---------|-------------|
| `query(string $prompt, $options?)` | `QueryResult` | Run query, return complete result |
| `stream(string $prompt, $options?)` | `Generator<Message>` | Stream messages as they arrive |
| `streamCollect(string $prompt, ?callable $onMessage, $options?)` | `QueryResult` | Stream with callback, return result |
| `withOptions($options)` | `static` | Set default options (returns clone) |
| `options()` | `ClaudeAgentOptions` | Get options builder with config defaults |
| `stop()` | `void` | Send SIGINT to running process |

## QueryResult

| Method/Property | Type | Description |
|----------------|------|-------------|
| `text()` | `?string` | Final result text |
| `structured()` | `?array` | Structured output (if schema set) |
| `isSuccess()` | `bool` | Result subtype is 'success' |
| `isError()` | `bool` | Has error or no result |
| `costUsd()` | `?float` | Total cost in USD |
| `turns()` | `int` | Number of turns |
| `durationMs()` | `int` | Duration in milliseconds |
| `assistantMessages()` | `AssistantMessage[]` | All assistant messages |
| `fullText()` | `string` | Concatenated assistant text |
| `toolUses()` | `ToolUseBlock[]` | All tool uses across messages |
| `modelUsage()` | `array<string, ModelUsage>` | Per-model usage breakdown |
| `cacheReadTokens()` | `int` | Total cache-read tokens across all models |
| `cacheCreationTokens()` | `int` | Total cache-creation tokens across all models |
| `$messages` | `Message[]` | All messages |
| `$result` | `?ResultMessage` | Final result message |
| `$sessionId` | `?string` | Session ID |

## AssistantMessage

| Method/Property | Type | Description |
|----------------|------|-------------|
| `text()` | `string` | Concatenated text content |
| `toolUses()` | `ToolUseBlock[]` | Tool use blocks |
| `$content` | `ContentBlock[]` | All content blocks |
| `$id` | `?string` | Message ID |
| `$model` | `?string` | Model used |
| `$usage` | `?array` | Token usage |
| `$parentToolUseId` | `?string` | Parent tool (subagent) |

## ResultMessage

| Property/Method | Type | Description |
|----------|------|-------------|
| `$subtype` | `string` | 'success' or 'error' |
| `$result` | `?string` | Result text |
| `$sessionId` | `?string` | Session ID |
| `$isError` | `bool` | Error flag |
| `$numTurns` | `int` | Turn count |
| `$totalCostUsd` | `?float` | Total cost |
| `$durationMs` | `int` | Total duration |
| `$durationApiMs` | `int` | API-only duration |
| `$usage` | `?array` | Token usage |
| `$modelUsage` | `?array` | Raw per-model usage data |
| `$structuredOutput` | `?array` | Parsed structured output |
| `isSuccess()` | `bool` | Whether subtype is 'success' |
| `parsedModelUsage()` | `array<string, ModelUsage>` | Per-model usage as typed objects |
| `cacheReadTokens()` | `int` | Total cache-read tokens across all models |
| `cacheCreationTokens()` | `int` | Total cache-creation tokens across all models |

## ModelUsage

Value object for per-model token usage and cost metrics.

| Property/Method | Type | Description |
|----------------|------|-------------|
| `$inputTokens` | `int` | Direct input tokens |
| `$outputTokens` | `int` | Output tokens |
| `$cacheReadInputTokens` | `int` | Tokens read from cache |
| `$cacheCreationInputTokens` | `int` | Tokens used to create cache |
| `$webSearchRequests` | `int` | Web search request count |
| `$costUsd` | `float` | Cost for this model in USD |
| `$contextWindow` | `int` | Context window size |
| `totalInputTokens()` | `int` | Sum of input + cacheRead + cacheCreation |
| `cacheHitRate()` | `float` | Cache-read / total input ratio (0.0–1.0) |
| `fromArray(array)` | `static` | Parse from array (supports camelCase and snake_case) |

## HookMatcher

| Method/Property | Type | Description |
|----------------|------|-------------|
| `$matcher` | `?string` | Regex pattern to match tool names (null = all) |
| `$hooks` | `string[]` | Shell commands the CLI executes |
| `$timeout` | `?int` | Timeout in seconds per hook (default: 60) |
| `command(cmd, matcher?, timeout?)` | `static` | Create from a shell command |
| `phpScript(path, matcher?, timeout?)` | `static` | Create from a PHP script path |
| `toArray()` | `array` | Serialize for CLI |
| `jsonSerialize()` | `array` | JSON serialization (implements JsonSerializable) |

## HookEvent

Enum of available hook events:

| Case | Value | Description |
|------|-------|-------------|
| `PreToolUse` | `'PreToolUse'` | Before a tool is executed |
| `PostToolUse` | `'PostToolUse'` | After a tool completes |
| `UserPromptSubmit` | `'UserPromptSubmit'` | When user prompt is submitted |
| `Stop` | `'Stop'` | When the agent stops |
| `SubagentStop` | `'SubagentStop'` | When a subagent stops |
| `PreCompact` | `'PreCompact'` | Before context compaction |

## ClaudeAgentOptions

See [[Options Reference]] for all fluent methods.

## Exceptions

| Exception | Properties | Description |
|-----------|-----------|-------------|
| `ClaudeAgentException` | — | Base exception (extends RuntimeException) |
| `CliNotFoundException` | — | CLI binary not found |
| `ProcessException` | `$exitCode`, `$stderr` | CLI process failed |
| `JsonParseException` | `$rawLine`, `$originalError` | JSON output parse failure |