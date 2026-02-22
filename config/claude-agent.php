<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Claude Code CLI Path
    |--------------------------------------------------------------------------
    |
    | Path to the Claude Code CLI binary. If null, the SDK will search
    | for 'claude' in your system PATH.
    |
    */
    'cli_path' => env('CLAUDE_AGENT_CLI_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your Anthropic API key. This is passed as an environment variable
    | to the CLI process.
    |
    */
    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default Claude model to use for queries.
    |
    */
    'model' => env('CLAUDE_AGENT_MODEL', null),

    /*
    |--------------------------------------------------------------------------
    | Default Permission Mode
    |--------------------------------------------------------------------------
    |
    | Controls how tools require permission.
    | Options: 'default', 'acceptEdits', 'dontAsk', 'bypassPermissions'
    |
    */
    'permission_mode' => env('CLAUDE_AGENT_PERMISSION_MODE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default Working Directory
    |--------------------------------------------------------------------------
    |
    | The default working directory for the agent. If null, uses the
    | Laravel base path.
    |
    */
    'cwd' => env('CLAUDE_AGENT_CWD', null),

    /*
    |--------------------------------------------------------------------------
    | Default Allowed Tools
    |--------------------------------------------------------------------------
    |
    | Tools the agent is allowed to use by default.
    |
    */
    'allowed_tools' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Max Turns
    |--------------------------------------------------------------------------
    |
    | Maximum number of conversation turns before stopping.
    |
    */
    'max_turns' => env('CLAUDE_AGENT_MAX_TURNS', null),

    /*
    |--------------------------------------------------------------------------
    | Process Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for the CLI process. Null = no limit.
    |
    */
    'process_timeout' => env('CLAUDE_AGENT_TIMEOUT', null),

    /*
    |--------------------------------------------------------------------------
    | Third-Party Providers
    |--------------------------------------------------------------------------
    |
    | Configure third-party API providers (Bedrock, Vertex, Foundry).
    |
    */
    'providers' => [
        'bedrock' => env('CLAUDE_CODE_USE_BEDROCK', false),
        'vertex' => env('CLAUDE_CODE_USE_VERTEX', false),
        'foundry' => env('CLAUDE_CODE_USE_FOUNDRY', false),
    ],
];