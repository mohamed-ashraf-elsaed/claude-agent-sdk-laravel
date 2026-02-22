<?php

namespace ClaudeAgentSDK\Tests\Unit\Content;

use ClaudeAgentSDK\Content\TextBlock;
use PHPUnit\Framework\TestCase;

class TextBlockTest extends TestCase
{
    public function test_creates_text_block(): void
    {
        $block = new TextBlock('Hello world');

        $this->assertSame('text', $block->type);
        $this->assertSame('Hello world', $block->text);
    }

    public function test_casts_to_string(): void
    {
        $block = new TextBlock('Hello');

        $this->assertSame('Hello', (string)$block);
    }

    public function test_handles_empty_text(): void
    {
        $block = new TextBlock('');

        $this->assertSame('', $block->text);
        $this->assertSame('', (string)$block);
    }
}