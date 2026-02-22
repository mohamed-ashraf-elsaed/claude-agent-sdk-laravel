# Subagents

Subagents are specialized agents that Claude can delegate tasks to. Each subagent has its own prompt, tools, and optional model.

## Defining Subagents
```php
use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])  // Task tool is REQUIRED
    ->agent('security-reviewer', new AgentDefinition(
        description: 'Security code review specialist',
        prompt: 'You are a security expert. Analyze PHP code for vulnerabilities including SQL injection, XSS, CSRF, and authentication issues.',
        tools: ['Read', 'Grep', 'Glob'],
        model: 'sonnet',
    ))
    ->agent('test-writer', new AgentDefinition(
        description: 'PHPUnit test writer',
        prompt: 'Write comprehensive PHPUnit tests. Use Mockery for mocking. Follow AAA pattern.',
        tools: ['Read', 'Write', 'Bash'],
    ))
    ->agent('documenter', new AgentDefinition(
        description: 'Documentation specialist',
        prompt: 'Write clear PHPDoc blocks and markdown documentation.',
        tools: ['Read', 'Write'],
    ));

$result = ClaudeAgent::query(
    'Review the auth module: check security, write tests, and update docs',
    $options,
);
```

## From Array
```php
$options->agent('reviewer', [
    'description' => 'Code reviewer',
    'prompt' => 'Review for best practices',
    'tools' => ['Read', 'Grep'],
    'model' => 'sonnet',
]);
```

## Identifying Subagent Responses

In streaming mode, subagent messages include `parentToolUseId`:
```php
foreach (ClaudeAgent::stream($prompt, $options) as $msg) {
    if ($msg instanceof AssistantMessage && $msg->parentToolUseId) {
        echo "[Subagent] " . $msg->text();
    }
}
```