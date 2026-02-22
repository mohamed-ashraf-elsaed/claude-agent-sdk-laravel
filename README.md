# Claude Agent SDK for Laravel

[![Tests](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/actions)
[![Latest Version](https://img.shields.io/packagist/v/mohamed-ashraf-elsaed/claude-agent-sdk-laravel.svg)](https://packagist.org/packages/mohamed-ashraf-elsaed/claude-agent-sdk-laravel)
[![License](https://img.shields.io/packagist/l/mohamed-ashraf-elsaed/claude-agent-sdk-laravel.svg)](LICENSE)

Build AI agents with Claude Code as a library in your Laravel applications. This SDK wraps the Claude Code CLI to give your app access to file operations, bash commands, code editing, web search, subagents, and more.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Claude Code CLI (`npm install -g @anthropic-ai/claude-code`)
- Anthropic API key

## Installation
```bash
composer require mohamed-ashraf-elsaed/claude-agent-sdk-laravel
```

Publish the config:
```bash
php artisan vendor:publish --tag=claude-agent-config
```

Add your API key to `.env`:
```env
ANTHROPIC_API_KEY=your-api-key
```

## Quick Start

### Simple Query
```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('What files are in this directory?');

echo $result->text();       // Final text result
echo $result->costUsd();    // Cost in USD
echo $result->sessionId;    // Session ID for resuming
```

### With Options (Fluent API)
```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Edit', 'Bash', 'Grep', 'Glob'])
    ->permission('acceptEdits')
    ->maxTurns(10)
    ->cwd('/path/to/project');

$result = ClaudeAgent::query('Find and fix the bug in auth.php', $options);

if ($result->isSuccess()) {
    echo $result->text();
}
```

### Streaming Responses
```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream('Refactor the User model') as $message) {
    if ($message instanceof AssistantMessage) {
        echo $message->text();
    }

    if ($message instanceof ResultMessage) {
        echo "\nDone! Cost: $" . $message->totalCostUsd;
    }
}
```

### Stream with Callback
```php
$result = ClaudeAgent::streamCollect(
    prompt: 'Create a REST API for products',
    onMessage: function ($message) {
        if ($message instanceof AssistantMessage) {
            Log::info($message->text());
        }
    },
    options: ClaudeAgentOptions::make()->tools(['Read', 'Write', 'Bash']),
);

echo $result->text();
```

## Options Reference

### Fluent Builder
```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Write', 'Edit', 'Bash', 'Grep', 'Glob'])
    ->disallow(['WebFetch'])
    ->model('claude-sonnet-4-5-20250929')
    ->permission('acceptEdits')
    ->maxTurns(15)
    ->cwd('/path/to/project')
    ->env('MY_VAR', 'value')
    ->settingSources(['project'])
    ->useClaudeCodePrompt('Also follow PSR-12.');
```

### From Array
```php
$options = ClaudeAgentOptions::fromArray([
    'allowed_tools' => ['Read', 'Bash'],
    'permission_mode' => 'bypassPermissions',
    'max_turns' => 5,
]);
```

### System Prompts
```php
// Custom string
$options->systemPrompt('You are a Laravel expert. Always use Eloquent.');

// Claude Code preset (includes default agent behavior)
$options->useClaudeCodePrompt();

// Claude Code preset with additions
$options->useClaudeCodePrompt('Follow PSR-12 and use strict types.');
```

### Permission Modes

| Mode               | Behavior                                    |
|--------------------|---------------------------------------------|
| `default`          | Ask for permission on each tool use         |
| `acceptEdits`      | Auto-accept file edits, ask for others      |
| `dontAsk`          | Don't ask but log decisions                 |
| `bypassPermissions`| Skip all permission checks                  |

## Subagents

Define specialized agents that Claude delegates tasks to:
```php
use ClaudeAgentSDK\Agents\AgentDefinition;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])
    ->agent('security-reviewer', new AgentDefinition(
        description: 'Security code review specialist',
        prompt: 'You are a security expert. Find vulnerabilities in PHP/Laravel code.',
        tools: ['Read', 'Grep', 'Glob'],
        model: 'sonnet',
    ))
    ->agent('test-writer', new AgentDefinition(
        description: 'PHPUnit test writer',
        prompt: 'Write comprehensive PHPUnit tests for Laravel applications.',
        tools: ['Read', 'Write', 'Bash'],
    ));

$result = ClaudeAgent::query('Review the auth module for security issues', $options);
```

## Structured Output

Get validated JSON responses matching a schema:
```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'issues' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'file' => ['type' => 'string'],
                        'line' => ['type' => 'number'],
                        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        'description' => ['type' => 'string'],
                    ],
                    'required' => ['file', 'severity', 'description'],
                ],
            ],
            'total' => ['type' => 'number'],
        ],
        'required' => ['issues', 'total'],
    ]);

$result = ClaudeAgent::query('Find all TODO comments in src/', $options);
$data = $result->structured(); // Validated array matching schema
```

## Session Resumption

Continue conversations across multiple queries:
```php
// First query
$result = ClaudeAgent::query('Read the auth module');
$sessionId = $result->sessionId;

// Resume later with full context
$result2 = ClaudeAgent::query(
    'Now find all places that call it',
    ClaudeAgentOptions::make()->resume($sessionId),
);

// Fork a session to try different approaches
$result3 = ClaudeAgent::query(
    'Try refactoring it with a different pattern',
    ClaudeAgentOptions::make()->resume($sessionId, fork: true),
);
```

## MCP Servers

Connect external tools via Model Context Protocol:
```php
use ClaudeAgentSDK\Tools\McpServerConfig;

// Stdio transport
$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['@modelcontextprotocol/server-database'],
        env: ['DB_URL' => config('database.url')],
    ))
    ->tools(['mcp__database__query', 'Read']);

$result = ClaudeAgent::query('Show me the latest users', $options);

// SSE transport
$options = ClaudeAgentOptions::make()
    ->mcpServer('api', McpServerConfig::sse(
        url: 'http://localhost:3000/mcp',
        headers: ['Authorization' => 'Bearer ' . config('services.mcp.token')],
    ));
```

## Working with Results
```php
$result = ClaudeAgent::query('Analyze this codebase');

// Basic info
$result->text();              // Final text result
$result->isSuccess();         // bool — true if subtype is 'success'
$result->isError();           // bool — true if error or no result
$result->costUsd();           // float|null — total cost in USD
$result->turns();             // int — number of conversation turns
$result->durationMs();        // int — total duration in milliseconds
$result->sessionId;           // string|null — for session resumption

// Messages
$result->messages;            // All Message objects
$result->assistantMessages(); // AssistantMessage[] only
$result->fullText();          // Concatenated text from all assistant messages
$result->toolUses();          // All ToolUseBlock objects across messages
$result->structured();        // Structured output array (if outputFormat set)
```

## Working with Messages
```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\UserMessage;

foreach (ClaudeAgent::stream('Do something') as $message) {
    match (true) {
        $message instanceof SystemMessage => handleSystem($message),
        $message instanceof AssistantMessage => handleAssistant($message),
        $message instanceof ResultMessage => handleResult($message),
        default => null,
    };
}

function handleAssistant(AssistantMessage $msg): void
{
    // Text content
    echo $msg->text();

    // Tool calls made by Claude
    foreach ($msg->toolUses() as $toolUse) {
        echo "Tool: {$toolUse->name}, Input: " . json_encode($toolUse->input);
    }

    // Metadata
    echo $msg->model;            // Model used
    echo $msg->id;               // Message ID
    echo $msg->parentToolUseId;  // If this is a subagent response
}
```

## Default Options via Config

Set defaults in `config/claude-agent.php` or `.env`:
```env
CLAUDE_AGENT_MODEL=claude-sonnet-4-5-20250929
CLAUDE_AGENT_PERMISSION_MODE=acceptEdits
CLAUDE_AGENT_MAX_TURNS=10
CLAUDE_AGENT_CWD=/var/www/project
CLAUDE_AGENT_TIMEOUT=300
CLAUDE_AGENT_CLI_PATH=/usr/local/bin/claude
```

Options passed to `query()` override config defaults.

## Advanced: Sandbox & Plugins
```php
// Run in a sandboxed environment
$options = ClaudeAgentOptions::make()
    ->sandbox(['type' => 'docker', 'image' => 'php:8.3-cli']);

// Load a local plugin
$options = ClaudeAgentOptions::make()
    ->plugin('/path/to/my-plugin');
```

## Error Handling
```php
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = ClaudeAgent::query('Do something');
} catch (CliNotFoundException $e) {
    // Claude Code CLI not installed
    // $e->getMessage() includes install instructions
} catch (ProcessException $e) {
    echo $e->exitCode;   // Process exit code
    echo $e->stderr;     // Standard error output
} catch (ClaudeAgentException $e) {
    // General SDK error
}
```

## Testing
```bash
composer test
```

To run with coverage:
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## License

MIT — see [LICENSE](LICENSE) for details.
