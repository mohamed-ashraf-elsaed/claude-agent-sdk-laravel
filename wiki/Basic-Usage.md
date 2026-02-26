# Basic Usage

> Send queries to Claude, configure options, and work with results.

## Overview

| Approach | Best for | Entry point |
|---|---|---|
| **Facade** | Controllers, routes, scripts | `ClaudeAgent::query()` |
| **Dependency Injection** | Testable services | `ClaudeAgentManager` type-hint |
| **Direct Instantiation** | Outside the container | `new ClaudeAgentManager(config(...))` |

## Using the Facade

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Explain what this codebase does');
echo $result->text();
```

## Using Dependency Injection

```php
use ClaudeAgentSDK\ClaudeAgentManager;

class CodeAnalysisController
{
    public function analyze(ClaudeAgentManager $agent)
    {
        $result = $agent->query('Find potential bugs in app/Models/');
        return response()->json(['analysis' => $result->text(), 'cost' => $result->costUsd()]);
    }
}
```

## Direct Instantiation

```php
$agent  = new ClaudeAgentManager(config('claude-agent'));
$result = $agent->query('Summarize README.md');
```

## Passing Options

### Fluent Builder
```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->permission('acceptEdits')
    ->maxTurns(5)->maxBudgetUsd(2.00);

$result = ClaudeAgent::query('List all route files', $options);
```

### From Array
```php
$result = ClaudeAgent::query('Analyze this', ClaudeAgentOptions::fromArray([
    'allowed_tools' => ['Read'], 'max_turns' => 3, 'max_budget_usd' => 1.00,
]));
```

### Inline Array
A plain array is converted via `fromArray()` internally:
```php
$result = ClaudeAgent::query('Analyze this', ['allowed_tools' => ['Read'], 'max_turns' => 3]);
```

## Persistent Default Options

`withOptions()` returns an immutable clone with defaults for every subsequent query:
```php
$agent = ClaudeAgent::withOptions(
    ClaudeAgentOptions::make()->tools(['Read', 'Grep'])->permission('acceptEdits')
);
$result1 = $agent->query('Find all models');
$result2 = $agent->query('Find all controllers');
```

## Working with Results

| Method / Property | Return | Description |
|---|---|---|
| `text()` | `?string` | Final response text |
| `structured()` | `?array` | Parsed structured output (when `outputFormat` set) |
| `isSuccess()` | `bool` | `true` if query completed without error |
| `isError()` | `bool` | `true` if result flagged an error |
| `costUsd()` | `?float` | Total API cost in USD |
| `turns()` | `int` | Number of conversational turns |
| `durationMs()` | `int` | Wall-clock duration in milliseconds |
| `assistantMessages()` | `AssistantMessage[]` | All assistant messages |
| `fullText()` | `string` | Concatenated text from every assistant message |
| `toolUses()` | `ToolUseBlock[]` | All tool-use blocks across messages |
| `modelUsage()` | `array<string, ModelUsage>` | Per-model token and cost breakdown |
| `cacheReadTokens()` | `int` | Total cache-read tokens |
| `cacheCreationTokens()` | `int` | Total cache-creation tokens |
| `$sessionId` | `?string` | Session ID for resuming conversations |

## Using in Artisan Commands
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use Illuminate\Console\Command;

class AnalyzeCodeCommand extends Command
{
    protected $signature = 'code:analyze {prompt}';
    public function handle(): int
    {
        $result = ClaudeAgent::query($this->argument('prompt'));
        $this->info($result->text());
        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }
}
```

## Using in Jobs

> **Warning:** The Claude CLI must be accessible to the user running the queue worker.

```php
use ClaudeAgentSDK\ClaudeAgentManager;
use Illuminate\Contracts\Queue\ShouldQueue;

class RunAgentJob implements ShouldQueue
{
    public function __construct(public string $prompt) {}
    public function handle(ClaudeAgentManager $agent): void
    {
        $result = $agent->query($this->prompt);
        logger()->info('Done', ['success' => $result->isSuccess(), 'cost' => $result->costUsd()]);
    }
}
```

## Stopping a Running Query
```php
$agent = app(ClaudeAgentManager::class);
$agent->stop(); // sends SIGINT; safe when no process is running
```

## Next Steps

- [[Streaming]] -- Process messages as they arrive in real time
- [[Configuration]] -- Tune models, budgets, tools, and permission modes
- [[Hooks]] -- Run shell commands before or after tool use
- [[Advanced Options]] -- MCP servers, agents, structured output, and more
