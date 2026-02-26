# Testing Your Integration

> Mock the SDK at every level -- facade, manager, and message fixtures -- to write fast, deterministic, cost-free tests for your Claude agent integration.

## Overview

Testing AI integrations presents three challenges: **cost** (every real query costs money), **speed** (queries take seconds, not milliseconds), and **determinism** (LLM output varies between runs). The SDK provides multiple mocking strategies so your test suite stays fast, free, and predictable.

## Mocking the Facade

The `ClaudeAgent` facade extends Laravel's base `Facade`, so it supports `shouldReceive` out of the box:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\QueryResult;
use ClaudeAgentSDK\Messages\ResultMessage;

public function test_it_analyzes_code(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->with('Analyze the User model', \Mockery::any())
        ->andReturn(new QueryResult([
            ResultMessage::parse([
                'type'           => 'result',
                'subtype'        => 'success',
                'result'         => 'The User model looks well-structured.',
                'session_id'     => 'sess_test_123',
                'total_cost_usd' => 0.002,
                'num_turns'      => 1,
                'duration_ms'    => 450,
            ]),
        ]));

    $response = $this->postJson('/api/analyze', ['prompt' => 'Analyze the User model']);

    $response->assertOk();
    $response->assertJsonFragment(['result' => 'The User model looks well-structured.']);
}
```

**Multiple expectations:**

```php
ClaudeAgent::shouldReceive('query')
    ->once()
    ->with('Step 1: Analyze', \Mockery::any())
    ->andReturn(new QueryResult([
        ResultMessage::parse([
            'type' => 'result', 'subtype' => 'success',
            'result' => 'Found 3 issues.', 'session_id' => 'sess_1',
        ]),
    ]));

ClaudeAgent::shouldReceive('query')
    ->once()
    ->with('Step 2: Fix the issues', \Mockery::any())
    ->andReturn(new QueryResult([
        ResultMessage::parse([
            'type' => 'result', 'subtype' => 'success',
            'result' => 'All issues fixed.', 'session_id' => 'sess_1',
        ]),
    ]));
```

## Mocking the Manager via DI

When your code injects `ClaudeAgentManager` directly (e.g. in a queued job), mock it and bind it to the container:

```php
use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\QueryResult;
use ClaudeAgentSDK\Messages\ResultMessage;
use Mockery;

public function test_job_calls_agent(): void
{
    $fakeResult = new QueryResult([
        ResultMessage::parse([
            'type' => 'result', 'subtype' => 'success',
            'result' => 'Job complete.',
        ]),
    ]);

    $mock = Mockery::mock(ClaudeAgentManager::class);
    $mock->shouldReceive('query')
        ->once()
        ->andReturn($fakeResult);

    $this->app->instance(ClaudeAgentManager::class, $mock);
    $this->app->instance('claude-agent', $mock);

    // Dispatch and assert your job
    RunAgentJob::dispatch('Analyze code');
}
```

## Creating Test Fixtures

### ResultMessage Fixtures

**Success result:**

```php
use ClaudeAgentSDK\Messages\ResultMessage;

$success = ResultMessage::parse([
    'type'           => 'result',
    'subtype'        => 'success',
    'result'         => 'Analysis complete.',
    'session_id'     => 'sess_abc',
    'total_cost_usd' => 0.003,
    'num_turns'      => 2,
    'duration_ms'    => 1200,
]);
```

**Error result:**

```php
$error = ResultMessage::parse([
    'type'     => 'result',
    'subtype'  => 'error_max_turns',
    'result'   => 'Max turns reached.',
    'is_error' => true,
]);
```

**With model usage (cache metrics):**

```php
$withUsage = ResultMessage::parse([
    'type'        => 'result',
    'subtype'     => 'success',
    'result'      => 'Done.',
    'model_usage' => [
        'claude-sonnet-4-5-20250929' => [
            'inputTokens'              => 100,
            'outputTokens'             => 50,
            'cacheReadInputTokens'     => 5000,
            'cacheCreationInputTokens' => 200,
            'costUSD'                  => 0.003,
        ],
    ],
]);
```

**With structured output:**

```php
$structured = ResultMessage::parse([
    'type'              => 'result',
    'subtype'           => 'success',
    'result'            => '{"summary":"Clean code","issues":[]}',
    'structured_output' => ['summary' => 'Clean code', 'issues' => []],
]);
```

### AssistantMessage Fixtures

**Text-only message:**

```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Content\TextBlock;

$textMsg = new AssistantMessage(
    content: [new TextBlock('The code follows PSR-12 standards.')],
    id: 'msg_test_1',
    model: 'claude-sonnet-4-5-20250929',
);
```

**With tool use:**

```php
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;

$toolMsg = new AssistantMessage(
    content: [
        new TextBlock('I will fix the issue in User.php.'),
        new ToolUseBlock('tu_1', 'Edit', ['path' => '/app/Models/User.php']),
    ],
    id: 'msg_test_2',
    model: 'claude-sonnet-4-5-20250929',
);
```

**With thinking block:**

```php
use ClaudeAgentSDK\Content\ThinkingBlock;
use ClaudeAgentSDK\Content\TextBlock;

$thinkingMsg = new AssistantMessage(
    content: [
        new ThinkingBlock('Let me consider the best approach...'),
        new TextBlock('I recommend the Strategy pattern.'),
    ],
    id: 'msg_test_3',
    model: 'claude-opus-4-20250514',
);
```

**Subagent message (with parentToolUseId):**

```php
$subagentMsg = new AssistantMessage(
    content: [new TextBlock('Security review passed.')],
    id: 'msg_sub_1',
    model: 'claude-sonnet-4-5-20250929',
    parentToolUseId: 'tu_parent_1',
);
```

### SystemMessage Fixtures

```php
use ClaudeAgentSDK\Messages\SystemMessage;

$init = new SystemMessage(
    subtype: 'init',
    sessionId: 'sess_test_456',
);

$this->assertTrue($init->isInit());
$this->assertSame('sess_test_456', $init->sessionId);
```

### QueryResult Fixtures

**Simple result:**

```php
use ClaudeAgentSDK\QueryResult;
use ClaudeAgentSDK\Messages\ResultMessage;

$result = new QueryResult([
    ResultMessage::parse([
        'type' => 'result', 'subtype' => 'success',
        'result' => 'Done.', 'session_id' => 'sess_1',
    ]),
]);

$this->assertTrue($result->isSuccess());
$this->assertSame('Done.', $result->text());
$this->assertSame('sess_1', $result->sessionId);
```

**Multi-message result:**

```php
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Content\TextBlock;

$result = new QueryResult([
    new SystemMessage(subtype: 'init', sessionId: 'sess_multi'),
    new AssistantMessage(
        content: [new TextBlock('Analyzing...')],
        id: 'msg_1', model: 'claude-sonnet-4-5-20250929',
    ),
    new AssistantMessage(
        content: [new TextBlock('Found the issue.')],
        id: 'msg_2', model: 'claude-sonnet-4-5-20250929',
    ),
    ResultMessage::parse([
        'type' => 'result', 'subtype' => 'success',
        'result' => 'Refactoring complete.',
        'session_id' => 'sess_multi', 'total_cost_usd' => 0.01,
    ]),
]);

$this->assertCount(2, $result->assistantMessages());
$this->assertSame('sess_multi', $result->sessionId);
```

**Error result:**

```php
$errorResult = new QueryResult([
    ResultMessage::parse([
        'type' => 'result', 'subtype' => 'error_max_turns',
        'result' => 'Max turns reached.', 'is_error' => true,
    ]),
]);

$this->assertTrue($errorResult->isError());
$this->assertFalse($errorResult->isSuccess());
```

**With cache metrics:**

```php
use ClaudeAgentSDK\Data\ModelUsage;

$result = new QueryResult([
    ResultMessage::parse([
        'type' => 'result', 'subtype' => 'success', 'result' => 'Done',
        'model_usage' => [
            'claude-sonnet-4-5-20250929' => [
                'inputTokens' => 100, 'outputTokens' => 50,
                'cacheReadInputTokens' => 5000,
                'cacheCreationInputTokens' => 200,
                'costUSD' => 0.003,
            ],
        ],
    ]),
]);

$this->assertSame(5000, $result->cacheReadTokens());
$this->assertSame(200, $result->cacheCreationTokens());

$usage = $result->modelUsage()['claude-sonnet-4-5-20250929'];
$this->assertInstanceOf(ModelUsage::class, $usage);
$this->assertGreaterThan(0.9, $usage->cacheHitRate());
```

## Testing Streaming

**Mocking `stream()` as a generator:**

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Content\TextBlock;

ClaudeAgent::shouldReceive('stream')
    ->once()
    ->andReturnUsing(function () {
        yield new AssistantMessage(
            content: [new TextBlock('Working on it...')],
            id: 'msg_1', model: 'claude-sonnet-4-5-20250929',
        );
        yield ResultMessage::parse([
            'type' => 'result', 'subtype' => 'success',
            'result' => 'Stream complete.',
        ]);
    });

$messages = [];
foreach (ClaudeAgent::stream('Do something') as $msg) {
    $messages[] = $msg;
}

$this->assertCount(2, $messages);
$this->assertInstanceOf(AssistantMessage::class, $messages[0]);
$this->assertInstanceOf(ResultMessage::class, $messages[1]);
```

**Testing `streamCollect()` with callback:**

```php
$collected = [];

ClaudeAgent::shouldReceive('streamCollect')
    ->once()
    ->andReturnUsing(function ($prompt, $onMessage) use (&$collected) {
        $msg = new AssistantMessage(
            content: [new TextBlock('Progress update')],
            id: 'msg_1', model: 'claude-sonnet-4-5-20250929',
        );
        if ($onMessage) {
            $onMessage($msg);
        }
        $collected[] = $msg;

        return new QueryResult([
            $msg,
            ResultMessage::parse([
                'type' => 'result', 'subtype' => 'success', 'result' => 'Done.',
            ]),
        ]);
    });

$result = ClaudeAgent::streamCollect('Run task', function ($msg) use (&$collected) {
    // Callback logic under test
});

$this->assertTrue($result->isSuccess());
```

## Testing Session Management

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

public function test_session_id_is_stored(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->andReturn(new QueryResult([
            ResultMessage::parse([
                'type' => 'result', 'subtype' => 'success',
                'result' => 'Analyzed.', 'session_id' => 'sess_persist',
            ]),
        ]));

    $result = ClaudeAgent::query('Analyze code');

    cache()->put('agent_session', $result->sessionId);
    $this->assertSame('sess_persist', cache()->get('agent_session'));
}

public function test_session_is_resumed(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->with('Follow up question', \Mockery::on(function ($opts) {
            return $opts instanceof ClaudeAgentOptions
                && $opts->sessionId === 'sess_resume_test';
        }))
        ->andReturn(new QueryResult([
            ResultMessage::parse([
                'type' => 'result', 'subtype' => 'success', 'result' => 'Resumed.',
            ]),
        ]));

    $options = ClaudeAgentOptions::make()->resume('sess_resume_test');
    $result = ClaudeAgent::query('Follow up question', $options);

    $this->assertTrue($result->isSuccess());
}
```

## Testing Options

Assert that the correct options are built and passed to the SDK:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

public function test_options_builder_output(): void
{
    $options = ClaudeAgentOptions::make()
        ->model('claude-sonnet-4-5-20250929')
        ->tools(['Read', 'Grep'])
        ->maxTurns(10)
        ->maxBudgetUsd(1.50)
        ->permission('dontAsk');

    $args = $options->toCliArgs();

    $this->assertContains('--model', $args);
    $this->assertContains('claude-sonnet-4-5-20250929', $args);
    $this->assertContains('--allowed-tools', $args);
    $this->assertContains('--max-turns', $args);
    $this->assertContains('--permission-mode', $args);
}
```

## Testing Error Scenarios

**Exception handling:**

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\ProcessException;

public function test_process_exception_is_handled(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->andThrow(new ProcessException('Process failed', exitCode: 1, stderr: 'Auth error'));

    $response = $this->postJson('/api/analyze', ['prompt' => 'test']);

    $response->assertStatus(500);
}
```

**Result errors:**

```php
public function test_agent_error_result(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->andReturn(new QueryResult([
            ResultMessage::parse([
                'type' => 'result', 'subtype' => 'error_max_turns',
                'result' => 'Max turns reached.', 'is_error' => true,
            ]),
        ]));

    $response = $this->postJson('/api/analyze', ['prompt' => 'big task']);

    $response->assertStatus(422);
}
```

**Timeout:**

```php
use ClaudeAgentSDK\Exceptions\ProcessException;

public function test_timeout_handling(): void
{
    ClaudeAgent::shouldReceive('query')
        ->once()
        ->andThrow(new ProcessException('Process timed out', exitCode: 137, stderr: 'Killed'));

    $response = $this->postJson('/api/analyze', ['prompt' => 'huge task']);

    $response->assertStatus(500);
    $response->assertJsonFragment(['error' => 'Request timed out']);
}
```

## Factory / Helper Pattern

Create a reusable test helper to reduce boilerplate:

```php
// tests/Support/AgentFixtures.php
namespace Tests\Support;

use ClaudeAgentSDK\QueryResult;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Content\TextBlock;

class AgentFixtures
{
    public static function successResult(
        string $text = 'Done.',
        string $sessionId = 'sess_test',
        float $cost = 0.001,
    ): QueryResult {
        return new QueryResult([
            ResultMessage::parse([
                'type'           => 'result',
                'subtype'        => 'success',
                'result'         => $text,
                'session_id'     => $sessionId,
                'total_cost_usd' => $cost,
            ]),
        ]);
    }

    public static function errorResult(
        string $text = 'Max turns reached.',
        string $subtype = 'error_max_turns',
    ): QueryResult {
        return new QueryResult([
            ResultMessage::parse([
                'type' => 'result', 'subtype' => $subtype,
                'result' => $text, 'is_error' => true,
            ]),
        ]);
    }

    public static function multiStepResult(array $steps, string $finalText = 'Complete.'): QueryResult
    {
        $messages = [new SystemMessage(subtype: 'init', sessionId: 'sess_multi')];
        foreach ($steps as $i => $step) {
            $messages[] = new AssistantMessage(
                content: [new TextBlock($step)],
                id: "msg_{$i}", model: 'claude-sonnet-4-5-20250929',
            );
        }
        $messages[] = ResultMessage::parse([
            'type' => 'result', 'subtype' => 'success', 'result' => $finalText,
        ]);

        return new QueryResult($messages);
    }
}
```

Usage:

```php
use Tests\Support\AgentFixtures;

ClaudeAgent::shouldReceive('query')->andReturn(AgentFixtures::successResult('Analysis done.'));
ClaudeAgent::shouldReceive('query')->andReturn(AgentFixtures::errorResult());
```

## Integration Testing with Real CLI

For critical workflows, you may want to run a small number of integration tests against the real CLI. These tests hit the actual API and cost real money.

> **Warning:** Real integration tests incur API costs. Always set a tight `max_budget_usd` and mark them as slow so they do not run on every commit.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

/**
 * @group slow
 * @group integration
 */
public function test_real_query_returns_valid_result(): void
{
    $result = ClaudeAgent::query(
        'What is 2 + 2? Reply with just the number.',
        ClaudeAgentOptions::make()
            ->maxTurns(1)
            ->maxBudgetUsd(0.05)
            ->tools([]) // No tools needed
            ->permission('dontAsk'),
    );

    $this->assertTrue($result->isSuccess());
    $this->assertNotEmpty($result->text());
    $this->assertStringContainsString('4', $result->text());
    $this->assertNotNull($result->sessionId);
}
```

Run integration tests selectively:

```bash
php artisan test --group=integration
```

## Next Steps

- [[Error Handling]] -- exception types and comprehensive error patterns
- [[Streaming]] -- streaming-specific testing patterns
- [[Options Reference]] -- all available options for configuring test queries
- [[Troubleshooting and FAQ]] -- common testing pitfalls and solutions
