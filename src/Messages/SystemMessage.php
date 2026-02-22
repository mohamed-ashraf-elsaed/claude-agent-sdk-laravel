<?php

namespace ClaudeAgentSDK\Messages;

class SystemMessage extends Message
{
    public function __construct(
        public readonly string $subtype,
        public readonly ?string $sessionId = null,
        public readonly array $data = [],
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
            raw: $data,
        );
    }

    public function isInit(): bool
    {
        return $this->subtype === 'init';
    }
}