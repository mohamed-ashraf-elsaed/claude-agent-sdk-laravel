# Configuration

The config file at `config/claude-agent.php` sets defaults for all queries. Any option passed directly to `query()` overrides the config default.

## Full Config Reference
```php
return [
    // Path to Claude Code CLI binary (null = auto-detect)
    'cli_path' => env('CLAUDE_AGENT_CLI_PATH', null),

    // Anthropic API key
    'api_key' => env('ANTHROPIC_API_KEY'),

    // Default model (null = CLI default)
    'model' => env('CLAUDE_AGENT_MODEL', null),

    // Permission mode: 'default', 'acceptEdits', 'dontAsk', 'bypassPermissions'
    'permission_mode' => env('CLAUDE_AGENT_PERMISSION_MODE', 'default'),

    // Working directory (null = Laravel base_path())
    'cwd' => env('CLAUDE_AGENT_CWD', null),

    // Default allowed tools
    'allowed_tools' => [],

    // Max conversation turns (null = unlimited)
    'max_turns' => env('CLAUDE_AGENT_MAX_TURNS', null),

    // Process timeout in seconds (null = no limit)
    'process_timeout' => env('CLAUDE_AGENT_TIMEOUT', null),

    // Max budget in USD per query (null = no limit)
    'max_budget_usd' => env('CLAUDE_AGENT_MAX_BUDGET_USD', null),

    // Max thinking tokens (null = CLI default)
    'max_thinking_tokens' => env('CLAUDE_AGENT_MAX_THINKING_TOKENS', null),

    // Custom API base URL (for self-hosted / compatible providers)
    'api_base_url' => env('ANTHROPIC_BASE_URL', null),

    // Custom auth token (for providers using a different auth header)
    'auth_token' => env('ANTHROPIC_AUTH_TOKEN', null),

    // Third-party provider flags
    'providers' => [
        'bedrock' => env('CLAUDE_CODE_USE_BEDROCK', false),
        'vertex' => env('CLAUDE_CODE_USE_VERTEX', false),
        'foundry' => env('CLAUDE_CODE_USE_FOUNDRY', false),
    ],
];
```

## Override Priority

1. Options passed to `query()` / `stream()` (highest)
2. Default options set via `withOptions()`
3. Config file values
4. Claude Code CLI defaults (lowest)