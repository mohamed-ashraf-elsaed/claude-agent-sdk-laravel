<?php

namespace ClaudeAgentSDK\Facades;

use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\QueryResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static QueryResult query(string $prompt, ClaudeAgentOptions|array|null $options = null)
 * @method static \Generator stream(string $prompt, ClaudeAgentOptions|array|null $options = null)
 * @method static ClaudeAgentManager withOptions(ClaudeAgentOptions|array $options)
 *
 * @see \ClaudeAgentSDK\ClaudeAgentManager
 */
class ClaudeAgent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'claude-agent';
    }
}