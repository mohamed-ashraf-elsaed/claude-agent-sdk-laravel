<?php

namespace ClaudeAgentSDK\Tests\Unit\Hooks;

use ClaudeAgentSDK\Hooks\HookEvent;
use PHPUnit\Framework\TestCase;

class HookEventTest extends TestCase
{
    public function test_all_hook_events_exist(): void
    {
        $this->assertSame('PreToolUse', HookEvent::PreToolUse->value);
        $this->assertSame('PostToolUse', HookEvent::PostToolUse->value);
        $this->assertSame('UserPromptSubmit', HookEvent::UserPromptSubmit->value);
        $this->assertSame('Stop', HookEvent::Stop->value);
        $this->assertSame('SubagentStop', HookEvent::SubagentStop->value);
        $this->assertSame('PreCompact', HookEvent::PreCompact->value);
    }

    public function test_from_string(): void
    {
        $event = HookEvent::from('PreToolUse');

        $this->assertSame(HookEvent::PreToolUse, $event);
    }
}