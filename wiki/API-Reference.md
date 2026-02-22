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

| Property | Type | Description |
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
| `$structuredOutput` | `?array` | Parsed structured output |

## ClaudeAgentOptions

See [[Options Reference]] for all fluent methods.