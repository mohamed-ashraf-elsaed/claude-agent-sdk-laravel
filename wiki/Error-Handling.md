# Error Handling

## Exception Types

| Exception              | When                                      |
|------------------------|-------------------------------------------|
| `CliNotFoundException` | Claude Code CLI binary not found          |
| `ProcessException`     | CLI process failed (with exit code/stderr)|
| `JsonParseException`   | Failed to parse CLI JSON output           |
| `ClaudeAgentException` | General SDK errors (base class)           |

## Handling Errors
```php
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = ClaudeAgent::query('Do something complex');

    if ($result->isError()) {
        Log::warning('Agent returned error', ['text' => $result->text()]);
    }
} catch (CliNotFoundException $e) {
    // Install instructions in $e->getMessage()
    Log::error('Claude CLI not installed');
} catch (ProcessException $e) {
    Log::error('Process failed', [
        'exit_code' => $e->exitCode,
        'stderr' => $e->stderr,
    ]);
} catch (ClaudeAgentException $e) {
    Log::error('SDK error: ' . $e->getMessage());
}
```

## Result-Level Errors

Not all errors throw exceptions. The result itself may indicate failure:
```php
$result = ClaudeAgent::query('...');

if ($result->isError()) {
    // Agent completed but reported an error
    echo "Error: " . $result->text();
}
```