# Session Management

Sessions let you maintain conversation context across multiple queries.

## Resume a Session
```php
// First query — starts a new session
$result1 = ClaudeAgent::query('Read and understand the User model');
$sessionId = $result1->sessionId;

// Store session ID (database, cache, session, etc.)
cache()->put("agent_session:{$userId}", $sessionId, now()->addHours(2));

// Later — resume with full context
$sessionId = cache()->get("agent_session:{$userId}");

$result2 = ClaudeAgent::query(
    'Now add email verification to it',
    ClaudeAgentOptions::make()->resume($sessionId),
);
```

## Fork a Session

Forking creates a branch from an existing session, allowing you to try different approaches without affecting the original:
```php
$result = ClaudeAgent::query('Analyze the payment module');
$sessionId = $result->sessionId;

// Try approach A
$approachA = ClaudeAgent::query(
    'Refactor using the Strategy pattern',
    ClaudeAgentOptions::make()->resume($sessionId, fork: true),
);

// Try approach B (from the same starting point)
$approachB = ClaudeAgent::query(
    'Refactor using the Service pattern',
    ClaudeAgentOptions::make()->resume($sessionId, fork: true),
);
```

## Session ID Sources

The session ID is extracted from messages in this priority:
1. `SystemMessage` with `session_id` (sent at initialization)
2. `ResultMessage` with `session_id` (sent at completion)