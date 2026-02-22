# Streaming

Streaming lets you process messages as they arrive rather than waiting for the full response.

## Basic Streaming
```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream('Refactor the User model') as $message) {
    if ($message instanceof SystemMessage && $message->isInit()) {
        echo "Session: {$message->sessionId}\n";
    }

    if ($message instanceof AssistantMessage) {
        echo $message->text();

        foreach ($message->toolUses() as $tool) {
            echo "\n[Tool: {$tool->name}]\n";
        }
    }

    if ($message instanceof ResultMessage) {
        echo "\n---\n";
        echo "Success: " . ($message->isSuccess() ? 'yes' : 'no') . "\n";
        echo "Cost: $" . $message->totalCostUsd . "\n";
        echo "Turns: " . $message->numTurns . "\n";
    }
}
```

## Stream with Collection

Process messages during streaming AND get a final QueryResult:
```php
$result = ClaudeAgent::streamCollect(
    prompt: 'Create a migration for products table',
    onMessage: function ($message) {
        // Broadcast to WebSocket, log, update progress bar, etc.
        if ($message instanceof AssistantMessage) {
            broadcast(new AgentProgress($message->text()));
        }
    },
    options: ClaudeAgentOptions::make()->tools(['Read', 'Write', 'Bash']),
);

// Full result available after streaming completes
echo $result->text();
echo $result->costUsd();
```

## Laravel Broadcasting Example
```php
// In a Job
class RunAgentJob implements ShouldQueue
{
    public function handle(ClaudeAgentManager $agent): void
    {
        $result = $agent->streamCollect(
            prompt: $this->prompt,
            onMessage: function ($message) {
                if ($message instanceof AssistantMessage) {
                    broadcast(new AgentMessageEvent(
                        userId: $this->userId,
                        text: $message->text(),
                    ));
                }
            },
            options: $this->options,
        );

        broadcast(new AgentCompleteEvent(
            userId: $this->userId,
            result: $result->text(),
            cost: $result->costUsd(),
        ));
    }
}
```