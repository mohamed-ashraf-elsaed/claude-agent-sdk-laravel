<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\ResultMessage;
use PHPUnit\Framework\TestCase;

class ResultMessageSubtypeTest extends TestCase
{
    public function test_subtype_constants_exist(): void
    {
        $this->assertSame('success', ResultMessage::SUBTYPE_SUCCESS);
        $this->assertSame('error_max_turns', ResultMessage::SUBTYPE_ERROR_MAX_TURNS);
        $this->assertSame('error_during_execution', ResultMessage::SUBTYPE_ERROR_DURING_EXECUTION);
        $this->assertSame('error_max_budget_usd', ResultMessage::SUBTYPE_ERROR_MAX_BUDGET);
        $this->assertSame('error_max_structured_output_retries', ResultMessage::SUBTYPE_ERROR_MAX_STRUCTURED_OUTPUT_RETRIES);
    }

    public function test_is_max_turns_error(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error_max_turns',
            'result' => 'Agent reached the maximum number of turns (10).',
            'is_error' => true,
            'num_turns' => 10,
            'duration_ms' => 45000,
            'total_cost_usd' => 0.15,
        ]);

        $this->assertTrue($msg->isMaxTurnsError());
        $this->assertFalse($msg->isSuccess());
        $this->assertFalse($msg->isBudgetError());
        $this->assertFalse($msg->isExecutionError());
        $this->assertFalse($msg->isStructuredOutputError());
    }

    public function test_is_budget_error(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error_max_budget_usd',
            'result' => 'Budget limit of $5.00 exceeded.',
            'is_error' => true,
            'total_cost_usd' => 5.01,
        ]);

        $this->assertTrue($msg->isBudgetError());
        $this->assertFalse($msg->isSuccess());
        $this->assertFalse($msg->isMaxTurnsError());
        $this->assertFalse($msg->isExecutionError());
        $this->assertFalse($msg->isStructuredOutputError());
    }

    public function test_is_execution_error(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error_during_execution',
            'result' => 'An unexpected error occurred during tool execution.',
            'is_error' => true,
        ]);

        $this->assertTrue($msg->isExecutionError());
        $this->assertFalse($msg->isSuccess());
        $this->assertFalse($msg->isMaxTurnsError());
        $this->assertFalse($msg->isBudgetError());
        $this->assertFalse($msg->isStructuredOutputError());
    }

    public function test_is_structured_output_error(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error_max_structured_output_retries',
            'result' => 'Failed to produce valid structured output after 3 retries.',
            'is_error' => true,
        ]);

        $this->assertTrue($msg->isStructuredOutputError());
        $this->assertFalse($msg->isSuccess());
        $this->assertFalse($msg->isMaxTurnsError());
        $this->assertFalse($msg->isBudgetError());
        $this->assertFalse($msg->isExecutionError());
    }

    public function test_permission_denials_parsed(): void
    {
        $denials = [
            [
                'tool_name' => 'Bash',
                'tool_use_id' => 'toolu_01XYZ',
                'tool_input' => ['command' => 'rm -rf /'],
            ],
            [
                'tool_name' => 'Write',
                'tool_use_id' => 'toolu_02ABC',
                'tool_input' => ['file_path' => '/etc/passwd', 'content' => 'hacked'],
            ],
        ];

        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Completed with some denials.',
            'permission_denials' => $denials,
        ]);

        $this->assertCount(2, $msg->permissionDenials);
        $this->assertSame('Bash', $msg->permissionDenials[0]['tool_name']);
        $this->assertSame('toolu_01XYZ', $msg->permissionDenials[0]['tool_use_id']);
        $this->assertSame('rm -rf /', $msg->permissionDenials[0]['tool_input']['command']);
        $this->assertSame('Write', $msg->permissionDenials[1]['tool_name']);
    }

    public function test_permission_denials_empty_by_default(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'All good.',
        ]);

        $this->assertSame([], $msg->permissionDenials);
    }

    public function test_errors_parsed(): void
    {
        $errors = [
            [
                'type' => 'tool_error',
                'message' => 'Process exited with code 1',
                'tool_use_id' => 'toolu_03DEF',
            ],
            [
                'type' => 'api_error',
                'message' => 'Rate limit exceeded',
            ],
        ];

        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'error_during_execution',
            'result' => 'Execution failed.',
            'is_error' => true,
            'errors' => $errors,
        ]);

        $this->assertCount(2, $msg->errors);
        $this->assertSame('tool_error', $msg->errors[0]['type']);
        $this->assertSame('Process exited with code 1', $msg->errors[0]['message']);
        $this->assertSame('api_error', $msg->errors[1]['type']);
    }

    public function test_errors_empty_by_default(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
        ]);

        $this->assertSame([], $msg->errors);
    }

    public function test_success_subtype(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Done!',
            'is_error' => false,
        ]);

        $this->assertTrue($msg->isSuccess());
        $this->assertFalse($msg->isMaxTurnsError());
        $this->assertFalse($msg->isBudgetError());
        $this->assertFalse($msg->isExecutionError());
        $this->assertFalse($msg->isStructuredOutputError());
    }
}
