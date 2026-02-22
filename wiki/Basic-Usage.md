# Basic Usage

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
        return response()->json([
            'analysis' => $result->text(),
            'cost' => $result->costUsd(),
            'turns' => $result->turns(),
        ]);
    }
}
```

## With Options
```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->permission('acceptEdits')
    ->maxTurns(5)
    ->cwd(base_path());

$result = ClaudeAgent::query('List all route files', $options);
```

## From Array

Useful when options come from a request or config:
```php
$result = ClaudeAgent::query('Analyze this', [
    'allowed_tools' => ['Read'],
    'max_turns' => 3,
]);
```

## Persistent Default Options
```php
$agent = ClaudeAgent::withOptions(
    ClaudeAgentOptions::make()
        ->tools(['Read', 'Grep'])
        ->permission('acceptEdits')
);

// Both queries use the same defaults
$result1 = $agent->query('Find all models');
$result2 = $agent->query('Find all controllers');
```

## Stopping a Query
```php
$agent = app(ClaudeAgentManager::class);

// In another thread or signal handler:
$agent->stop(); // Sends SIGINT to the running process
```