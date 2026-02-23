# Error Handling

## Exception Types

| Exception              | When                                           |
|------------------------|-------------------------------------------------|
| `CliNotFoundException` | Claude Code CLI binary not found                |
| `ProcessException`     | CLI process failed with non-zero exit code      |
| `JsonParseException`   | JSON-looking CLI output failed to parse          |
| `ClaudeAgentException` | General SDK errors (base class for all above)   |

## Handling Errors
```php
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
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
} catch (JsonParseException $e) {
    Log::error('Failed to parse CLI output', [
        'raw_line' => $e->rawLine,
        'original_error' => $e->originalError?->getMessage(),
    ]);
} catch (ClaudeAgentException $e) {
    Log::error('SDK error: ' . $e->getMessage());
}
```

## When Exceptions Are Thrown

**`ProcessException`** is thrown when:
- The CLI exits with a non-zero code AND no result messages were found in the output.
- If the CLI exits non-zero but returned valid result messages (e.g., max turns reached), no exception is thrown — check `$result->isError()` instead.

**`JsonParseException`** is thrown when:
- A line in the CLI output looks like JSON (starts with `{` or `[`) but fails to parse.
- Plain text lines (CLI startup messages, warnings) are silently skipped.

**`CliNotFoundException`** is thrown when:
- The stderr output contains "not found" or "command not found".

## Result-Level Errors

Not all errors throw exceptions. The result itself may indicate failure:
```php
$result = ClaudeAgent::query('...');

if ($result->isError()) {
    // Agent completed but reported an error
    echo "Error: " . $result->text();
}
```