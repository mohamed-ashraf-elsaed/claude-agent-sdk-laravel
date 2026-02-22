# Testing Your Integration

## Mocking the Facade
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\QueryResult;
use ClaudeAgentSDK\Messages\ResultMessage;

// In your test
ClaudeAgent::shouldReceive('query')
    ->once()
    ->with('Analyze code', \Mockery::type(ClaudeAgentOptions::class))
    ->andReturn(new QueryResult([
        ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Code looks good!',
            'session_id' => 'test_session',
            'total_cost_usd' => 0.001,
            'num_turns' => 1,
            'duration_ms' => 500,
        ]),
    ]));
```

## Mocking the Manager
```php
use ClaudeAgentSDK\ClaudeAgentManager;

$mock = Mockery::mock(ClaudeAgentManager::class);
$mock->shouldReceive('query')->andReturn($fakeResult);

$this->app->instance(ClaudeAgentManager::class, $mock);
$this->app->instance('claude-agent', $mock);
```

## Creating Test Fixtures
```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;

// Fake an assistant message with tool use
$msg = new AssistantMessage(
    content: [
        new TextBlock('I found the issue.'),
        new ToolUseBlock('tu_1', 'Edit', ['path' => '/app/Models/User.php']),
    ],
    id: 'msg_test',
    model: 'claude-sonnet-4-5-20250929',
);
```