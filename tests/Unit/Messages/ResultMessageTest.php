<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\ResultMessage;
use PHPUnit\Framework\TestCase;

class ResultMessageTest extends TestCase
{
    public function test_parse_success_result(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'All done!',
            'session_id' => 'sess_1',
            'duration_ms' => 5000,
            'duration_api_ms' => 4500,
            'is_error' => false,
            'num_turns' => 3,
            'total_cost_usd' => 0.025,
            'usage' => ['input_tokens' => 100, 'output_tokens' => 200],
        ]);

        $this->assertSame('result', $msg->type);
        $this->assertSame('success', $msg->subtype);
        $this->assertSame('All done!', $msg->result);
        $this->assertSame('sess_1', $msg->sessionId);
        $this->assertSame(5000, $msg->durationMs);
        $this->assertSame(4500, $msg->durationApiMs);
        $this->assertFalse($msg->isError);
        $this->assertSame(3, $msg->numTurns);
        $this->assertSame(0.025, $msg->totalCostUsd);
        $this->assertTrue($msg->isSuccess());
    }

    public function test_parse_error_result(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error',
            'result' => 'Something failed',
            'is_error' => true,
        ]);

        $this->assertFalse($msg->isSuccess());
        $this->assertTrue($msg->isError);
    }

    public function test_parse_with_structured_output(): void
    {
        $structured = ['issues' => [], 'total' => 0];
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'structured_output' => $structured,
        ]);

        $this->assertSame($structured, $msg->structuredOutput);
    }

    public function test_defaults_for_missing_fields(): void
    {
        $msg = ResultMessage::parse(['type' => 'result']);

        $this->assertSame('', $msg->subtype);
        $this->assertNull($msg->result);
        $this->assertNull($msg->sessionId);
        $this->assertSame(0, $msg->durationMs);
        $this->assertSame(0, $msg->durationApiMs);
        $this->assertFalse($msg->isError);
        $this->assertSame(0, $msg->numTurns);
        $this->assertNull($msg->totalCostUsd);
        $this->assertNull($msg->usage);
        $this->assertNull($msg->structuredOutput);
    }

    public function test_parse_with_model_usage(): void
    {
        $modelUsage = ['claude-sonnet-4-5-20250929' => ['input' => 100, 'output' => 50]];
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'model_usage' => $modelUsage,
        ]);

        $this->assertSame($modelUsage, $msg->modelUsage);
    }
}