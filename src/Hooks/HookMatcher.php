<?php


namespace ClaudeAgentSDK\Hooks;

class HookMatcher
{
    /**
     * @param string|null $matcher  Regex pattern to match tool names
     * @param callable[]  $hooks    Callback functions
     * @param int|null    $timeout  Timeout in seconds (default: 60)
     */
    public function __construct(
        public readonly ?string $matcher = null,
        public readonly array $hooks = [],
        public readonly ?int $timeout = null,
    ) {}
}