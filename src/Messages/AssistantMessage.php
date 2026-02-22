<?php

namespace ClaudeAgentSDK\Messages;

use ClaudeAgentSDK\Content\ContentBlock;
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;

class AssistantMessage extends Message
{
    /** @var ContentBlock[] */
    public readonly array $content;

    public readonly ?string $id;
    public readonly ?string $model;
    public readonly ?array $usage;
    public readonly ?string $parentToolUseId;

    public function __construct(
        array   $content,
        ?string $id = null,
        ?string $model = null,
        ?array  $usage = null,
        ?string $parentToolUseId = null,
        array   $raw = [],
    )
    {
        parent::__construct('assistant', $raw);
        $this->content = $content;
        $this->id = $id;
        $this->model = $model;
        $this->usage = $usage;
        $this->parentToolUseId = $parentToolUseId;
    }

    public static function parse(array $data): static
    {
        $msg = $data['message'] ?? $data;
        $blocks = array_map(
            fn($b) => ContentBlock::fromArray($b),
            $msg['content'] ?? []
        );

        return new static(
            content: $blocks,
            id: $msg['id'] ?? $data['id'] ?? null,
            model: $msg['model'] ?? $data['model'] ?? null,
            usage: $msg['usage'] ?? $data['usage'] ?? null,
            parentToolUseId: $data['parent_tool_use_id'] ?? null,
            raw: $data,
        );
    }

    public function text(): string
    {
        $parts = [];
        foreach ($this->content as $block) {
            if ($block instanceof TextBlock) {
                $parts[] = $block->text;
            }
        }
        return implode("\n", $parts);
    }

    /**
     * @return ToolUseBlock[]
     */
    public function toolUses(): array
    {
        return array_values(
            array_filter(
                $this->content,
                fn($b) => $b instanceof ToolUseBlock
            )
        );
    }
}