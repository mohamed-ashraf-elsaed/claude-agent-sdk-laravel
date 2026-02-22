<?php

namespace ClaudeAgentSDK\Messages;

abstract class Message
{
    public readonly string $type;

    public function __construct(string $type, public readonly array $raw = [])
    {
        $this->type = $type;
    }

    public static function fromJson(array $data): static
    {
        return match ($data['type'] ?? null) {
            'user' => UserMessage::parse($data),
            'assistant' => AssistantMessage::parse($data),
            'system' => SystemMessage::parse($data),
            'result' => ResultMessage::parse($data),
            default => new GenericMessage($data['type'] ?? 'unknown', $data),
        };
    }
}