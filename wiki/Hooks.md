# Hooks

Hooks let you run shell commands before or after Claude uses tools. They're executed by the CLI as subprocesses.

## Shorthand Methods

The quickest way to add hooks:
```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Bash'])
    ->preToolUse('php artisan lint:check', '/Edit|Write/', 30)
    ->postToolUse('php artisan test:affected');

$result = ClaudeAgent::query('Refactor the User model', $options);
```

## Using HookMatcher Directly

For full control, use `hook()` with a `HookEvent` and `HookMatcher`:
```php
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;

$options = ClaudeAgentOptions::make()
    ->hook(HookEvent::PreToolUse, new HookMatcher(
        matcher: '/Edit|Write/',
        hooks: ['php /hooks/lint.php', 'php /hooks/backup.php'],
        timeout: 30,
    ))
    ->hook(HookEvent::PostToolUse, HookMatcher::command('php /hooks/notify.php'))
    ->hook(HookEvent::Stop, HookMatcher::command('php /hooks/cleanup.php'));
```

## Factory Methods

```php
// From a shell command
$matcher = HookMatcher::command('eslint --fix', '/Edit|Write/', 60);

// From a PHP script (auto-wraps with php binary path)
$matcher = HookMatcher::phpScript('/hooks/validate.php', '/Bash/', 10);
```

## Multiple Matchers per Event

You can stack multiple matchers on the same event:
```php
$options = ClaudeAgentOptions::make()
    ->preToolUse('php /hooks/lint-edits.php', '/Edit/')
    ->preToolUse('php /hooks/audit-bash.php', '/Bash/', 10);
```

## Multiple Hook Commands per Matcher

A single matcher can run multiple commands sequentially:
```php
$matcher = new HookMatcher(
    matcher: '/Write/',
    hooks: [
        'php /hooks/backup.php',
        'php /hooks/validate.php',
        'php /hooks/notify.php',
    ],
    timeout: 120,
);
```

## Available Events

| Event              | When                                  |
|--------------------|---------------------------------------|
| `PreToolUse`       | Before a tool is executed             |
| `PostToolUse`      | After a tool completes                |
| `UserPromptSubmit` | When user prompt is submitted         |
| `Stop`             | When the agent stops                  |
| `SubagentStop`     | When a subagent stops                 |
| `PreCompact`       | Before context compaction             |

## HookMatcher Parameters

| Parameter  | Type       | Description                                         |
|------------|------------|-----------------------------------------------------|
| `matcher`  | `?string`  | Regex to match tool names (null = match all tools)  |
| `hooks`    | `string[]` | Shell commands the CLI executes when the event fires |
| `timeout`  | `?int`     | Timeout in seconds per hook command (default: 60)   |