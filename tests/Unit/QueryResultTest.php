<?php

namespace ClaudeAgentSDK\Tests\Unit;

use ClaudeAgentSDK\Content\TextBlock;
use ClaudeAgentSDK\Content\ToolUseBlock;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\GenericMessage;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\SystemMessage;
use ClaudeAgentSDK\QueryResult;
use PHPUnit\Framework\TestCase;

class QueryResultTest extends TestCase
{
    public function test_text_returns_result(): void
    {
        $qr = new QueryResult([$this->makeResult()]);

        $this->assertSame('Final answer', $qr->text());
    }

    private function makeResult(array $overrides = []): ResultMessage
    {
        return ResultMessage::parse(array_merge([
            'type' => 'result',
            'subtype' => 'success',
            'result' => 'Final answer',
            'session_id' => 'sess_1',
            'duration_ms' => 3000,
            'num_turns' => 2,
            'total_cost_usd' => 0.01,
            'is_error' => false,
        ], $overrides));
    }

    public function test_text_returns_null_without_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertNull($qr->text());
    }

    public function test_is_success(): void
    {
        $qr = new QueryResult([$this->makeResult()]);

        $this->assertTrue($qr->isSuccess());
        $this->assertFalse($qr->isError());
    }

    public function test_is_error_when_no_result(): void
    {
        $qr = new QueryResult([]);

        $this->assertFalse($qr->isSuccess());
        $this->assertTrue($qr->isError());
    }

    public function test_is_error_when_error_result(): void
    {
        $qr = new QueryResult([
            $this->makeResult(['subtype' => 'error', 'is_error' => true]),
        ]);

        $this->assertFalse($qr->isSuccess());
        $this->assertTrue($qr->isError());
    }

    public function test_cost_usd(): void
    {
        $qr = new QueryResult([$this->makeResult(['total_cost_usd' => 0.05])]);

        $this->assertSame(0.05, $qr->costUsd());
    }

    public function test_turns(): void
    {
        $qr = new QueryResult([$this->makeResult(['num_turns' => 5])]);

        $this->assertSame(5, $qr->turns());
    }

    public function test_duration_ms(): void
    {
        $qr = new QueryResult([$this->makeResult(['duration_ms' => 8000])]);

        $this->assertSame(8000, $qr->durationMs());
    }

    public function test_session_id_from_system_message(): void
    {
        $qr = new QueryResult([
            $this->makeSystem('sess_from_system'),
            $this->makeAssistant('Hi'),
            $this->makeResult(['session_id' => 'sess_from_result']),
        ]);

        $this->assertSame('sess_from_system', $qr->sessionId);
    }

    private function makeSystem(string $sessionId): SystemMessage
    {
        return SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => $sessionId,
        ]);
    }

    private function makeAssistant(string $text, array $tools = []): AssistantMessage
    {
        $content = [new TextBlock($text)];
        foreach ($tools as $t) {
            $content[] = new ToolUseBlock($t['id'], $t['name'], $t['input'] ?? []);
        }
        return new AssistantMessage(content: $content);
    }

    public function test_session_id_from_result_when_no_system(): void
    {
        $qr = new QueryResult([
            $this->makeAssistant('Hi'),
            $this->makeResult(['session_id' => 'sess_from_result']),
        ]);

        $this->assertSame('sess_from_result', $qr->sessionId);
    }

    public function test_session_id_null_when_missing(): void
    {
        $qr = new QueryResult([
            $this->makeAssistant('Hi'),
        ]);

        $this->assertNull($qr->sessionId);
    }

    public function test_assistant_messages(): void
    {
        $a1 = $this->makeAssistant('First');
        $a2 = $this->makeAssistant('Second');

        $qr = new QueryResult([
            $this->makeSystem('s'),
            $a1,
            new GenericMessage('custom'),
            $a2,
            $this->makeResult(),
        ]);

        $assistants = $qr->assistantMessages();
        $this->assertCount(2, $assistants);
        $this->assertSame('First', $assistants[0]->text());
        $this->assertSame('Second', $assistants[1]->text());
    }

    public function test_full_text(): void
    {
        $qr = new QueryResult([
            $this->makeAssistant('Part one'),
            $this->makeAssistant('Part two'),
            $this->makeAssistant('Part three'),
        ]);

        $this->assertSame("Part one\nPart two\nPart three", $qr->fullText());
    }

    public function test_full_text_skips_empty(): void
    {
        $qr = new QueryResult([
            $this->makeAssistant('Part one'),
            new AssistantMessage(content: []),
            $this->makeAssistant('Part two'),
        ]);

        $this->assertSame("Part one\nPart two", $qr->fullText());
    }

    public function test_tool_uses(): void
    {
        $qr = new QueryResult([
            $this->makeAssistant('Reading...', [
                ['id' => 'tu_1', 'name' => 'Read'],
            ]),
            $this->makeAssistant('Editing...', [
                ['id' => 'tu_2', 'name' => 'Edit'],
                ['id' => 'tu_3', 'name' => 'Bash'],
            ]),
        ]);

        $uses = $qr->toolUses();
        $this->assertCount(3, $uses);
        $this->assertSame('Read', $uses[0]->name);
        $this->assertSame('Edit', $uses[1]->name);
        $this->assertSame('Bash', $uses[2]->name);
    }

    public function test_structured_output(): void
    {
        $structured = ['issues' => [], 'total' => 0];
        $qr = new QueryResult([
            $this->makeResult(['structured_output' => $structured]),
        ]);

        $this->assertSame($structured, $qr->structured());
    }

    public function test_uses_last_result_message(): void
    {
        $qr = new QueryResult([
            $this->makeResult(['result' => 'First result']),
            $this->makeResult(['result' => 'Last result']),
        ]);

        $this->assertSame('Last result', $qr->text());
    }
}