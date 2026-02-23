<?php

namespace ClaudeAgentSDK\Hooks;

use JsonSerializable;

class HookMatcher implements JsonSerializable
{
    /**
     * @param string|null $matcher  Regex pattern to match tool names (null = match all)
     * @param string[]    $hooks    Shell commands the CLI executes when the event fires
     * @param int|null    $timeout  Timeout in seconds for each hook command (default: 60)
     */
    public function __construct(
        public readonly ?string $matcher = null,
        public readonly array $hooks = [],
        public readonly ?int $timeout = null,
    ) {}

    /**
     * Create a matcher that runs a single shell command.
     */
    public static function command(string $command, ?string $matcher = null, ?int $timeout = null): static
    {
        return new static(
            matcher: $matcher,
            hooks: [$command],
            timeout: $timeout,
        );
    }

    /**
     * Create a matcher that runs a PHP script.
     */
    public static function phpScript(string $scriptPath, ?string $matcher = null, ?int $timeout = null): static
    {
        return new static(
            matcher: $matcher,
            hooks: [PHP_BINARY . ' ' . escapeshellarg($scriptPath)],
            timeout: $timeout,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'matcher' => $this->matcher,
            'hooks' => ! empty($this->hooks) ? $this->hooks : null,
            'timeout' => $this->timeout,
        ], fn($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}