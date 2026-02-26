# Hooks

> Run shell commands automatically before or after Claude uses tools, enabling linting, testing, auditing, backups, and notifications as part of your agent workflow.

## Overview

Hooks let you attach shell commands to lifecycle events in the Claude agent. When an event fires, the CLI executes your hook commands as subprocesses. This enables automated quality gates (lint before edits), validation (run tests after changes), security auditing (log Bash commands), and operational tasks (notify on completion).

Hooks are serialized as the `--hooks` CLI JSON argument. Each hook consists of a `HookEvent` (when to run), a `HookMatcher` (which tools to match), and one or more shell commands to execute.

## Quick Start

The fastest way to add hooks is with the `preToolUse()` and `postToolUse()` shorthand methods:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Write', 'Bash'])
    ->preToolUse('php-cs-fixer fix --dry-run --diff', '/Edit|Write/')
    ->postToolUse('php artisan test --filter=affected');

$result = ClaudeAgent::query('Refactor the User model', $options);
```

In this example, `php-cs-fixer` runs before every `Edit` or `Write` tool invocation, and the test suite runs after every tool completes.

## HookEvent Reference

The `HookEvent` enum defines six lifecycle events:

| Event | Enum Value | When It Fires |
|-------|------------|---------------|
| `HookEvent::PreToolUse` | `PreToolUse` | Before the CLI executes a tool. Use for validation, linting, or blocking. |
| `HookEvent::PostToolUse` | `PostToolUse` | After a tool completes. Use for testing, verification, or cleanup. |
| `HookEvent::UserPromptSubmit` | `UserPromptSubmit` | When the user prompt is submitted to the agent. Use for input sanitization or logging. |
| `HookEvent::Stop` | `Stop` | When the parent agent stops. Use for cleanup, notification, or summary generation. |
| `HookEvent::SubagentStop` | `SubagentStop` | When a subagent finishes. Use for per-subagent cleanup or result validation. |
| `HookEvent::PreCompact` | `PreCompact` | Before context compaction occurs. Use for snapshotting or logging context state. |

```php
use ClaudeAgentSDK\Hooks\HookEvent;

// Use enum cases directly
$event = HookEvent::PreToolUse;
$event = HookEvent::Stop;

// String value access
echo HookEvent::PostToolUse->value; // "PostToolUse"
```

## HookMatcher

The `HookMatcher` class defines which tools to match and what commands to run:

```php
use ClaudeAgentSDK\Hooks\HookMatcher;

// Full constructor
$matcher = new HookMatcher(
    matcher: '/Edit|Write/',   // Regex pattern for tool names (null = all tools)
    hooks: ['php lint.php'],   // Shell commands to execute
    timeout: 30,               // Seconds before the command is killed (default: 60)
);
```

### Factory Methods

Two static factory methods simplify common cases:

```php
use ClaudeAgentSDK\Hooks\HookMatcher;

// Single shell command
$matcher = HookMatcher::command('eslint --fix', '/Edit|Write/', 60);

// PHP script (automatically wraps with the PHP binary path)
$matcher = HookMatcher::phpScript('/app/hooks/validate.php', '/Bash/', 10);
// Equivalent to: new HookMatcher('/Bash/', ['php /app/hooks/validate.php'], 10)
```

The `phpScript()` factory uses `PHP_BINARY` to resolve the current PHP interpreter and `escapeshellarg()` to safely quote the script path.

### Constructor Parameters

| Parameter | Type       | Default | Description |
|-----------|------------|---------|-------------|
| `matcher` | `?string`  | `null`  | Regex pattern to match tool names. `null` matches all tools. |
| `hooks`   | `string[]` | `[]`    | Shell commands the CLI executes sequentially when the event fires. |
| `timeout` | `?int`     | `null`  | Timeout in seconds per hook command. Defaults to 60 when `null`. |

## Matcher Patterns

The `matcher` parameter is a regex pattern tested against the tool name. Pass `null` to match every tool.

| Pattern | Matches |
|---------|---------|
| `null` | All tools |
| `'/Edit\|Write/'` | `Edit` and `Write` tools |
| `'/Bash/'` | Only the `Bash` tool |
| `'/Edit/'` | Only the `Edit` tool |
| `'/mcp__.*/'` | All MCP server tools (they are prefixed with `mcp__`) |
| `'/mcp__database__.*/'` | Tools from a specific MCP server named `database` |

> **Note:** The pattern is matched against the tool name string. Use standard PHP regex syntax including delimiters.

## Shorthand Methods

The `ClaudeAgentOptions` class provides convenience methods for the two most common events:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    // preToolUse(command, matcher?, timeout?)
    ->preToolUse('php-cs-fixer fix --dry-run', '/Edit/', 30)

    // postToolUse(command, matcher?, timeout?)
    ->postToolUse('php artisan test', null, 120)

    // hook(HookEvent, HookMatcher) for any event
    ->hook(HookEvent::Stop, HookMatcher::command('php /hooks/cleanup.php'));
```

Each shorthand creates a `HookMatcher::command()` internally and attaches it to the corresponding `HookEvent`.

## Multiple Matchers per Event

Call the same hook method multiple times to stack matchers on a single event. Each matcher is evaluated independently:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Bash'])
    // Lint check before any Edit
    ->preToolUse('php-cs-fixer fix --dry-run --diff', '/Edit/')
    // Security audit before any Bash command
    ->preToolUse('php /hooks/audit-bash.php', '/Bash/', 10)
    // Log all tool uses regardless of tool name
    ->postToolUse('php /hooks/log-tool-use.php');
```

All matchers for a given event are serialized as an array in the `--hooks` JSON payload.

## Multiple Commands per Matcher

A single `HookMatcher` can run multiple commands sequentially. If any command fails, subsequent commands in the list are still executed:

```php
use ClaudeAgentSDK\Hooks\HookMatcher;

$matcher = new HookMatcher(
    matcher: '/Write/',
    hooks: [
        'php /hooks/backup.php',      // Step 1: backup the target file
        'php /hooks/validate.php',    // Step 2: validate the content
        'php /hooks/notify.php',      // Step 3: send a notification
    ],
    timeout: 120,
);
```

> **Tip:** Use multiple commands per matcher when they form a logical pipeline for the same set of tools. Use multiple matchers when different tools need different commands.

## Real-World Patterns

### Lint Before Edits

Run PHP CS Fixer in dry-run mode before any file modification to catch style violations early:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Write', 'Bash'])
    ->preToolUse('php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php', '/Edit|Write/', 30);
```

### Run Affected Tests

Execute PHPUnit after tool use to verify nothing is broken:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Write', 'Bash'])
    ->postToolUse('php artisan test --stop-on-failure', '/Edit|Write/', 120);
```

### Security Audit Log

Log every Bash command the agent runs for compliance and review:

```php
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Bash'])
    ->hook(HookEvent::PreToolUse, HookMatcher::phpScript(
        base_path('hooks/audit-log.php'),
        '/Bash/',
        10,
    ));
```

### Backup Before Write

Copy the original file to a backup location before any write operation:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Write'])
    ->preToolUse('php ' . base_path('hooks/backup-file.php'), '/Edit|Write/', 15);
```

### Notification on Completion

Send a Slack message when the agent finishes its work:

```php
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Write', 'Bash'])
    ->hook(HookEvent::Stop, HookMatcher::command(
        'curl -s -X POST "$SLACK_WEBHOOK_URL" -d \'{"text":"Agent completed."}\'',
    ))
    ->hook(HookEvent::SubagentStop, HookMatcher::phpScript(
        base_path('hooks/notify-subagent-done.php'),
    ));
```

## Writing PHP Hook Scripts

Hook scripts receive context from the CLI via environment variables and stdin. Here is a template for a PHP hook script:

```php
<?php
// hooks/audit-log.php

// Read the JSON payload from stdin (contains tool name, input, etc.)
$input = file_get_contents('php://stdin');
$payload = json_decode($input, true);

// Environment variables set by the CLI
$sessionId = getenv('CLAUDE_SESSION_ID') ?: 'unknown';
$toolName  = $payload['tool_name'] ?? 'unknown';
$toolInput = $payload['tool_input'] ?? [];

// Write to your audit log
$entry = [
    'timestamp'  => date('c'),
    'session_id' => $sessionId,
    'tool'       => $toolName,
    'input'      => $toolInput,
];

file_put_contents(
    '/var/log/claude-audit.jsonl',
    json_encode($entry) . "\n",
    FILE_APPEND | LOCK_EX,
);

// Exit 0 = success, non-zero = hook failure
exit(0);
```

> **Important:** Always handle errors gracefully in hook scripts. An unhandled exception that causes a non-zero exit code may be reported as a hook failure by the CLI.

### Error Handling in Hook Scripts

```php
<?php
// hooks/validate.php

try {
    $input = file_get_contents('php://stdin');
    $payload = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

    // Your validation logic here
    if (! isValid($payload)) {
        fwrite(STDERR, "Validation failed: invalid payload\n");
        exit(1);
    }

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Hook error: {$e->getMessage()}\n");
    exit(1);
}
```

## Timeout Handling

Each hook command runs with a timeout. When the timeout is reached, the CLI kills the subprocess.

| Scenario | Timeout | Behaviour |
|----------|---------|-----------|
| No timeout specified | 60 seconds | Default applied by the CLI |
| `timeout: 30` | 30 seconds | Custom timeout per matcher |
| `timeout: 0` | No timeout | The hook runs until it finishes (use with caution) |

```php
use ClaudeAgentSDK\Hooks\HookMatcher;

// Quick validation -- kill after 10 seconds
$fast = HookMatcher::command('php /hooks/quick-check.php', '/Edit/', 10);

// Test suite -- allow up to 5 minutes
$slow = new HookMatcher(
    matcher: '/Write/',
    hooks: ['php artisan test --stop-on-failure'],
    timeout: 300,
);
```

> **Warning:** Long-running hooks block the agent. The CLI waits for each hook to complete (or time out) before proceeding. Keep hook scripts fast, especially for `PreToolUse` hooks that run before every tool invocation.

## Next Steps

- [[Subagents]] -- Use `SubagentStop` hooks to run commands when subagents finish
- [[Streaming]] -- Stream agent output while hooks execute in the background
- [[Options Reference]] -- Full list of `ClaudeAgentOptions` fluent methods
- [[Error Handling]] -- How hook failures surface in exceptions and result messages
