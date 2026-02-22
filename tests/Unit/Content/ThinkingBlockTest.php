<?php

namespace ClaudeAgentSDK\Tests\Unit\Content;

use ClaudeAgentSDK\Content\ThinkingBlock;
use PHPUnit\Framework\TestCase;

class ThinkingBlockTest extends TestCase
{
    public function test_creates_thinking_block(): void
    {
        $block = new ThinkingBlock('Let me think...', 'sig123');

        $this->assertSame('thinking', $block->type);
        $this->assertSame('Let me think...', $block->thinking);
        $this->assertSame('sig123', $block->signature);
    }

    public function test_default_empty_signature(): void
    {
        $block = new ThinkingBlock('thinking content');

        $this->assertSame('', $block->signature);
    }
}