<?php

namespace ClaudeAgentSDK\Messages;

/**
 * Represents a streaming partial message event.
 *
 * Emitted when includePartialMessages is enabled.
 * Contains raw stream event data for real-time streaming updates.
 */
class PartialAssistantMessage extends Message
{
    public function __construct(
        public readonly ?array $event = null,
        public readonly ?string $parentToolUseId = null,
        public readonly ?string $uuid = null,
        public readonly ?string $sessionId = null,
        array $raw = [],
    ) {
        parent::__construct('stream_event', $raw);
    }

    public static function parse(array $data): static
    {
        return new static(
            event: $data['event'] ?? null,
            parentToolUseId: $data['parent_tool_use_id'] ?? null,
            uuid: $data['uuid'] ?? null,
            sessionId: $data['session_id'] ?? null,
            raw: $data,
        );
    }
}
