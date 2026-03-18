<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\PartialAssistantMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use PHPUnit\Framework\TestCase;

class MessageFactoryNewTypesTest extends TestCase
{
    public function test_stream_event_dispatches_to_partial(): void
    {
        $msg = Message::fromJson([
            'type' => 'stream_event',
            'event' => [
                'type' => 'content_block_delta',
                'index' => 0,
                'delta' => ['type' => 'text_delta', 'text' => 'Hello world'],
            ],
            'uuid' => 'msg-stream-001',
            'session_id' => 'sess_abc',
        ]);

        $this->assertInstanceOf(PartialAssistantMessage::class, $msg);
        $this->assertSame('stream_event', $msg->type);

        /** @var PartialAssistantMessage $msg */
        $this->assertSame('msg-stream-001', $msg->uuid);
        $this->assertSame('sess_abc', $msg->sessionId);
        $this->assertSame('content_block_delta', $msg->event['type']);
    }

    public function test_stream_event_with_parent_tool_use(): void
    {
        $msg = Message::fromJson([
            'type' => 'stream_event',
            'event' => ['type' => 'content_block_start'],
            'parent_tool_use_id' => 'toolu_01ABC',
        ]);

        $this->assertInstanceOf(PartialAssistantMessage::class, $msg);

        /** @var PartialAssistantMessage $msg */
        $this->assertSame('toolu_01ABC', $msg->parentToolUseId);
    }

    public function test_stream_event_minimal(): void
    {
        $msg = Message::fromJson([
            'type' => 'stream_event',
        ]);

        $this->assertInstanceOf(PartialAssistantMessage::class, $msg);

        /** @var PartialAssistantMessage $msg */
        $this->assertNull($msg->event);
        $this->assertNull($msg->parentToolUseId);
        $this->assertNull($msg->uuid);
        $this->assertNull($msg->sessionId);
    }

    public function test_compact_boundary_dispatches_to_system(): void
    {
        $msg = Message::fromJson([
            'type' => 'system',
            'subtype' => 'compact_boundary',
            'session_id' => 'sess_compact',
            'compact_metadata' => [
                'original_tokens' => 80000,
                'compacted_tokens' => 12000,
                'reason' => 'context_window',
            ],
        ]);

        $this->assertInstanceOf(SystemMessage::class, $msg);
        $this->assertSame('system', $msg->type);

        /** @var SystemMessage $msg */
        $this->assertSame('compact_boundary', $msg->subtype);
        $this->assertTrue($msg->isCompactBoundary());
        $this->assertFalse($msg->isInit());

        $metadata = $msg->compactMetadata();
        $this->assertNotNull($metadata);
        $this->assertSame(80000, $metadata['original_tokens']);
        $this->assertSame(12000, $metadata['compacted_tokens']);
    }

    public function test_system_init_still_works(): void
    {
        $msg = Message::fromJson([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_init',
            'tools' => ['Read', 'Write'],
            'model' => 'claude-sonnet-4-5-20250929',
        ]);

        $this->assertInstanceOf(SystemMessage::class, $msg);

        /** @var SystemMessage $msg */
        $this->assertTrue($msg->isInit());
        $this->assertFalse($msg->isCompactBoundary());
        $this->assertSame(['Read', 'Write'], $msg->tools);
        $this->assertSame('claude-sonnet-4-5-20250929', $msg->model);
    }
}
