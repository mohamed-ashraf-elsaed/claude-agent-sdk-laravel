<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\GenericMessage;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\Messages\UserMessage;
use PHPUnit\Framework\TestCase;

class MessageFactoryTest extends TestCase
{
    public function test_parses_user_message(): void
    {
        $msg = Message::fromJson([
            'type' => 'user',
            'message' => ['content' => 'Hello'],
            'uuid' => 'u1',
        ]);

        $this->assertInstanceOf(UserMessage::class, $msg);
    }

    public function test_parses_assistant_message(): void
    {
        $msg = Message::fromJson([
            'type' => 'assistant',
            'message' => [
                'id' => 'msg_1',
                'model' => 'claude-sonnet-4-5-20250929',
                'content' => [['type' => 'text', 'text' => 'Hi']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ],
        ]);

        $this->assertInstanceOf(AssistantMessage::class, $msg);
    }

    public function test_parses_system_message(): void
    {
        $msg = Message::fromJson([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_1',
        ]);

        $this->assertInstanceOf(SystemMessage::class, $msg);
    }

    public function test_parses_result_message(): void
    {
        $msg = Message::fromJson([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Done',
            'session_id' => 'sess_1',
            'total_cost_usd' => 0.01,
        ]);

        $this->assertInstanceOf(ResultMessage::class, $msg);
    }

    public function test_unknown_type_returns_generic(): void
    {
        $msg = Message::fromJson(['type' => 'custom_event', 'data' => 'foo']);

        $this->assertInstanceOf(GenericMessage::class, $msg);
        $this->assertSame('custom_event', $msg->type);
    }

    public function test_missing_type_returns_generic(): void
    {
        $msg = Message::fromJson(['data' => 'foo']);

        $this->assertInstanceOf(GenericMessage::class, $msg);
        $this->assertSame('unknown', $msg->type);
    }
}