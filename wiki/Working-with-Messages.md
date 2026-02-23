# Working with Messages

The SDK parses Claude Code's streaming JSON output into typed message objects.

## Message Types

| Class | Type | Description |
|-------|------|-------------|
| `SystemMessage` | `system` | Session init and system events |
| `AssistantMessage` | `assistant` | Claude's responses (text + tool use) |
| `UserMessage` | `user` | User prompts |
| `ResultMessage` | `result` | Final result with cost/usage metadata |
| `GenericMessage` | varies | Catch-all for unknown message types |

## Processing Messages
```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream('Do something') as $message) {
    match (true) {
        $message instanceof SystemMessage => handleSystem($message),
        $message instanceof AssistantMessage => handleAssistant($message),
        $message instanceof ResultMessage => handleResult($message),
        default => null,
    };
}
```

## AssistantMessage
```php
function handleAssistant(AssistantMessage $msg): void
{
    // Text content
    echo $msg->text();

    // Tool calls made by Claude
    foreach ($msg->toolUses() as $toolUse) {
        echo "Tool: {$toolUse->name}\n";
        echo "Input: " . json_encode($toolUse->input) . "\n";
    }

    // Metadata
    echo $msg->model;            // Model used
    echo $msg->id;               // Message ID
    echo $msg->parentToolUseId;  // If this is a subagent response
}
```

## SystemMessage
```php
function handleSystem(SystemMessage $msg): void
{
    if ($msg->isInit()) {
        echo "Session started: {$msg->sessionId}\n";
    }
}
```

## ResultMessage
```php
function handleResult(ResultMessage $msg): void
{
    echo "Success: " . ($msg->isSuccess() ? 'yes' : 'no') . "\n";
    echo "Result: {$msg->result}\n";
    echo "Cost: \${$msg->totalCostUsd}\n";
    echo "Turns: {$msg->numTurns}\n";
    echo "Duration: {$msg->durationMs}ms\n";

    // Per-model usage breakdown
    foreach ($msg->parsedModelUsage() as $model => $usage) {
        echo "{$model}: {$usage->inputTokens} in, {$usage->outputTokens} out\n";
        echo "  Cache hit rate: " . round($usage->cacheHitRate() * 100) . "%\n";
        echo "  Cost: \${$usage->costUsd}\n";
    }
}
```

## Content Blocks

AssistantMessage content is an array of typed content blocks:

| Class | Type | Key Properties |
|-------|------|----------------|
| `TextBlock` | `text` | `$text` |
| `ThinkingBlock` | `thinking` | `$thinking`, `$signature` |
| `ToolUseBlock` | `tool_use` | `$id`, `$name`, `$input` |
| `ToolResultBlock` | `tool_result` | `$toolUseId`, `$content`, `$isError` |

```php
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ThinkingBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;

foreach ($assistantMessage->content as $block) {
    match (true) {
        $block instanceof TextBlock => echo $block->text,
        $block instanceof ThinkingBlock => echo "[Thinking] {$block->thinking}",
        $block instanceof ToolUseBlock => echo "[Tool: {$block->name}]",
        default => null,
    };
}
```

## Message Factory

All messages are created via the static factory:
```php
use ClaudeAgentSDK\Messages\Message;

$message = Message::fromJson($parsedJsonArray);
// Returns the appropriate typed message based on 'type' field
```