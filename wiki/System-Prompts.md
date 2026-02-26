# System Prompts

> Control how the agent behaves by providing custom instructions, using the Claude Code preset, or combining both for tailored agent workflows.

## Overview

System prompts define the agent's persona, constraints, and objectives. They are sent as the `--system-prompt` CLI flag and shape every response the agent produces during a query. Without a system prompt, the agent uses the Claude Code CLI's built-in defaults.

The SDK offers three approaches:

| Approach | Method | Best For |
|----------|--------|----------|
| Custom string | `systemPrompt('...')` | Full control over instructions |
| Claude Code preset | `useClaudeCodePrompt()` | Leveraging Claude Code's built-in coding prompt |
| Preset with additions | `useClaudeCodePrompt('...')` | Coding prompt plus your own rules |

---

## Custom String Prompt

Pass a plain string to `systemPrompt()` to fully replace the default behaviour with your own instructions.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->systemPrompt('You are a Laravel expert. Always follow PSR-12 coding standards. Explain your reasoning before making changes.')
    ->tools(['Read', 'Edit', 'Grep'])
    ->permission('dontAsk');

$result = ClaudeAgent::query('Refactor the User model to use value objects', $options);
```

The string is sent directly to the CLI as `--system-prompt "Your prompt here"`.

> **Tip:** Keep custom prompts focused. A concise, well-structured prompt outperforms a long, rambling one.

---

## Using the Claude Code Preset

`useClaudeCodePrompt()` sends a JSON payload that activates Claude Code's built-in coding-focused system prompt. This preset is optimized for reading, writing, and reasoning about code.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt()
    ->tools(['Read', 'Edit', 'Bash', 'Grep', 'Glob']);

$result = ClaudeAgent::query('Find and fix any N+1 queries in the controllers', $options);
```

Under the hood, this sends the following JSON as the system prompt:

```json
{"type": "preset", "preset": "claude_code"}
```

---

## Claude Code Preset with Additions

Pass a string to `useClaudeCodePrompt()` to keep the preset behaviour and append your own rules. This is the recommended approach for most agent tasks -- you get Claude Code's coding intelligence plus project-specific guidance.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('Also follow PSR-12 coding standards. Write PHPUnit tests for every change. Never modify migration files.')
    ->tools(['Read', 'Edit', 'Bash', 'Grep', 'Glob']);

$result = ClaudeAgent::query('Add soft deletes to the Post model', $options);
```

Under the hood, this sends:

```json
{"type": "preset", "preset": "claude_code", "append": "Also follow PSR-12 coding standards. Write PHPUnit tests for every change. Never modify migration files."}
```

> **Tip:** The `append` text is added after the preset prompt, so you can reference concepts the preset already establishes (like file editing conventions) without repeating them.

---

## Array Format

For maximum control, pass a raw array to `systemPrompt()`. This is useful when you need to construct the prompt payload dynamically.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->systemPrompt([
        'type' => 'preset',
        'preset' => 'claude_code',
        'append' => 'Follow the coding standards defined in .editorconfig.',
    ]);

$result = ClaudeAgent::query('Clean up the service layer', $options);
```

When an array is provided, it is JSON-encoded before being passed to the CLI. This gives you the same result as `useClaudeCodePrompt()` but with explicit control over every key.

---

## Prompt Engineering Tips for Agent Tasks

Writing effective system prompts for autonomous agents differs from writing prompts for chat. The agent will make decisions, use tools, and iterate without your input.

### Be Specific About the Task

Bad: `"You help with code."`
Good: `"You are a PHP code reviewer. Analyze files for security vulnerabilities, performance issues, and PSR-12 violations."`

### Set Boundaries

Tell the agent what it should not do. Autonomous agents can be overzealous without guardrails.

```
Do not modify test files. Do not delete any files.
Do not run destructive database commands.
Only edit files inside the app/ directory.
```

### Provide Context

Give the agent information about your project that it cannot discover from the code alone.

```
This is a Laravel 11 application using Inertia.js with Vue 3.
The database is PostgreSQL. We use Pest for testing.
API routes are versioned under routes/api/v2/.
```

### Define Success Criteria

Help the agent know when it is done.

```
Your task is complete when all modified files pass phpstan level 8
and all existing tests still pass.
```

---

## Common Prompt Templates

### Code Reviewer

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->systemPrompt('You are a senior PHP code reviewer. Analyze the given files for bugs, security vulnerabilities, performance issues, and coding standard violations. Provide actionable feedback. Do not modify any files.')
    ->tools(['Read', 'Grep', 'Glob']);

$result = ClaudeAgent::query('Review app/Services/PaymentService.php', $options);
```

### Migration Generator

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('Generate Laravel migrations following these rules: use descriptive names, always include down() methods, use appropriate column types, add indexes for foreign keys.')
    ->tools(['Read', 'Write', 'Bash', 'Glob'])
    ->permission('acceptEdits');

$result = ClaudeAgent::query('Create a migration for a comments table with polymorphic relations', $options);
```

### Test Writer

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('Write Pest PHP tests. Cover happy paths, edge cases, and error conditions. Use factories and fake data. Run the tests after writing them to verify they pass.')
    ->tools(['Read', 'Write', 'Edit', 'Bash', 'Grep', 'Glob'])
    ->permission('dontAsk');

$result = ClaudeAgent::query('Write tests for app/Services/OrderService.php', $options);
```

### API Documentation Generator

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->systemPrompt('You are a technical writer. Read the API route files and controllers, then generate OpenAPI 3.0 YAML documentation. Include request/response schemas, status codes, and example payloads.')
    ->tools(['Read', 'Grep', 'Glob', 'Write'])
    ->permission('acceptEdits');

$result = ClaudeAgent::query('Document all API endpoints in routes/api.php', $options);
```

### Bug Investigator

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('You are investigating a bug. Read logs, trace code paths, and identify root causes. Do not fix the bug -- only diagnose it and explain what is happening and why.')
    ->tools(['Read', 'Grep', 'Glob', 'Bash'])
    ->permission('dontAsk')
    ->maxTurns(20);

$result = ClaudeAgent::query('Users report that order totals are sometimes negative. Investigate.', $options);
```

---

## Combining with Tools

The system prompt and tool restrictions work together. The prompt tells the agent _what_ to do; the tool list controls _how_ it can act. A well-designed combination prevents the agent from exceeding its intended scope.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

// Read-only investigator: prompt says "do not modify", tools enforce it
$options = ClaudeAgentOptions::make()
    ->systemPrompt('Analyze the codebase for potential memory leaks. Report findings but do not modify any files.')
    ->tools(['Read', 'Grep', 'Glob'])   // no Write, Edit, or Bash
    ->permission('dontAsk');

$result = ClaudeAgent::query('Check for memory leaks in the queue workers', $options);
```

> **Warning:** If your prompt instructs the agent to write files but you have not included `Write` or `Edit` in the tools list, the agent will be unable to comply. Always ensure the prompt and tools are aligned.

When using [[Hooks]], the system prompt can reference hook behaviour:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Facades\ClaudeAgent;

$options = ClaudeAgentOptions::make()
    ->useClaudeCodePrompt('A linter runs automatically after every file edit. If the linter fails, fix the issues before moving on.')
    ->tools(['Read', 'Edit', 'Bash', 'Grep'])
    ->preToolUse('php artisan lint:check', '/Edit|Write/', 30)
    ->permission('dontAsk');

$result = ClaudeAgent::query('Refactor the AuthController to use form requests', $options);
```

---

## Next Steps

- [[Options Reference]] -- Full reference for every `ClaudeAgentOptions` method
- [[Hooks]] -- Run shell commands before or after tool use
- [[Subagents]] -- Define specialized agents with their own system prompts
- [[Structured Output]] -- Combine system prompts with JSON schema output
- [[Configuration]] -- Set application-wide defaults in `config/claude-agent.php`
