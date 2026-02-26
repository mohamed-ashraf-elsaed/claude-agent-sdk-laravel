# Subagents

> Delegate specialized tasks to independent agents, each with their own prompt, tools, and optional model, orchestrated by a parent agent through the Task tool.

## Overview

Subagents let you break complex work into focused units. The parent agent receives a prompt and decides -- based on its own judgment -- when to delegate portions of the work to named subagents via the built-in **Task** tool. Each subagent runs in its own context with its own set of allowed tools, a dedicated system prompt, and optionally a different model.

The SDK serializes subagent definitions as the `--agents` CLI JSON argument. The parent sees each subagent as a callable tool and invokes them by name through the Task tool.

> **Important:** The parent agent MUST have the `Task` tool in its allowed tools list. Without it, the parent cannot delegate to any subagent.

## Defining a Subagent

Use the `AgentDefinition` class and the fluent `agent()` method on `ClaudeAgentOptions`:

```php
use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])  // Task is REQUIRED
    ->agent('security-reviewer', new AgentDefinition(
        description: 'Security code review specialist',
        prompt: 'You are a security expert. Analyze PHP code for vulnerabilities including SQL injection, XSS, CSRF, and authentication issues.',
        tools: ['Read', 'Grep', 'Glob'],
        model: 'sonnet',
    ));

$result = ClaudeAgent::query('Review the auth module for security issues', $options);
```

The `AgentDefinition` constructor accepts four parameters:

```php
public function __construct(
    public readonly string  $description,   // Short summary shown to the parent
    public readonly string  $prompt,         // System prompt for the subagent
    public readonly ?array  $tools = null,   // Allowed tools (null = inherit parent's)
    public readonly ?string $model = null,   // Model override (null = use parent's model)
)
```

## AgentDefinition Properties

| Property      | Type      | Required | Description |
|---------------|-----------|----------|-------------|
| `description` | `string`  | Yes      | A concise summary the parent reads to decide when to delegate to this subagent. |
| `prompt`      | `string`  | Yes      | The system prompt that defines the subagent's role, expertise, and instructions. |
| `tools`       | `?array`  | No       | List of tool names available to the subagent. When `null`, the subagent inherits the parent's tools. |
| `model`       | `?string` | No       | Override the model for this subagent (e.g. `'haiku'`, `'sonnet'`). When `null`, the parent's model is used. |

## From Array

You can define subagents from plain arrays, which is useful for dynamic or database-stored definitions:

```php
use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Pass an array directly to agent() -- it calls AgentDefinition::fromArray() internally
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])
    ->agent('reviewer', [
        'description' => 'Code reviewer',
        'prompt'      => 'Review for best practices and clean code.',
        'tools'       => ['Read', 'Grep'],
        'model'       => 'sonnet',
    ]);

// Or use the static factory explicitly
$definition = AgentDefinition::fromArray($config);
$options->agent('reviewer', $definition);
```

The `fromArray()` method expects an associative array with `description` and `prompt` as required keys, plus optional `tools` and `model`. The `toArray()` method on `AgentDefinition` produces the inverse, filtering out `null` values.

## Multiple Subagents

Chain multiple `agent()` calls to build a team of specialists:

```php
use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Write', 'Edit', 'Grep', 'Glob', 'Bash', 'Task'])
    ->agent('security-reviewer', new AgentDefinition(
        description: 'Security code review specialist',
        prompt: 'You are a security expert. Analyze PHP code for vulnerabilities including SQL injection, XSS, CSRF, and authentication issues. Report findings with severity levels.',
        tools: ['Read', 'Grep', 'Glob'],
        model: 'sonnet',
    ))
    ->agent('test-writer', new AgentDefinition(
        description: 'PHPUnit test writer',
        prompt: 'Write comprehensive PHPUnit tests. Use Mockery for mocking. Follow the Arrange-Act-Assert pattern. Ensure edge cases are covered.',
        tools: ['Read', 'Write', 'Bash'],
    ))
    ->agent('documenter', new AgentDefinition(
        description: 'Documentation specialist',
        prompt: 'Write clear PHPDoc blocks and markdown documentation. Follow PSR-5 conventions. Include @param, @return, and @throws tags.',
        tools: ['Read', 'Write'],
        model: 'haiku',
    ));

$result = ClaudeAgent::query(
    'Review the auth module: check security, write tests, and update docs',
    $options,
);

echo $result->text();
```

The parent agent reads each subagent's `description` and decides which ones to invoke and in what order. In this example, the parent might first delegate to `security-reviewer`, then pass findings to `test-writer`, and finally ask `documenter` to update the docblocks.

## Identifying Subagent Responses

When streaming, messages originating from a subagent have a non-null `parentToolUseId` property on `AssistantMessage`. This lets you distinguish parent messages from delegated work:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream($prompt, $options) as $message) {
    if ($message instanceof AssistantMessage) {
        if ($message->parentToolUseId) {
            // This message came from a subagent
            echo "[Subagent] " . $message->text() . "\n";
        } else {
            // This is the parent agent speaking
            echo "[Parent] " . $message->text() . "\n";
        }
    }

    if ($message instanceof ResultMessage) {
        echo "Cost: \${$message->totalCostUsd}\n";
    }
}
```

> **Tip:** The `parentToolUseId` value is the tool-use ID of the Task invocation that spawned the subagent. You can correlate it with `ToolUseBlock` instances from the parent to track which delegation produced which output.

## Real-World Patterns

### Code Review Pipeline

Run security, style, and performance reviews in parallel, then synthesize results:

```php
use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Task'])
    ->agent('security', new AgentDefinition(
        description: 'Finds security vulnerabilities',
        prompt: 'Audit PHP code for OWASP Top 10 vulnerabilities. Output a markdown table of findings.',
        tools: ['Read', 'Grep', 'Glob'],
    ))
    ->agent('style', new AgentDefinition(
        description: 'Checks coding standards and style',
        prompt: 'Review code for PSR-12 compliance, naming conventions, and clean code principles.',
        tools: ['Read', 'Grep'],
        model: 'haiku',
    ))
    ->agent('performance', new AgentDefinition(
        description: 'Identifies performance bottlenecks',
        prompt: 'Analyze code for N+1 queries, missing indexes, unnecessary allocations, and caching opportunities.',
        tools: ['Read', 'Grep', 'Glob'],
    ));
```

### Feature Development Team

An architect plans, an implementer writes code, and a test writer validates:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Write', 'Edit', 'Bash', 'Grep', 'Glob', 'Task'])
    ->agent('architect', new AgentDefinition(
        description: 'Plans architecture and file structure',
        prompt: 'Design the architecture. Output a plan with file paths, class names, and relationships. Do not write code.',
        tools: ['Read', 'Grep', 'Glob'],
    ))
    ->agent('implementer', new AgentDefinition(
        description: 'Writes production code',
        prompt: 'Implement code following the architecture plan. Use Laravel conventions, type hints, and return types.',
        tools: ['Read', 'Write', 'Edit', 'Bash'],
    ))
    ->agent('test-writer', new AgentDefinition(
        description: 'Writes tests for new code',
        prompt: 'Write feature and unit tests using PHPUnit and Mockery. Aim for full branch coverage.',
        tools: ['Read', 'Write', 'Bash'],
    ));
```

### Research and Report

A researcher gathers information, an analyst processes it, and a writer produces the final output:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob', 'Bash', 'Task'])
    ->agent('researcher', new AgentDefinition(
        description: 'Gathers information from the codebase',
        prompt: 'Search the codebase to gather all relevant information about the topic. Output raw findings.',
        tools: ['Read', 'Grep', 'Glob'],
        model: 'haiku',
    ))
    ->agent('analyst', new AgentDefinition(
        description: 'Analyzes findings and identifies patterns',
        prompt: 'Analyze the raw findings. Identify patterns, inconsistencies, and areas of concern. Output a structured analysis.',
        tools: ['Read', 'Grep'],
        model: 'sonnet',
    ))
    ->agent('writer', new AgentDefinition(
        description: 'Produces polished reports',
        prompt: 'Write a clear, actionable report from the analysis. Use markdown formatting with headings, tables, and code examples.',
        tools: ['Read'],
    ));
```

## Model Selection Strategy

Choose models strategically to balance cost and capability:

| Task Type | Recommended Model | Rationale |
|-----------|-------------------|-----------|
| Data collection, file searching, simple extraction | `haiku` | Fast and cheap for high-volume, low-complexity work |
| Code analysis, pattern recognition, review | `sonnet` | Good balance of capability and cost |
| Architecture decisions, complex reasoning | `opus` (or default) | Highest capability for nuanced judgment |
| Documentation, formatting, boilerplate | `haiku` | Straightforward output that does not require deep reasoning |

```php
->agent('collector', new AgentDefinition(
    description: 'Collects data from files',
    prompt: 'Read files and extract the requested data. Output as JSON.',
    tools: ['Read', 'Grep', 'Glob'],
    model: 'haiku',               // Cheap: just reading and extracting
))
->agent('analyzer', new AgentDefinition(
    description: 'Deep analysis of collected data',
    prompt: 'Perform thorough analysis of the data.',
    tools: ['Read', 'Grep'],
    model: 'sonnet',              // Mid-tier: needs reasoning ability
))
```

> **Note:** When `model` is `null`, the subagent inherits the parent's model. Set it explicitly only when you want a different cost/capability tradeoff.

## Limitations

- **Single level of delegation.** Subagents cannot spawn their own subagents. The Task tool is only available to the parent.
- **Cost accumulates.** Each subagent consumes its own tokens. A parent with three subagents can cost significantly more than a single agent. Use [[Budget Control]] and `maxBudgetUsd()` to set spending limits.
- **Separate threads.** Each subagent runs in its own conversation thread. It receives the parent's delegation message but does not see the full parent conversation history or other subagents' outputs unless the parent explicitly passes that information.
- **No direct communication.** Subagents cannot talk to each other. The parent must relay information between them.

## Next Steps

- [[Hooks]] -- Run shell commands before or after tool use, including subagent tool invocations
- [[Streaming]] -- Process subagent messages in real time with `parentToolUseId` detection
- [[Options Reference]] -- Full list of `ClaudeAgentOptions` fluent methods
- [[Working with Messages]] -- Understanding `AssistantMessage`, `ToolUseBlock`, and content types
