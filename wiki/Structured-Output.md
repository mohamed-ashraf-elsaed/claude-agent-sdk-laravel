# Structured Output

> Get validated JSON responses that conform to a JSON Schema -- ideal for API responses, data extraction, and classification tasks.

When you need predictable, machine-readable output from the agent, structured output lets you define a JSON Schema that the CLI enforces. The agent's response is guaranteed to match your schema, and the SDK parses it into a PHP array automatically.

## How It Works

1. You define a JSON Schema and pass it via `outputFormat()`.
2. The SDK sends the schema to the CLI as the `--output-format-json-schema` argument.
3. Claude generates a response that conforms to the schema.
4. The SDK parses the result and exposes it through `$result->structured()`.

Use structured output when you need to:
- Return typed data from an API endpoint
- Extract specific fields from documents or code
- Classify or categorize content into predefined buckets
- Feed agent output into downstream PHP logic

## Basic Usage

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
        ],
        'required' => ['summary', 'risk_level'],
    ]);

$result = ClaudeAgent::query('Assess the security posture of the auth module', $options);

$data = $result->structured(); // ['summary' => '...', 'risk_level' => 'medium']
```

## Schema Examples

### Simple Object

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Glob'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'files_analyzed' => ['type' => 'number'],
            'main_language' => ['type' => 'string'],
        ],
        'required' => ['summary', 'files_analyzed', 'main_language'],
    ]);
```

### Array of Objects

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])
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
                        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['file', 'severity', 'message'],
                ],
            ],
        ],
        'required' => ['issues'],
    ]);

$result = ClaudeAgent::query('Find all SQL injection risks in app/Http/', $options);

foreach ($result->structured()['issues'] as $issue) {
    echo "[{$issue['severity']}] {$issue['file']}: {$issue['message']}\n";
}
```

### Nested Objects

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'project' => ['type' => 'string'],
            'architecture' => [
                'type' => 'object',
                'properties' => [
                    'pattern' => ['type' => 'string'],
                    'layers' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'directory' => ['type' => 'string'],
                                'file_count' => ['type' => 'number'],
                            ],
                            'required' => ['name', 'directory'],
                        ],
                    ],
                ],
                'required' => ['pattern', 'layers'],
            ],
        ],
        'required' => ['project', 'architecture'],
    ]);
```

### Enum Fields

Enums constrain the agent to a fixed set of values -- useful for classification:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'category' => ['type' => 'string', 'enum' => ['feature', 'bugfix', 'refactor', 'docs', 'test']],
            'complexity' => ['type' => 'string', 'enum' => ['trivial', 'small', 'medium', 'large']],
            'summary' => ['type' => 'string'],
        ],
        'required' => ['category', 'complexity', 'summary'],
    ]);

$result = ClaudeAgent::query('Classify the changes in the latest commit', $options);
// ['category' => 'bugfix', 'complexity' => 'small', 'summary' => '...']
```

## Accessing Results

`structured()` returns the parsed array, while `text()` returns the raw string:

```php
$result = ClaudeAgent::query('Analyze the User model', $options);

// Parsed array conforming to your schema (or null if unavailable)
$data = $result->structured();

// Raw text response as a string
$raw = $result->text();

// Always check for null before using structured output
if ($data = $result->structured()) {
    return response()->json($data);
}

return response()->json(['error' => 'No structured output'], 500);
```

> **Note:** `structured()` returns `null` when the result message has no `structured_output` field -- for example, if the agent errored out before completing. Always check for null in production code.

## Mapping to PHP DTOs

You can map the structured array to a typed PHP class for better IDE support and validation:

```php
class CodeAnalysis
{
    public function __construct(
        public readonly string $summary,
        public readonly int $filesAnalyzed,
        public readonly string $riskLevel,
        public readonly array $issues,
    ) {}

    public static function fromStructured(array $data): self
    {
        return new self(
            summary: $data['summary'],
            filesAnalyzed: $data['files_analyzed'],
            riskLevel: $data['risk_level'],
            issues: $data['issues'] ?? [],
        );
    }
}

// Usage
$result = ClaudeAgent::query('Audit the codebase for security issues', $options);

if ($data = $result->structured()) {
    $analysis = CodeAnalysis::fromStructured($data);
    echo $analysis->summary;
    echo $analysis->riskLevel; // IDE autocompletion works here
}
```

## Combining with Streaming

Structured output is only available on the final `QueryResult`, not on individual streamed messages. Use `streamCollect()` to stream progress while still getting the structured result at the end:

```php
use ClaudeAgentSDK\Messages\AssistantMessage;

$result = ClaudeAgent::streamCollect(
    prompt: 'Analyze all controllers for REST compliance',
    onMessage: function ($message) {
        if ($message instanceof AssistantMessage) {
            broadcast(new AgentProgress($message->text()));
        }
    },
    options: $options,
);

// Structured output is available after streaming completes
$data = $result->structured();
```

> **Important:** Calling `structured()` on individual `AssistantMessage` objects will not work. The structured output is only populated on the `ResultMessage` and surfaced through `QueryResult::structured()`.

## Common Patterns

### Code Analysis Report

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'files_analyzed' => ['type' => 'number'],
            'issues' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'file' => ['type' => 'string'],
                        'line' => ['type' => 'number'],
                        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['file', 'severity', 'message'],
                ],
            ],
        ],
        'required' => ['summary', 'files_analyzed', 'issues'],
    ]);

$result = ClaudeAgent::query('Audit app/Services/ for error handling issues', $options);
```

### Data Extraction from Files

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'endpoints' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string', 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']],
                        'uri' => ['type' => 'string'],
                        'controller' => ['type' => 'string'],
                        'middleware' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['method', 'uri', 'controller'],
                ],
            ],
        ],
        'required' => ['endpoints'],
    ]);

$result = ClaudeAgent::query('Extract all API routes from routes/api.php', $options);
```

### Classification / Categorization

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'files' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['model', 'controller', 'service', 'repository', 'helper', 'other']],
                        'test_coverage' => ['type' => 'string', 'enum' => ['none', 'partial', 'full']],
                    ],
                    'required' => ['path', 'type', 'test_coverage'],
                ],
            ],
        ],
        'required' => ['files'],
    ]);

$result = ClaudeAgent::query('Classify all PHP files in app/ by type and test coverage', $options);
```

## Schema Tips

- **Always use `required`.** Without it, Claude may omit fields, and your downstream code will break on missing keys.
- **Use `enum` for constrained values.** This prevents free-text in fields that should have fixed options (severity levels, categories, statuses).
- **Keep schemas simple.** Deeply nested schemas with many optional fields can reduce output quality. Prefer flat structures where possible.
- **Limit array sizes with descriptions.** While JSON Schema does not enforce array length at the CLI level, adding a `description` like `"Return at most 10 items"` guides the agent.

> **Warning:** Very complex schemas (deeply nested, many properties, or large enums) can degrade response quality. If you find the agent struggling, simplify the schema or break the task into smaller queries with simpler schemas.

## Next Steps

- [[Options-Reference]] -- all available options including `outputFormat()`
- [[Streaming]] -- combine structured output with `streamCollect()`
- [[Session-Management]] -- chain structured queries across sessions
- [[Error-Handling]] -- handle cases where `structured()` returns null
