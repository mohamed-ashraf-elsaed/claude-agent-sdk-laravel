<?php

namespace ClaudeAgentSDK\Tests\Unit\Content;

use ClaudeAgentSDK\Content\ToolUseBlock;
use PHPUnit\Framework\TestCase;

class ToolUseBlockTest extends TestCase
{
    public function test_creates_tool_use_block(): void
    {
        $block = new ToolUseBlock('tu_123', 'Read', ['path' => '/foo']);

        $this->assertSame('tool_use', $block->type);
        $this->assertSame('tu_123', $block->id);
        $this->assertSame('Read', $block->name);
        $this->assertSame(['path' => '/foo'], $block->input);
    }

    public function test_default_empty_input(): void
    {
        $block = new ToolUseBlock('tu_1', 'Bash');

        $this->assertSame([], $block->input);
    }
}