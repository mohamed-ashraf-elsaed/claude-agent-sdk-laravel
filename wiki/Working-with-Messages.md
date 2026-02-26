# Working with Messages

> The SDK parses Claude Code's streaming JSON output into a hierarchy of typed PHP objects, giving you compile-time safety and IDE autocompletion for every field the CLI emits.

## Message Types

Every JSON line from the CLI is routed through `Message::fromJson()` and returned as one of five concrete classes:

| Class | `$type` | Description |
|-------|---------|-------------|
| `SystemMessage` | `system` | Session initialization and system-level events |
| `AssistantMessage` | `assistant` | Claude's responses -- text, tool use, and thinking blocks |
| `UserMessage` | `user` | Echoed user prompts |
| `ResultMessage` | `result` | Final result with cost, duration, and token metrics |
| `GenericMessage` | varies | Catch-all for unrecognized message types |

All classes live under `ClaudeAgentSDK\Messages` and extend the abstract `Message` base, which exposes `$type` (string) and `$raw` (the original decoded array).

## SystemMessage

A `SystemMessage` is emitted once at the start of every session. Use it to capture the session ID for [[Session-Management]] or to trigger initialization logic.

```php
use ClaudeAgentSDK\Messages\SystemMessage;

function handleSystem(SystemMessage $msg): void
{
    if ($msg->isInit()) {
        echo "Session started: {$msg->sessionId}\n";
    }

    // Access the subtype and any extra data
    echo "Subtype: {$msg->subtype}\n";
    print_r($msg->data);
}
```

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$subtype` | `string` | Event subtype (e.g. `init`) |
| `$sessionId` | `?string` | Session identifier |
| `$data` | `array` | Full event payload |
| `isInit()` | `bool` | `true` when `$subtype === 'init'` |

## AssistantMessage

`AssistantMessage` represents Claude's responses and is the message type you will interact with most. It contains an array of typed [[#Content Blocks]] and convenience methods to extract text and tool calls.

```php
use ClaudeAgentSDK\Messages\AssistantMessage;

function handleAssistant(AssistantMessage $msg): void
{
    // Concatenated text from all TextBlocks
    echo $msg->text();

    // Iterate over tool invocations
    foreach ($msg->toolUses() as $tool) {
        echo "Tool: {$tool->name}, ID: {$tool->id}\n";
        echo "Input: " . json_encode($tool->input) . "\n";
    }

    // Metadata
    echo "Model: {$msg->model}\n";
    echo "Message ID: {$msg->id}\n";
}
```

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$content` | `ContentBlock[]` | Ordered array of content blocks |
| `$id` | `?string` | Unique message identifier |
| `$model` | `?string` | Model that generated this message |
| `$usage` | `?array` | Raw token usage from the API |
| `$parentToolUseId` | `?string` | Non-null when this is a subagent response |
| `text()` | `string` | All `TextBlock` texts joined by newline |
| `toolUses()` | `ToolUseBlock[]` | Filtered list of tool-use blocks |

**Subagent detection** -- When Claude delegates work to a subagent, the response carries a `$parentToolUseId` linking it back to the originating tool call. See [[Subagents]] for details.

```php
if ($msg->parentToolUseId !== null) {
    echo "Subagent response for tool use: {$msg->parentToolUseId}\n";
}
```

## ResultMessage

The final message in every session. It carries success/error status, timing, cost, and per-model token breakdowns.

```php
use ClaudeAgentSDK\Messages\ResultMessage;

function handleResult(ResultMessage $msg): void
{
    if ($msg->isSuccess()) {
        echo "Result: {$msg->result}\n";
    } else {
        echo "Error: {$msg->result}\n";
    }

    echo "Duration: {$msg->durationMs}ms (API: {$msg->durationApiMs}ms)\n";
    echo "Turns: {$msg->numTurns}\n";
    echo "Total cost: \${$msg->totalCostUsd}\n";

    // Cache token totals across all models
    echo "Cache reads: {$msg->cacheReadTokens()} tokens\n";
    echo "Cache writes: {$msg->cacheCreationTokens()} tokens\n";
}
```

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$subtype` | `string` | `success` or `error` |
| `$result` | `?string` | Final text output or error message |
| `$sessionId` | `?string` | Session identifier |
| `$durationMs` | `int` | Wall-clock duration in milliseconds |
| `$durationApiMs` | `int` | Time spent in API calls |
| `$isError` | `bool` | `true` if the session ended with an error |
| `$numTurns` | `int` | Number of conversational turns |
| `$totalCostUsd` | `?float` | Total session cost in USD |
| `$usage` | `?array` | Raw aggregate usage data |
| `$modelUsage` | `?array` | Raw per-model usage data |
| `$structuredOutput` | `?array` | Structured output when configured (see [[Structured-Output]]) |
| `isSuccess()` | `bool` | `true` when `$subtype === 'success'` |
| `parsedModelUsage()` | `array<string, ModelUsage>` | Per-model usage as typed objects |
| `cacheReadTokens()` | `int` | Sum of cache-read tokens across all models |
| `cacheCreationTokens()` | `int` | Sum of cache-creation tokens across all models |

## UserMessage and GenericMessage

`UserMessage` echoes back user input. `GenericMessage` is the catch-all for any type the SDK does not yet have a dedicated class for.

```php
use ClaudeAgentSDK\Messages\UserMessage;
use ClaudeAgentSDK\Messages\GenericMessage;

// UserMessage
echo $userMsg->content;  // string or array
echo $userMsg->uuid;     // optional correlation ID

// GenericMessage -- inspect with $raw
echo $genericMsg->type;
print_r($genericMsg->raw);
```

## Content Blocks

Each element in `AssistantMessage->content` is a concrete subclass of the abstract `ContentBlock`. The factory method `ContentBlock::fromArray()` dispatches on the `type` field.

| Class | `type` | Key Properties |
|-------|--------|----------------|
| `TextBlock` | `text` | `$text` -- implements `__toString()` |
| `ThinkingBlock` | `thinking` | `$thinking`, `$signature` |
| `ToolUseBlock` | `tool_use` | `$id`, `$name`, `$input` (array) |
| `ToolResultBlock` | `tool_result` | `$toolUseId`, `$content` (string, array, or null), `$isError` |

```php
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ThinkingBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;
use ClaudeAgentSDK\Content\ToolResultBlock;

foreach ($assistantMessage->content as $block) {
    match (true) {
        $block instanceof TextBlock       => print($block),          // uses __toString()
        $block instanceof ThinkingBlock   => logThinking($block->thinking, $block->signature),
        $block instanceof ToolUseBlock    => dispatchTool($block->name, $block->input),
        $block instanceof ToolResultBlock => recordResult($block->toolUseId, $block->isError),
        default                           => null,
    };
}
```

> **Note:** Unknown block types are silently converted to `TextBlock` with a JSON-encoded representation, so iterating over `$content` is always safe.

## Working with ModelUsage

`ResultMessage::parsedModelUsage()` returns an associative array keyed by model name, with `ModelUsage` value objects from `ClaudeAgentSDK\Data\ModelUsage`.

```php
use ClaudeAgentSDK\Data\ModelUsage;

foreach ($resultMsg->parsedModelUsage() as $model => $usage) {
    echo "{$model}:\n";
    echo "  Input:  {$usage->inputTokens} tokens\n";
    echo "  Output: {$usage->outputTokens} tokens\n";
    echo "  Cache read:     {$usage->cacheReadInputTokens}\n";
    echo "  Cache creation: {$usage->cacheCreationInputTokens}\n";
    echo "  Total input:    {$usage->totalInputTokens()}\n";
    echo "  Cache hit rate: " . round($usage->cacheHitRate() * 100, 1) . "%\n";
    echo "  Web searches:   {$usage->webSearchRequests}\n";
    echo "  Cost: \${$usage->costUsd}\n";
    echo "  Context window: {$usage->contextWindow}\n";
}
```

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$inputTokens` | `int` | Direct (non-cached) input tokens |
| `$outputTokens` | `int` | Output tokens generated |
| `$cacheReadInputTokens` | `int` | Tokens served from cache |
| `$cacheCreationInputTokens` | `int` | Tokens written to cache |
| `$webSearchRequests` | `int` | Number of web search tool invocations |
| `$costUsd` | `float` | Cost attributed to this model |
| `$contextWindow` | `int` | Context window size used |
| `totalInputTokens()` | `int` | Sum of input + cache-read + cache-creation tokens |
| `cacheHitRate()` | `float` | Ratio of cache-read to total input (0.0 -- 1.0) |

> `ModelUsage::fromArray()` accepts both camelCase (`inputTokens`) and snake_case (`input_tokens`) keys, so it works regardless of how the CLI serializes the data.

## Filtering and Processing Patterns

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

$messages = iterator_to_array(ClaudeAgent::stream('Refactor the auth module'));

// Filter to assistant messages only
$assistantMessages = array_filter($messages, fn($m) => $m instanceof AssistantMessage);

// Extract every tool name used across the session
$toolNames = array_unique(array_merge(
    ...array_map(
        fn(AssistantMessage $m) => array_map(fn($t) => $t->name, $m->toolUses()),
        array_values($assistantMessages),
    )
));

// Build a simple audit log
$audit = array_map(fn($m) => [
    'type'      => $m->type,
    'timestamp' => microtime(true),
    'raw_keys'  => array_keys($m->raw),
], $messages);
```

## Accessing Raw JSON

Every message retains the original decoded JSON in its `$raw` property. Use it to access CLI-specific fields the SDK does not yet map to typed properties.

```php
// Inspect any unmapped field
$customField = $message->raw['some_new_field'] ?? null;

// Serialize back to JSON for logging or forwarding
$json = json_encode($message->raw, JSON_PRETTY_PRINT);
```

## Message Factory

`Message::fromJson()` is the single entry point for converting decoded JSON arrays into typed message objects. `ContentBlock::fromArray()` does the same for content blocks.

```php
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Content\ContentBlock;

// Parse a full message
$message = Message::fromJson([
    'type'    => 'assistant',
    'message' => [
        'id'      => 'msg_test_001',
        'model'   => 'claude-sonnet-4-20250514',
        'content' => [['type' => 'text', 'text' => 'Hello, world!']],
    ],
]);

// Parse a single content block
$block = ContentBlock::fromArray(['type' => 'text', 'text' => 'Test']);
```

> **Tip:** The factory methods make it easy to build message fixtures for unit tests. Construct the array by hand, pass it through the factory, and assert against typed properties. See [[Testing-Your-Integration]] for full examples.

## Next Steps

- [[Streaming]] -- Consume messages in real-time as they arrive
- [[Subagents]] -- Understand `parentToolUseId` and multi-agent workflows
- [[Structured-Output]] -- Parse `$structuredOutput` from `ResultMessage`
- [[Session-Management]] -- Reuse session IDs across interactions
- [[Error-Handling]] -- Handle error results and exceptions gracefully
- [[API-Reference]] -- Complete class and method reference
