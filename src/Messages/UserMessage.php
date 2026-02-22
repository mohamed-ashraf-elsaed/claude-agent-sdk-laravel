<?php

namespace ClaudeAgentSDK\Messages;

class UserMessage extends Message
{
    public function __construct(
        public readonly string|array $content,
        public readonly ?string      $uuid = null,
        array                        $raw = [],
    ) {
        parent::__construct('user', $raw);
    }

    public static function parse(array $data): static
    {
        $msg = $data['message'] ?? $data;
        return new static(
            content: $msg['content'] ?? '',
            uuid: $data['uuid'] ?? null,
            raw: $data,
        );
    }
}
