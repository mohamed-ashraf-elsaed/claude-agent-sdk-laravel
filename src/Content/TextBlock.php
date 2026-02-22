<?php

namespace ClaudeAgentSDK\Content;

class TextBlock extends ContentBlock
{
    public function __construct(public readonly string $text)
    {
        parent::__construct('text');
    }

    public function __toString(): string
    {
        return $this->text;
    }
}