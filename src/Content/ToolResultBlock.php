<?php


namespace ClaudeAgentSDK\Content;

class ToolResultBlock extends ContentBlock
{
    public function __construct(
        public readonly string $toolUseId,
        public readonly string|array|null $content = null,
        public readonly bool $isError = false,
    ) {
        parent::__construct('tool_result');
    }
}