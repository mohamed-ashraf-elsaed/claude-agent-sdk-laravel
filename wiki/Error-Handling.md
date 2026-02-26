# Error Handling

> Understand the two levels of errors in the SDK -- exceptions thrown during execution and result-level errors returned by the agent -- and learn patterns for catching, retrying, and degrading gracefully.

## Overview

The SDK surfaces errors through two distinct mechanisms:

1. **Exceptions** -- thrown when something prevents the query from completing (CLI not found, process crash, corrupt output). These are caught with `try/catch`.
2. **Result errors** -- returned when the agent completes but reports a problem (max turns reached, budget exceeded, agent-reported failure). These are checked with `$result->isError()`.

A robust integration handles both levels.

## Exception Types

| Exception | Extends | When Thrown |
|-----------|---------|------------|
| `ClaudeAgentException` | `RuntimeException` | Base class for all SDK exceptions |
| `CliNotFoundException` | `ClaudeAgentException` | Claude Code CLI binary not found on the system |
| `ProcessException` | `ClaudeAgentException` | CLI process exits non-zero with no result messages |
| `JsonParseException` | `ClaudeAgentException` | A JSON-looking output line fails to parse |

## CliNotFoundException

Thrown when the CLI stderr output contains "not found" or "command not found". The exception message includes installation instructions.

**Common causes:**
- The Claude Code CLI (`@anthropic-ai/claude-code`) is not installed
- The binary is not on the PHP process `$PATH`
- The `cli_path` config points to a non-existent location

**Resolution steps:**
1. Install the CLI globally: `npm install -g @anthropic-ai/claude-code`
2. Verify it works: `claude --version`
3. If PHP runs as a different user (e.g. `www-data`), ensure the binary is accessible to that user
4. Alternatively, set an explicit path in `config/claude-agent.php` via `cli_path`

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\CliNotFoundException;

try {
    $result = ClaudeAgent::query('Hello');
} catch (CliNotFoundException $e) {
    // $e->getMessage() includes install instructions
    Log::critical('Claude CLI not installed: ' . $e->getMessage());
    abort(503, 'AI service unavailable');
}
```

## ProcessException

Thrown when the CLI exits with a non-zero exit code **and** no valid result messages were found in the output. If the CLI exits non-zero but produced valid result messages (e.g. max turns reached), no exception is thrown -- check `$result->isError()` instead.

**Properties:**
- `$exitCode` (`?int`) -- the process exit code
- `$stderr` (`?string`) -- captured stderr output

**Common exit codes:**
| Code | Meaning |
|------|---------|
| 1 | General error (invalid arguments, authentication failure) |
| 2 | Misuse of CLI (bad flags, missing prompt) |
| 137 | Process killed (OOM or `process_timeout` exceeded) |
| 139 | Segmentation fault (rare, usually a CLI bug) |

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\ProcessException;

try {
    $result = ClaudeAgent::query('Analyze this codebase');
} catch (ProcessException $e) {
    Log::error('CLI process failed', [
        'exit_code' => $e->exitCode,
        'stderr'    => $e->stderr,
    ]);
}
```

## JsonParseException

Thrown when a line in the CLI output starts with `{` or `[` (looks like JSON) but fails to parse. Plain text lines -- such as CLI startup messages or warnings -- are silently skipped and do not trigger this exception.

**Properties:**
- `$rawLine` (`string`) -- the raw output line that failed to parse
- `$originalError` (`?Throwable`) -- the underlying `json_decode` error

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\JsonParseException;

try {
    $result = ClaudeAgent::query('Summarize the README');
} catch (JsonParseException $e) {
    Log::error('JSON parse failure', [
        'raw_line'       => $e->rawLine,
        'original_error' => $e->originalError?->getMessage(),
    ]);
}
```

## ClaudeAgentException

The base class for all SDK exceptions. Extend `RuntimeException`. Use this as a catch-all after handling specific exception types:

```php
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = ClaudeAgent::query('...');
} catch (ClaudeAgentException $e) {
    // Catches CliNotFoundException, ProcessException, JsonParseException,
    // and any future SDK exception types
    Log::error('SDK error: ' . $e->getMessage());
}
```

## Result-Level Errors

Not all errors throw exceptions. When the agent completes but reports a problem, the result itself indicates failure:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Refactor the entire codebase');

if ($result->isError()) {
    // Agent completed but reported an error
    Log::warning('Agent error', ['text' => $result->text()]);
}

if ($result->isSuccess()) {
    // Agent completed successfully
    echo $result->text();
}
```

Result-level errors occur when:
- The agent reaches the `max_turns` limit without completing the task
- The `max_budget_usd` ceiling is exceeded mid-conversation
- The agent itself determines it cannot fulfill the request

> **Note:** `isError()` returns `true` when no `ResultMessage` is present (e.g. if the messages array is empty). Always check `isSuccess()` for positive confirmation.

## Comprehensive Error Handling Pattern

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = ClaudeAgent::query('Analyze the User model');

    if ($result->isError()) {
        Log::warning('Agent returned an error', [
            'text'  => $result->text(),
            'cost'  => $result->costUsd(),
            'turns' => $result->turns(),
        ]);
        return response()->json(['error' => 'Agent could not complete the task'], 422);
    }

    return response()->json([
        'result' => $result->text(),
        'cost'   => $result->costUsd(),
    ]);
} catch (CliNotFoundException $e) {
    Log::critical('Claude CLI not installed: ' . $e->getMessage());
    return response()->json(['error' => 'AI service unavailable'], 503);
} catch (ProcessException $e) {
    Log::error('CLI process failed', [
        'exit_code' => $e->exitCode,
        'stderr'    => $e->stderr,
    ]);
    return response()->json(['error' => 'AI service error'], 500);
} catch (JsonParseException $e) {
    Log::error('Failed to parse CLI output', [
        'raw_line'       => $e->rawLine,
        'original_error' => $e->originalError?->getMessage(),
    ]);
    return response()->json(['error' => 'AI service error'], 500);
} catch (ClaudeAgentException $e) {
    Log::error('SDK error: ' . $e->getMessage());
    return response()->json(['error' => 'AI service error'], 500);
}
```

## Error Handling in Streaming

When streaming, exceptions can be thrown at any point during the generator iteration. If an exception occurs mid-stream, you may have already received partial messages:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

$partialText = '';

try {
    foreach (ClaudeAgent::stream('Refactor the Order model') as $message) {
        if ($message instanceof AssistantMessage) {
            $partialText .= $message->text();
            echo $message->text();
        }
    }
} catch (ProcessException $e) {
    Log::error('Stream interrupted', [
        'exit_code'    => $e->exitCode,
        'partial_text' => $partialText,
    ]);
} catch (ClaudeAgentException $e) {
    Log::error('Stream error: ' . $e->getMessage(), [
        'partial_text' => $partialText,
    ]);
}
```

> **Tip:** When using `streamCollect()`, exceptions behave the same way -- wrap the call in `try/catch`. If the exception occurs after some messages were collected, those messages are lost unless you use the `onMessage` callback to capture them incrementally.

## Retry Strategies

**When to retry:**
- `ProcessException` with exit code 137 (process killed, possibly transient)
- Network-related failures in stderr (timeouts, connection resets)
- Transient API errors (rate limits, server overload)

**When NOT to retry:**
- `CliNotFoundException` -- the CLI is not installed; retrying will not help
- `JsonParseException` -- output corruption is unlikely to resolve itself
- Result-level errors (max turns, budget exceeded) -- these are deterministic
- Authentication failures in stderr -- fix the credentials first

**Simple retry:**

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\ProcessException;

$maxRetries = 3;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $result = ClaudeAgent::query('Analyze this file');
        break; // Success
    } catch (ProcessException $e) {
        if ($attempt === $maxRetries) {
            throw $e;
        }
        sleep(pow(2, $attempt)); // Exponential backoff: 2s, 4s, 8s
    }
}
```

> **Warning:** Every retry runs a new query and incurs additional API costs. Set `max_budget_usd` to cap total spend, and only retry on transient failures.

## Graceful Degradation

When the AI service is unavailable, fall back to alternative behaviour:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

function analyzeCode(string $code): string
{
    try {
        $result = ClaudeAgent::query("Analyze this code:\n{$code}");
        if ($result->isSuccess()) {
            return $result->text();
        }
    } catch (ClaudeAgentException $e) {
        Log::warning('AI analysis unavailable, using fallback', [
            'error' => $e->getMessage(),
        ]);
    }

    // Fallback: return a generic response or skip the AI step
    return 'Automated analysis is temporarily unavailable.';
}
```

**Feature flags** can disable AI features entirely during outages:

```php
if (config('features.ai_analysis', true)) {
    try {
        $analysis = ClaudeAgent::query($prompt);
    } catch (ClaudeAgentException $e) {
        config(['features.ai_analysis' => false]); // Disable for this request
        $analysis = null;
    }
}
```

## Logging Best Practices

**What to log:**
- Exception type and message
- `exitCode` and `stderr` from `ProcessException`
- `rawLine` from `JsonParseException` (for debugging CLI version issues)
- Cost and turn count from result errors (for budget monitoring)
- Partial text length during stream failures

**What NOT to log:**
- Full prompt text (may contain sensitive user data)
- Full response text at INFO level (can be very large)
- API keys or auth tokens from environment variables
- Raw CLI output lines beyond the failing line

## Next Steps

- [[Streaming]] -- error handling patterns specific to real-time output
- [[Configuration]] -- set `process_timeout`, `max_budget_usd`, and `max_turns` to prevent runaway queries
- [[Testing Your Integration]] -- mock exceptions and result errors in your test suite
- [[Troubleshooting and FAQ]] -- solutions for common issues
