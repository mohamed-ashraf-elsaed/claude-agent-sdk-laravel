# Getting Started

> A 5-minute guided tutorial to run your first AI agent query in Laravel using the Claude Agent SDK.

Before starting, make sure you have completed [[Installation]] and have your `ANTHROPIC_API_KEY` set in `.env`.

## Your First Query

Open a route file or `artisan tinker` and run this minimal example:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('What PHP version is this project using?');

echo $result->text();    // The agent's full text response
echo $result->costUsd(); // Cost of the query in USD (e.g. 0.0032)
echo $result->turns();   // Number of agentic turns taken
```

| Line | What it does |
|------|--------------|
| `use ...ClaudeAgent` | Import the facade (auto-resolved by Laravel) |
| `ClaudeAgent::query(...)` | Send a prompt to Claude and wait for a complete response |
| `$result->text()` | Get the agent's final text answer |
| `$result->costUsd()` | Total API cost for the query |
| `$result->turns()` | How many agentic turns the agent used |

> **Note:** The agent runs inside your project directory by default, so it can read your files to answer questions.

## Understanding the Result

Every `query()` returns a `QueryResult` with these methods:

```php
$result->text();       // Full text response (string)
$result->costUsd();    // Total cost in USD (float)
$result->turns();      // Number of agentic turns (int)
$result->sessionId;    // Session ID for resuming later (string)
$result->isSuccess();  // True if the agent completed normally (bool)
$result->isError();    // True if the agent reported an error (bool)
```

> **Tip:** Always check `isError()` in production. The agent may complete without throwing an exception but still report a failure -- for example, if it hit a max-turns limit before finishing.

## Adding Options

Use `ClaudeAgentOptions` to control what the agent can do:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob'])   // Only allow these tools
    ->maxTurns(5)                        // Stop after 5 turns
    ->maxBudgetUsd(2.00)                 // Hard cost cap in USD
    ->cwd(base_path());                  // Working directory

$result = ClaudeAgent::query('List all route files and summarize them', $options);
```

> **Important:** Restricting tools is a best practice. Give the agent only the tools it needs. See [[Options-Reference]] for every available option.

## Streaming Responses

For long-running tasks, stream messages as they arrive instead of waiting:

```php
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream('Refactor the User model') as $message) {
    if ($message instanceof AssistantMessage) {
        echo $message->text();
    }
    if ($message instanceof ResultMessage) {
        echo "\nDone! Cost: $" . $message->totalCostUsd . "\n";
    }
}
```

The stream yields three message types: `SystemMessage` (session init), `AssistantMessage` (text and tool use), and `ResultMessage` (final summary with cost/turns). See [[Streaming]] for the full API.

## Using Dependency Injection

In controllers or services, type-hint `ClaudeAgentManager` instead of using the facade:

```php
use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

class CodeReviewController extends Controller
{
    public function review(Request $request, ClaudeAgentManager $agent)
    {
        $options = ClaudeAgentOptions::make()
            ->tools(['Read', 'Grep', 'Glob'])
            ->maxBudgetUsd(1.00);

        $result = $agent->query($request->input('prompt'), $options);

        return response()->json([
            'analysis' => $result->text(),
            'cost'     => $result->costUsd(),
            'turns'    => $result->turns(),
        ]);
    }
}
```

> **Tip:** Dependency injection makes testing easier -- you can mock `ClaudeAgentManager` in your test suite. See [[Testing-Your-Integration]].

## Common Patterns

### Read-Only Analysis

Safe for any environment. The agent can read and search but never modify files:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep', 'Glob']);

$result = ClaudeAgent::query('Find all N+1 query risks in app/Models/', $options);
```

### Code Modification

Allow the agent to edit code. Pair write tools with a permission mode:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Write', 'Edit', 'Bash'])
    ->permission('acceptEdits')
    ->cwd(base_path());

$result = ClaudeAgent::query('Add created_by column to the posts migration', $options);
```

> **Warning:** `acceptEdits` lets the agent write and edit files without confirmation. Use with care, and always set a `maxBudgetUsd()` limit.

### Web Research

Let the agent fetch URLs or search the web:

```php
$options = ClaudeAgentOptions::make()
    ->tools(['WebFetch', 'WebSearch']);

$result = ClaudeAgent::query('Summarize the Laravel 12 release notes', $options);
```

## Next Steps

You now know the core workflow. Explore these pages to go deeper:

- [[Options-Reference]] -- every option and CLI flag
- [[Streaming]] -- real-time output, `streamCollect()`, and broadcasting
- [[Session-Management]] -- resume and fork conversations
- [[Hooks]] -- run callbacks on tool-use events
- [[Error-Handling]] -- exceptions and result-level errors
- [[Structured-Output]] -- get JSON responses matching a schema
- [[Subagents]] -- delegate tasks to child agents
- [[MCP-Servers]] -- connect external tool servers
