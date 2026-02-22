<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\SystemMessage;
use PHPUnit\Framework\TestCase;

class SystemMessageTest extends TestCase
{
    public function test_parse_init_message(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_abc',
        ]);

        $this->assertSame('system', $msg->type);
        $this->assertSame('init', $msg->subtype);
        $this->assertSame('sess_abc', $msg->sessionId);
        $this->assertTrue($msg->isInit());
    }

    public function test_parse_non_init_message(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'other',
        ]);

        $this->assertFalse($msg->isInit());
        $this->assertNull($msg->sessionId);
    }

    public function test_data_preserved(): void
    {
        $data = ['type' => 'system', 'subtype' => 'init', 'extra' => 'field'];
        $msg = SystemMessage::parse($data);

        $this->assertSame($data, $msg->data);
        $this->assertSame($data, $msg->raw);
    }
}