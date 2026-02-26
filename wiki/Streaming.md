# Streaming

> Process Claude's responses in real time for live UIs, progress indicators, and efficient memory usage.

## Overview

| Method | Returns | Memory | Use case |
|---|---|---|---|
| `stream($prompt, $options)` | `Generator<Message>` | Constant | Live output, SSE endpoints |
| `streamCollect($prompt, $onMessage, $options)` | `QueryResult` | Grows | Callbacks plus final result |

Both yield the same message types in order:

| Message class | Key API | Appears |
|---|---|---|
| `SystemMessage` | `isInit()`, `$sessionId` | Once at start |
| `AssistantMessage` | `text()`, `toolUses()` | One or more times |
| `ResultMessage` | `isSuccess()`, `$totalCostUsd`, `$numTurns` | Once at end |

## Basic Streaming with stream()
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\{AssistantMessage, ResultMessage, SystemMessage};

foreach (ClaudeAgent::stream('Refactor the User model') as $message) {
    if ($message instanceof SystemMessage && $message->isInit()) {
        echo "Session: {$message->sessionId}\n";
    }
    if ($message instanceof AssistantMessage) {
        echo $message->text();
        foreach ($message->toolUses() as $tool) { echo "[Tool: {$tool->name}] "; }
    }
    if ($message instanceof ResultMessage) {
        echo "\nCost: \${$message->totalCostUsd} | Turns: {$message->numTurns}\n";
    }
}
```

> **Tip:** `stream()` returns a Generator -- `break` any time, or call `$agent->stop()` to terminate the process.

## Stream with Collection via streamCollect()
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$result = ClaudeAgent::streamCollect(
    prompt: 'Create a migration for products table',
    onMessage: fn($m) => $m instanceof AssistantMessage ? logger()->info($m->text()) : null,
    options: ClaudeAgentOptions::make()->tools(['Read', 'Write', 'Bash']),
);
echo $result->text();
```

## Real-World Integration Patterns

### SSE Endpoint
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\{AssistantMessage, ResultMessage};
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::get('/agent/stream', function () {
    return new StreamedResponse(function () {
        foreach (ClaudeAgent::stream(request('prompt')) as $message) {
            if ($message instanceof AssistantMessage) {
                echo "event: message\ndata: ".json_encode(['text' => $message->text()])."\n\n";
            }
            if ($message instanceof ResultMessage) {
                echo "event: done\ndata: ".json_encode(['cost' => $message->totalCostUsd])."\n\n";
            }
            ob_flush(); flush();
        }
    }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
});
```
```js
const source = new EventSource('/agent/stream?prompt=' + encodeURIComponent(prompt));
source.addEventListener('message', (e) => {
    document.getElementById('output').textContent += JSON.parse(e.data).text;
});
source.addEventListener('done', () => source.close());
```

### WebSocket Broadcasting
Offload to a job and broadcast via Laravel Echo:
```php
use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Messages\AssistantMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class RunAgentJob implements ShouldQueue
{
    public function __construct(public string $prompt, public int $userId) {}
    public function handle(ClaudeAgentManager $agent): void
    {
        $result = $agent->streamCollect(
            prompt: $this->prompt,
            onMessage: fn($m) => $m instanceof AssistantMessage
                ? broadcast(new AgentMessageEvent($this->userId, $m->text())) : null,
        );
        broadcast(new AgentCompleteEvent($this->userId, $result->text(), $result->costUsd()));
    }
}
```
```js
Echo.private(`agent.${userId}`)
    .listen('AgentMessageEvent', (e) => appendText(e.text))
    .listen('AgentCompleteEvent', (e) => showComplete(e.result));
```

### Livewire Integration
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use Livewire\Component;

class AgentChat extends Component
{
    public string $output = '';
    public function ask(string $prompt): void
    {
        ClaudeAgent::streamCollect($prompt, function ($message) {
            if ($message instanceof AssistantMessage) {
                $this->output .= $message->text();
                $this->stream('output', $this->output);
            }
        });
    }
}
```

## Partial Messages
Enable partial (incomplete) messages for the most granular output:
```php
$options = ClaudeAgentOptions::make()->includePartialMessages();
foreach (ClaudeAgent::stream('Write a poem', $options) as $message) {
    echo $message->text();
}
```

> **Warning:** Partial messages may contain incomplete sentences. Treat each chunk as an incremental append.

## Progress Tracking
```php
$toolCount = 0;
foreach (ClaudeAgent::stream($prompt) as $message) {
    if ($message instanceof AssistantMessage) { $toolCount += count($message->toolUses()); }
    if ($message instanceof ResultMessage) {
        logger()->info("Done", ['cost' => $message->totalCostUsd, 'tools' => $toolCount]);
    }
}
```

## Memory Considerations

| Method | Behaviour |
|---|---|
| `stream()` | Generator -- messages are garbage-collected each iteration. Constant memory. |
| `streamCollect()` | Collects all messages. Memory grows with response length. |

> **Tip:** For long conversations or high-throughput workers, prefer `stream()` unless you need the final `QueryResult`.

## Error Handling During Streaming
```php
use ClaudeAgentSDK\Exceptions\{ClaudeAgentException, ProcessException};

try {
    foreach (ClaudeAgent::stream($prompt) as $message) { /* ... */ }
} catch (ProcessException $e) {
    logger()->error('Process failed', ['exit_code' => $e->getCode()]);
} catch (ClaudeAgentException $e) {
    logger()->error('SDK error: ' . $e->getMessage());
}
```

> **Tip:** A `ResultMessage` with `isSuccess() === false` is not an exception -- check both the exception path and `ResultMessage->isError` for full coverage.

## Next Steps

- [[Basic Usage]] -- Non-streaming query API and result handling
- [[Configuration]] -- Set default models, budgets, and tool permissions
- [[Hooks]] -- Trigger shell commands on tool-use events
- [[Advanced Options]] -- Structured output, MCP servers, and agents
