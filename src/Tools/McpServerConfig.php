<?php

namespace ClaudeAgentSDK\Tools;

use JsonSerializable;

class McpServerConfig implements JsonSerializable
{
    public function __construct(
        public readonly string  $command,
        public readonly array   $args = [],
        public readonly array   $env = [],
        public readonly ?string $type = null,
    )
    {
    }

    public static function stdio(string $command, array $args = [], array $env = []): static
    {
        return new static(command: $command, args: $args, env: $env, type: 'stdio');
    }

    public static function sse(string $url, array $headers = []): static
    {
        return new static(command: $url, args: [], env: $headers, type: 'sse');
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        if ($this->type === 'sse') {
            return array_filter([
                'type' => 'sse',
                'url' => $this->command,
                'headers' => !empty($this->env) ? $this->env : null,
            ], fn($v) => $v !== null);
        }

        return array_filter([
            'command' => $this->command,
            'args' => !empty($this->args) ? $this->args : null,
            'env' => !empty($this->env) ? $this->env : null,
        ], fn($v) => $v !== null);
    }
}