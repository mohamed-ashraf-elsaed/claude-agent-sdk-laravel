# Claude Agent SDK for Laravel

Build AI agents with Claude Code as a library in your Laravel applications. This SDK wraps the Claude Code CLI to give your app access to file operations, bash commands, code editing, web search, subagents, and more.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Claude Code CLI installed (`npm install -g @anthropic-ai/claude-code`)
- Anthropic API key

## Installation

```bash
composer require your-vendor/claude-agent-sdk-laravel
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

echo $result->text();           // Final text result
echo $result->costUsd();        // Cost in USD
echo $result->sessionId;        // Session ID for resuming
```

### With Options (Fluent API)

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
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
use ClaudeAgentSDK\Facades\ClaudeAgent;
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

### Stream with Callback Collection

```php
$result = ClaudeAgent::streamCollect(
    prompt: 'Create a REST API for products',
    onMessage: function ($message) {
        if ($message instanceof AssistantMessage) {
            // Log or broadcast each message as it arrives
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
    ->permission('acceptEdits')          // 'default', 'acceptEdits', 'dontAsk', 'bypassPermissions'
    ->maxTurns(15)
    ->cwd('/path/to/project')
    ->env('MY_VAR', 'value')
    ->settingSources(['project'])        // Load .claude/ settings
    ->useClaudeCodePrompt('Also follow PSR-12.');  // Claude Code system prompt + append
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

// Claude Code preset
$options->useClaudeCodePrompt();

// Claude Code preset with additions
$options->useClaudeCodePrompt('Follow PSR-12 and use strict types.');
```

## Subagents

Define specialized agents that Claude delegates tasks to:

```php
use ClaudeAgentSDK\Agents\AgentDefinition;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])  // Task tool required for subagents
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

$result = ClaudeAgent::query(
    'Review the auth module for security issues',
    $options,
);
```

## Structured Output

Get validated JSON responses:

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

$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['@modelcontextprotocol/server-database'],
        env: ['DB_URL' => config('database.url')],
    ))
    ->tools(['mcp__database__query', 'Read']);

$result = ClaudeAgent::query('Show me the latest users', $options);
```

## Working with Results

```php
$result = ClaudeAgent::query('Analyze this codebase');

// Basic info
$result->text();              // Final text
$result->isSuccess();         // bool
$result->isError();           // bool
$result->costUsd();           // float|null
$result->turns();             // int
$result->durationMs();        // int
$result->sessionId;           // string|null

// Messages
$result->messages;            // All Message objects
$result->assistantMessages(); // AssistantMessage[] only
$result->fullText();          // Concatenated text from all assistant messages
$result->toolUses();          // All ToolUseBlock objects
$result->structured();        // Structured output array (if outputFormat was set)
```

## Default Options via Config

Set defaults in `config/claude-agent.php` or `.env`:

```env
CLAUDE_AGENT_MODEL=claude-sonnet-4-5-20250929
CLAUDE_AGENT_PERMISSION_MODE=acceptEdits
CLAUDE_AGENT_MAX_TURNS=10
CLAUDE_AGENT_CWD=/var/www/project
CLAUDE_AGENT_TIMEOUT=300
```

Options passed to `query()` override config defaults.

## Error Handling

```php
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = ClaudeAgent::query('Do something');
} catch (CliNotFoundException $e) {
    // Claude Code CLI not installed
} catch (ProcessException $e) {
    echo $e->exitCode;
    echo $e->stderr;
} catch (ClaudeAgentException $e) {
    // General SDK error
}
```

## File Structure

```
src/
├── Agents/
│   └── AgentDefinition.php
├── Content/
│   ├── ContentBlock.php
│   ├── TextBlock.php
│   ├── ThinkingBlock.php
│   ├── ToolResultBlock.php
│   └── ToolUseBlock.php
├── Exceptions/
│   ├── ClaudeAgentException.php
│   ├── CliNotFoundException.php
│   ├── JsonParseException.php
│   └── ProcessException.php
├── Facades/
│   └── ClaudeAgent.php
├── Hooks/
│   ├── HookEvent.php
│   └── HookMatcher.php
├── Messages/
│   ├── AssistantMessage.php
│   ├── GenericMessage.php
│   ├── Message.php
│   ├── ResultMessage.php
│   ├── SystemMessage.php
│   └── UserMessage.php
├── Options/
│   └── ClaudeAgentOptions.php
├── Tools/
│   └── McpServerConfig.php
├── Transport/
│   └── ProcessTransport.php
├── ClaudeAgentManager.php
├── ClaudeAgentServiceProvider.php
└── QueryResult.php
```

## Publishing to Packagist

1. Create a GitHub repository with this structure
2. Update `composer.json` with your vendor name
3. Tag a release: `git tag v1.0.0 && git push --tags`
4. Submit at [packagist.org/packages/submit](https://packagist.org/packages/submit)

## License

MIT