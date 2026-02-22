<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\GenericMessage;
use PHPUnit\Framework\TestCase;

class GenericMessageTest extends TestCase
{
    public function test_creates_generic_message(): void
    {
        $raw = ['type' => 'custom', 'data' => 'test'];
        $msg = new GenericMessage('custom', $raw);

        $this->assertSame('custom', $msg->type);
        $this->assertSame($raw, $msg->raw);
    }
}