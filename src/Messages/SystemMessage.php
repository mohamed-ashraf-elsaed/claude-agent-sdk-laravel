<?php

namespace ClaudeAgentSDK\Messages;

class SystemMessage extends Message
{
    public function __construct(
        public readonly string $subtype,
        public readonly ?string $sessionId = null,
        public readonly array $data = [],
        /** @var string[] Available tools in this session */
        public readonly array $tools = [],
        /** @var array[] MCP server statuses */
        public readonly array $mcpServers = [],
        public readonly ?string $model = null,
        public readonly ?string $permissionMode = null,
        /** @var array[] Available slash commands */
        public readonly array $slashCommands = [],
        public readonly ?string $outputStyle = null,
        public readonly ?string $apiKeySource = null,
        public readonly ?string $sessionCwd = null,
        array $raw = [],
    ) {
        parent::__construct('system', $raw);
    }

    public static function parse(array $data): static
    {
        return new static(
            subtype: $data['subtype'] ?? '',
            sessionId: $data['session_id'] ?? null,
            data: $data,
            tools: $data['tools'] ?? [],
            mcpServers: $data['mcp_servers'] ?? [],
            model: $data['model'] ?? null,
            permissionMode: $data['permissionMode'] ?? $data['permission_mode'] ?? null,
            slashCommands: $data['slash_commands'] ?? [],
            outputStyle: $data['output_style'] ?? null,
            apiKeySource: $data['apiKeySource'] ?? $data['api_key_source'] ?? null,
            sessionCwd: $data['cwd'] ?? null,
            raw: $data,
        );
    }

    public function isInit(): bool
    {
        return $this->subtype === 'init';
    }

    /**
     * Check if this is a compact boundary message.
     */
    public function isCompactBoundary(): bool
    {
        return $this->subtype === 'compact_boundary';
    }

    /**
     * Get compact metadata if this is a compact boundary message.
     */
    public function compactMetadata(): ?array
    {
        if (! $this->isCompactBoundary()) {
            return null;
        }

        return $this->data['compact_metadata'] ?? null;
    }
}
