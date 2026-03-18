<?php

namespace ClaudeAgentSDK\Tests\Unit;

use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\QueryResult;
use PHPUnit\Framework\TestCase;

class QueryResultNewFieldsTest extends TestCase
{
    public function test_subtype(): void
    {
        $qr = new QueryResult([
            $this->makeResult(['subtype' => 'error_max_turns']),
        ]);

        $this->assertSame('error_max_turns', $qr->subtype());
    }

    public function test_subtype_null_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertNull($qr->subtype());
    }

    public function test_is_max_turns_error(): void
    {
        $qr = new QueryResult([
            $this->makeResult([
                'subtype' => 'error_max_turns',
                'is_error' => true,
                'num_turns' => 10,
            ]),
        ]);

        $this->assertTrue($qr->isMaxTurnsError());
        $this->assertFalse($qr->isBudgetError());
    }

    public function test_is_max_turns_error_false_on_success(): void
    {
        $qr = new QueryResult([
            $this->makeResult(['subtype' => 'success']),
        ]);

        $this->assertFalse($qr->isMaxTurnsError());
    }

    public function test_is_max_turns_error_false_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertFalse($qr->isMaxTurnsError());
    }

    public function test_is_budget_error(): void
    {
        $qr = new QueryResult([
            $this->makeResult([
                'subtype' => 'error_max_budget_usd',
                'is_error' => true,
                'total_cost_usd' => 10.50,
            ]),
        ]);

        $this->assertTrue($qr->isBudgetError());
        $this->assertFalse($qr->isMaxTurnsError());
    }

    public function test_is_budget_error_false_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertFalse($qr->isBudgetError());
    }

    public function test_permission_denials(): void
    {
        $denials = [
            [
                'tool_name' => 'Bash',
                'tool_use_id' => 'toolu_01ABC',
                'tool_input' => ['command' => 'rm -rf /'],
            ],
            [
                'tool_name' => 'Write',
                'tool_use_id' => 'toolu_02DEF',
                'tool_input' => ['file_path' => '/etc/shadow'],
            ],
        ];

        $qr = new QueryResult([
            $this->makeResult(['permission_denials' => $denials]),
        ]);

        $result = $qr->permissionDenials();
        $this->assertCount(2, $result);
        $this->assertSame('Bash', $result[0]['tool_name']);
        $this->assertSame('Write', $result[1]['tool_name']);
    }

    public function test_permission_denials_empty_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertSame([], $qr->permissionDenials());
    }

    public function test_errors(): void
    {
        $errors = [
            [
                'type' => 'tool_error',
                'message' => 'Command failed with exit code 127',
                'tool_use_id' => 'toolu_03GHI',
            ],
        ];

        $qr = new QueryResult([
            $this->makeResult([
                'subtype' => 'error_during_execution',
                'is_error' => true,
                'errors' => $errors,
            ]),
        ]);

        $result = $qr->errors();
        $this->assertCount(1, $result);
        $this->assertSame('tool_error', $result[0]['type']);
        $this->assertSame('Command failed with exit code 127', $result[0]['message']);
    }

    public function test_errors_empty_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertSame([], $qr->errors());
    }

    public function test_model_from_init(): void
    {
        $qr = new QueryResult([
            $this->makeInit([
                'model' => 'claude-opus-4-20250514',
                'tools' => ['Read', 'Write'],
            ]),
            $this->makeResult(),
        ]);

        $this->assertSame('claude-opus-4-20250514', $qr->model());
    }

    public function test_available_tools_from_init(): void
    {
        $qr = new QueryResult([
            $this->makeInit([
                'tools' => ['Read', 'Write', 'Edit', 'Bash', 'Glob', 'Grep'],
            ]),
            $this->makeResult(),
        ]);

        $tools = $qr->availableTools();
        $this->assertCount(6, $tools);
        $this->assertSame('Read', $tools[0]);
        $this->assertSame('Grep', $tools[5]);
    }

    public function test_mcp_server_status_from_init(): void
    {
        $mcpServers = [
            ['name' => 'database', 'status' => 'connected', 'tools' => ['query']],
            ['name' => 'search', 'status' => 'error', 'error' => 'Connection refused'],
        ];

        $qr = new QueryResult([
            $this->makeInit(['mcp_servers' => $mcpServers]),
            $this->makeResult(),
        ]);

        $status = $qr->mcpServerStatus();
        $this->assertCount(2, $status);
        $this->assertSame('database', $status[0]['name']);
        $this->assertSame('connected', $status[0]['status']);
        $this->assertSame('error', $status[1]['status']);
    }

    public function test_supported_commands_from_init(): void
    {
        $commands = [
            ['name' => '/help', 'description' => 'Show help message'],
            ['name' => '/compact', 'description' => 'Compact the conversation'],
            ['name' => '/cost', 'description' => 'Show cost breakdown'],
        ];

        $qr = new QueryResult([
            $this->makeInit(['slash_commands' => $commands]),
            $this->makeResult(),
        ]);

        $supported = $qr->supportedCommands();
        $this->assertCount(3, $supported);
        $this->assertSame('/help', $supported[0]['name']);
        $this->assertSame('Show cost breakdown', $supported[2]['description']);
    }

    public function test_init_message_null_when_no_system_message(): void
    {
        $qr = new QueryResult([
            $this->makeResult(),
        ]);

        $this->assertNull($qr->initMessage);
        $this->assertNull($qr->model());
        $this->assertSame([], $qr->availableTools());
        $this->assertSame([], $qr->mcpServerStatus());
        $this->assertSame([], $qr->supportedCommands());
    }

    public function test_init_message_null_when_system_not_init(): void
    {
        $qr = new QueryResult([
            SystemMessage::parse([
                'type' => 'system',
                'subtype' => 'compact_boundary',
                'session_id' => 'sess_1',
            ]),
            $this->makeResult(),
        ]);

        $this->assertNull($qr->initMessage);
        $this->assertNull($qr->model());
    }

    public function test_init_message_extracted_from_messages(): void
    {
        $init = $this->makeInit([
            'model' => 'claude-sonnet-4-5-20250929',
            'tools' => ['Read'],
        ]);

        $qr = new QueryResult([$init, $this->makeResult()]);

        $this->assertSame($init, $qr->initMessage);
    }

    // --- Helpers ---

    private function makeResult(array $overrides = []): ResultMessage
    {
        return ResultMessage::parse(array_merge([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Task completed.',
            'session_id' => 'sess_test',
            'duration_ms' => 5000,
            'duration_api_ms' => 4500,
            'num_turns' => 3,
            'total_cost_usd' => 0.02,
            'is_error' => false,
        ], $overrides));
    }

    private function makeInit(array $overrides = []): SystemMessage
    {
        return SystemMessage::parse(array_merge([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_test',
        ], $overrides));
    }
}
