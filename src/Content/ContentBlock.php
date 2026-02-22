<?php

namespace ClaudeAgentSDK\Content;

abstract class ContentBlock
{
    public function __construct(public readonly string $type) {}

    public static function fromArray(array $data): static
    {
        return match ($data['type'] ?? null) {
            'text' => new TextBlock($data['text'] ?? ''),
            'thinking' => new ThinkingBlock($data['thinking'] ?? '', $data['signature'] ?? ''),
            'tool_use' => new ToolUseBlock(
                id: $data['id'] ?? '',
                name: $data['name'] ?? '',
                input: $data['input'] ?? [],
            ),
            'tool_result' => new ToolResultBlock(
                toolUseId: $data['tool_use_id'] ?? '',
                content: $data['content'] ?? null,
                isError: $data['is_error'] ?? false,
            ),
            default => new TextBlock(json_encode($data)),
        };
    }
}