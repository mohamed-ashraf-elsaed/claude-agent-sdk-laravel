<?php

namespace ClaudeAgentSDK\Tests\Unit\Content;

use ClaudeAgentSDK\Content\ToolResultBlock;
use PHPUnit\Framework\TestCase;

class ToolResultBlockTest extends TestCase
{
    public function test_creates_tool_result_block(): void
    {
        $block = new ToolResultBlock('tu_123', 'file contents', false);

        $this->assertSame('tool_result', $block->type);
        $this->assertSame('tu_123', $block->toolUseId);
        $this->assertSame('file contents', $block->content);
        $this->assertFalse($block->isError);
    }

    public function test_error_result(): void
    {
        $block = new ToolResultBlock('tu_1', 'Not found', true);

        $this->assertTrue($block->isError);
    }

    public function test_null_content(): void
    {
        $block = new ToolResultBlock('tu_1');

        $this->assertNull($block->content);
        $this->assertFalse($block->isError);
    }

    public function test_array_content(): void
    {
        $block = new ToolResultBlock('tu_1', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $block->content);
    }
}