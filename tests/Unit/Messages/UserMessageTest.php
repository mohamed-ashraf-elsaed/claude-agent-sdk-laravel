<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

class UserMessageTest extends TestCase
{
    public function test_parse_with_message_wrapper(): void
    {
        $msg = UserMessage::parse([
            'type' => 'user',
            'message' => ['content' => 'Hello Claude'],
            'uuid' => 'uuid-123',
        ]);

        $this->assertSame('user', $msg->type);
        $this->assertSame('Hello Claude', $msg->content);
        $this->assertSame('uuid-123', $msg->uuid);
    }

    public function test_parse_without_message_wrapper(): void
    {
        $msg = UserMessage::parse([
            'type' => 'user',
            'content' => 'Hello directly',
        ]);

        $this->assertSame('Hello directly', $msg->content);
        $this->assertNull($msg->uuid);
    }

    public function test_parse_with_array_content(): void
    {
        $content = [['type' => 'text', 'text' => 'Hi']];
        $msg = UserMessage::parse([
            'message' => ['content' => $content],
        ]);

        $this->assertSame($content, $msg->content);
    }

    public function test_raw_data_preserved(): void
    {
        $data = ['type' => 'user', 'content' => 'test', 'extra' => 'field'];
        $msg = UserMessage::parse($data);

        $this->assertSame($data, $msg->raw);
    }
}