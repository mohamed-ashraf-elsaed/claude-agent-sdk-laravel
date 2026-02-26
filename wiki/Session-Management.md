# Session Management

> Maintain conversation context across multiple queries by resuming, forking, or continuing sessions.

Every Claude Code conversation has a session ID. The SDK captures this ID from the CLI output, letting you store it and use it later to resume the conversation with full context. This is essential for multi-step workflows where each query builds on the previous one.

## Session ID Lifecycle

1. You send a `query()` or `stream()` call.
2. The CLI starts a session and emits a `SystemMessage` with a `session_id`.
3. The agent works and eventually emits a `ResultMessage`, also containing the `session_id`.
4. The SDK extracts the session ID and exposes it as `$result->sessionId`.
5. You store the ID and pass it to a future query via `resume()`.

## Getting the Session ID

The session ID is available on the `QueryResult` object after any query:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;

$result = ClaudeAgent::query('Analyze the User model');

$sessionId = $result->sessionId; // e.g. "a1b2c3d4-5678-..."
```

When streaming, the session ID appears on both the `SystemMessage` (at init) and the `ResultMessage` (at completion):

```php
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\ResultMessage;

foreach (ClaudeAgent::stream('Analyze the User model') as $message) {
    if ($message instanceof SystemMessage && $message->isInit()) {
        // Available immediately when the session starts
        $sessionId = $message->sessionId;
    }

    if ($message instanceof ResultMessage) {
        // Also available at the end
        $sessionId = $message->sessionId;
    }
}
```

The SDK resolves the session ID in this priority order:
1. `SystemMessage` with `session_id` (sent at initialization)
2. `ResultMessage` with `session_id` (sent at completion)

## Resuming a Session

Pass a stored session ID to `resume()` to continue a conversation with full context:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// First query -- starts a new session
$result = ClaudeAgent::query('Read and understand the User model');
$sessionId = $result->sessionId;

// Store the session ID
cache()->put("agent_session:{$userId}", $sessionId, now()->addHours(2));

// Later -- resume with full context
$sessionId = cache()->get("agent_session:{$userId}");

$result = ClaudeAgent::query(
    'Now add email verification to it',
    ClaudeAgentOptions::make()->resume($sessionId),
);
```

The resumed session has access to everything from the original conversation -- files read, analysis performed, and decisions made. Claude does not need to re-read files or re-analyze code.

## Forking a Session

Forking creates a branch from an existing session, letting you try different approaches from the same starting point without affecting the original:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Base analysis
$result = ClaudeAgent::query('Analyze the payment module');
$sessionId = $result->sessionId;

// Try approach A (fork from the base)
$approachA = ClaudeAgent::query(
    'Refactor using the Strategy pattern',
    ClaudeAgentOptions::make()->resume($sessionId, fork: true),
);

// Try approach B (fork from the same base)
$approachB = ClaudeAgent::query(
    'Refactor using the Service pattern',
    ClaudeAgentOptions::make()->resume($sessionId, fork: true),
);

// Compare the results
$responseA = $approachA->text();
$responseB = $approachB->text();
```

Each fork is an independent session. Changes in fork A do not affect fork B, and neither affects the original session. Think of it as git branching for conversations:

```
[Base Session] ── analyze payment module
       ├── [Fork A] ── Strategy pattern refactor
       └── [Fork B] ── Service pattern refactor
```

## Continuing Last Conversation

`continueConversation()` resumes the most recent conversation without needing a session ID:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$result = ClaudeAgent::query(
    'Continue where we left off',
    ClaudeAgentOptions::make()->continueConversation(),
);
```

> **Warning:** `continueConversation()` uses the `--continue` CLI flag, which resumes the last session for the current CLI user on the current machine. This is only suitable for single-user, local-development scenarios. Do **not** use it in multi-user applications -- use `resume()` with an explicit session ID instead.

## Storing Session IDs

### Laravel Cache

```php
// Store
cache()->put("agent_session:{$userId}", $result->sessionId, now()->addHours(2));

// Retrieve
$sessionId = cache()->get("agent_session:{$userId}");
```

### Database

```php
// Migration concept
Schema::table('users', function (Blueprint $table) {
    $table->string('agent_session_id')->nullable();
    $table->timestamp('agent_session_at')->nullable();
});

// Store
$user->update([
    'agent_session_id' => $result->sessionId,
    'agent_session_at' => now(),
]);

// Retrieve
$sessionId = $user->agent_session_id;
```

### Redis

```php
use Illuminate\Support\Facades\Redis;

// Store with TTL
Redis::setex("agent_session:{$userId}", 7200, $result->sessionId);

// Retrieve
$sessionId = Redis::get("agent_session:{$userId}");
```

### User Session

```php
// Store in the HTTP session
session()->put('agent_session_id', $result->sessionId);

// Retrieve
$sessionId = session()->get('agent_session_id');
```

> **Tip:** Choose the storage mechanism that matches your session lifetime needs. Cache and Redis are good for ephemeral sessions. Database is better when you need sessions to survive deployments and cache clears.

## Session Lifecycle

- **Storage:** Session data is stored on disk by the Claude Code CLI, typically in `~/.claude/sessions/`. The SDK does not manage this storage -- it only stores and passes the session ID.
- **Expiration:** Sessions do not have a hard expiration, but old sessions may be cleaned up by the CLI over time. Store session IDs with a reasonable TTL (e.g. 2-4 hours) so your application does not attempt to resume stale sessions.
- **Cleanup:** The CLI manages its own session files. You only need to clean up the session IDs you store in your application (cache, database, etc.).
- **Size:** Session context grows with each turn. Very long sessions may hit context limits, at which point the CLI may compact the context automatically.

## Multi-Step Agent Workflows

Chain multiple queries across a session to build complex, multi-step agent workflows:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

// Step 1: Analyze
$result = ClaudeAgent::query(
    'Analyze the Order model and its relationships for performance issues',
    ClaudeAgentOptions::make()->tools(['Read', 'Grep', 'Glob']),
);
$sessionId = $result->sessionId;

// Step 2: Suggest fixes (building on Step 1 context)
$result = ClaudeAgent::query(
    'Suggest specific fixes for the issues you found',
    ClaudeAgentOptions::make()
        ->tools(['Read', 'Grep'])
        ->resume($sessionId),
);

// Step 3: Implement the fixes (building on Steps 1-2)
$result = ClaudeAgent::query(
    'Implement the top 3 most impactful fixes',
    ClaudeAgentOptions::make()
        ->tools(['Read', 'Write', 'Edit', 'Bash'])
        ->permission('acceptEdits')
        ->resume($sessionId),
);

// Step 4: Verify (building on Steps 1-3)
$result = ClaudeAgent::query(
    'Run the tests and verify the fixes work correctly',
    ClaudeAgentOptions::make()
        ->tools(['Bash', 'Read'])
        ->resume($sessionId),
);

if ($result->isError()) {
    Log::warning('Agent verification failed', ['text' => $result->text()]);
}
```

> **Tip:** Notice how each step can have different tools and permissions. The analysis steps are read-only, the implementation step allows edits, and the verification step only needs Bash and Read. Restricting tools per step is a security best practice. See [[Options-Reference]] for all available tool options.

## Next Steps

- [[Getting-Started]] -- core workflow and `QueryResult` basics
- [[Streaming]] -- get the session ID early via `SystemMessage`
- [[Structured-Output]] -- combine sessions with schema-validated output
- [[Options-Reference]] -- full reference for `resume()`, `continueConversation()`, and more
- [[Error-Handling]] -- handle errors in multi-step workflows
