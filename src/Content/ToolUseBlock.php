<?php


namespace ClaudeAgentSDK\Content;

class ToolUseBlock extends ContentBlock
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input = [],
    ) {
        parent::__construct('tool_use');
    }
}