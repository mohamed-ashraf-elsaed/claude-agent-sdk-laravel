<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\PartialAssistantMessage;
use PHPUnit\Framework\TestCase;

class PartialAssistantMessageTest extends TestCase
{
    public function test_parse_stream_event(): void
    {
        $msg = PartialAssistantMessage::parse([
            'type' => 'stream_event',
            'event' => ['type' => 'content_block_delta', 'delta' => ['text' => 'Hello']],
        ]);

        $this->assertSame('stream_event', $msg->type);
        $this->assertInstanceOf(Message::class, $msg);
    }

    public function test_parse_with_event_data(): void
    {
        $eventData = [
            'type' => 'content_block_delta',
            'index' => 0,
            'delta' => [
                'type' => 'text_delta',
                'text' => 'Here is the answer.',
            ],
        ];

        $msg = PartialAssistantMessage::parse([
            'type' => 'stream_event',
            'event' => $eventData,
        ]);

        $this->assertSame($eventData, $msg->event);
    }

    public function test_parse_with_parent_tool_use_id(): void
    {
        $msg = PartialAssistantMessage::parse([
            'type' => 'stream_event',
            'event' => ['type' => 'content_block_start'],
            'parent_tool_use_id' => 'toolu_01ABC123',
        ]);

        $this->assertSame('toolu_01ABC123', $msg->parentToolUseId);
    }

    public function test_parse_with_uuid_and_session(): void
    {
        $msg = PartialAssistantMessage::parse([
            'type' => 'stream_event',
            'event' => ['type' => 'message_start'],
            'uuid' => 'msg-uuid-456',
            'session_id' => 'sess_abc123',
        ]);

        $this->assertSame('msg-uuid-456', $msg->uuid);
        $this->assertSame('sess_abc123', $msg->sessionId);
    }

    public function test_parse_minimal(): void
    {
        $data = ['type' => 'stream_event'];
        $msg = PartialAssistantMessage::parse($data);

        $this->assertSame('stream_event', $msg->type);
        $this->assertNull($msg->event);
        $this->assertNull($msg->parentToolUseId);
        $this->assertNull($msg->uuid);
        $this->assertNull($msg->sessionId);
        $this->assertSame($data, $msg->raw);
    }

    public function test_raw_data_preserved(): void
    {
        $data = [
            'type' => 'stream_event',
            'event' => ['type' => 'content_block_delta', 'delta' => ['text' => 'x']],
            'uuid' => 'u1',
            'session_id' => 's1',
            'parent_tool_use_id' => 'tu1',
            'extra_field' => 'should be in raw',
        ];

        $msg = PartialAssistantMessage::parse($data);

        $this->assertSame($data, $msg->raw);
    }
}
