<?php


namespace ClaudeAgentSDK\Content;

class ThinkingBlock extends ContentBlock
{
    public function __construct(
        public readonly string $thinking,
        public readonly string $signature = '',
    ) {
        parent::__construct('thinking');
    }
}