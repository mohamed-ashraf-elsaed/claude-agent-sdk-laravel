<?php

namespace ClaudeAgentSDK\Tests\Unit\Content;

use ClaudeAgentSDK\Content\ContentBlock;
use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ThinkingBlock;
use ClaudeAgentSDK\Content\ToolResultBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;
use PHPUnit\Framework\TestCase;

class ContentBlockFactoryTest extends TestCase
{
    public function test_parses_text_block(): void
    {
        $block = ContentBlock::fromArray(['type' => 'text', 'text' => 'Hello']);

        $this->assertInstanceOf(TextBlock::class, $block);
        $this->assertSame('Hello', $block->text);
    }

    public function test_parses_thinking_block(): void
    {
        $block = ContentBlock::fromArray([
            'type' => 'thinking',
            'thinking' => 'Hmm...',
            'signature' => 'sig',
        ]);

        $this->assertInstanceOf(ThinkingBlock::class, $block);
        $this->assertSame('Hmm...', $block->thinking);
        $this->assertSame('sig', $block->signature);
    }

    public function test_parses_tool_use_block(): void
    {
        $block = ContentBlock::fromArray([
            'type' => 'tool_use',
            'id' => 'tu_1',
            'name' => 'Read',
            'input' => ['path' => '/tmp'],
        ]);

        $this->assertInstanceOf(ToolUseBlock::class, $block);
        $this->assertSame('tu_1', $block->id);
        $this->assertSame('Read', $block->name);
        $this->assertSame(['path' => '/tmp'], $block->input);
    }

    public function test_parses_tool_result_block(): void
    {
        $block = ContentBlock::fromArray([
            'type' => 'tool_result',
            'tool_use_id' => 'tu_1',
            'content' => 'result data',
            'is_error' => true,
        ]);

        $this->assertInstanceOf(ToolResultBlock::class, $block);
        $this->assertSame('tu_1', $block->toolUseId);
        $this->assertSame('result data', $block->content);
        $this->assertTrue($block->isError);
    }

    public function test_unknown_type_returns_text_with_json(): void
    {
        $data = ['type' => 'custom', 'foo' => 'bar'];
        $block = ContentBlock::fromArray($data);

        $this->assertInstanceOf(TextBlock::class, $block);
        $this->assertSame(json_encode($data), $block->text);
    }

    public function test_missing_type_returns_text(): void
    {
        $block = ContentBlock::fromArray(['foo' => 'bar']);

        $this->assertInstanceOf(TextBlock::class, $block);
    }

    public function test_handles_missing_fields_gracefully(): void
    {
        $block = ContentBlock::fromArray(['type' => 'text']);
        $this->assertInstanceOf(TextBlock::class, $block);
        $this->assertSame('', $block->text);

        $block = ContentBlock::fromArray(['type' => 'tool_use']);
        $this->assertInstanceOf(ToolUseBlock::class, $block);
        $this->assertSame('', $block->id);

        $block = ContentBlock::fromArray(['type' => 'tool_result']);
        $this->assertInstanceOf(ToolResultBlock::class, $block);
        $this->assertSame('', $block->toolUseId);
    }
}