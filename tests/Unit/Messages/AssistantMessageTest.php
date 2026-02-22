<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\AssistantMessage;
use PHPUnit\Framework\TestCase;

class AssistantMessageTest extends TestCase
{
    public function test_parse_with_text_content(): void
    {
        $msg = AssistantMessage::parse([
            'type' => 'assistant',
            'message' => [
                'id' => 'msg_1',
                'model' => 'claude-sonnet-4-5-20250929',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello!'],
                    ['type' => 'text', 'text' => 'How are you?'],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ],
        ]);

        $this->assertSame('assistant', $msg->type);
        $this->assertSame('msg_1', $msg->id);
        $this->assertSame('claude-sonnet-4-5-20250929', $msg->model);
        $this->assertCount(2, $msg->content);
        $this->assertSame("Hello!\nHow are you?", $msg->text());
    }

    public function test_parse_with_tool_use(): void
    {
        $msg = AssistantMessage::parse([
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'Let me read that.'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'Read', 'input' => ['path' => '/app']],
                    ['type' => 'tool_use', 'id' => 'tu_2', 'name' => 'Bash', 'input' => ['cmd' => 'ls']],
                ],
            ],
        ]);

        $this->assertSame('Let me read that.', $msg->text());

        $tools = $msg->toolUses();
        $this->assertCount(2, $tools);
        $this->assertSame('tu_1', $tools[0]->id);
        $this->assertSame('Read', $tools[0]->name);
        $this->assertSame('tu_2', $tools[1]->id);
    }

    public function test_tool_uses_returns_sequential_keys(): void
    {
        $msg = AssistantMessage::parse([
            'message' => [
                'content' => [
                    ['type' => 'text', 'text' => 'x'],
                    ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'Read', 'input' => []],
                ],
            ],
        ]);

        $tools = $msg->toolUses();
        $this->assertSame([0], array_keys($tools));
    }

    public function test_parse_with_parent_tool_use_id(): void
    {
        $msg = AssistantMessage::parse([
            'type' => 'assistant',
            'parent_tool_use_id' => 'parent_tu_1',
            'message' => ['content' => []],
        ]);

        $this->assertSame('parent_tu_1', $msg->parentToolUseId);
    }

    public function test_empty_content(): void
    {
        $msg = AssistantMessage::parse([
            'message' => ['content' => []],
        ]);

        $this->assertSame('', $msg->text());
        $this->assertEmpty($msg->toolUses());
    }

    public function test_parse_without_message_wrapper(): void
    {
        $msg = AssistantMessage::parse([
            'id' => 'msg_2',
            'model' => 'claude-sonnet-4-5-20250929',
            'content' => [['type' => 'text', 'text' => 'Direct']],
        ]);

        $this->assertSame('msg_2', $msg->id);
        $this->assertSame('Direct', $msg->text());
    }
}