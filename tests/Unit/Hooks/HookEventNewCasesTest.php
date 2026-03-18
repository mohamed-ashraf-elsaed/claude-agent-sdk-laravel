<?php

namespace ClaudeAgentSDK\Tests\Unit\Hooks;

use ClaudeAgentSDK\Hooks\HookEvent;
use PHPUnit\Framework\TestCase;

class HookEventNewCasesTest extends TestCase
{
    public function test_post_tool_use_failure_value(): void
    {
        $this->assertSame('PostToolUseFailure', HookEvent::PostToolUseFailure->value);
        $this->assertSame(HookEvent::PostToolUseFailure, HookEvent::from('PostToolUseFailure'));
    }

    public function test_notification_value(): void
    {
        $this->assertSame('Notification', HookEvent::Notification->value);
        $this->assertSame(HookEvent::Notification, HookEvent::from('Notification'));
    }

    public function test_session_start_value(): void
    {
        $this->assertSame('SessionStart', HookEvent::SessionStart->value);
        $this->assertSame(HookEvent::SessionStart, HookEvent::from('SessionStart'));
    }

    public function test_session_end_value(): void
    {
        $this->assertSame('SessionEnd', HookEvent::SessionEnd->value);
        $this->assertSame(HookEvent::SessionEnd, HookEvent::from('SessionEnd'));
    }

    public function test_subagent_start_value(): void
    {
        $this->assertSame('SubagentStart', HookEvent::SubagentStart->value);
        $this->assertSame(HookEvent::SubagentStart, HookEvent::from('SubagentStart'));
    }

    public function test_permission_request_value(): void
    {
        $this->assertSame('PermissionRequest', HookEvent::PermissionRequest->value);
        $this->assertSame(HookEvent::PermissionRequest, HookEvent::from('PermissionRequest'));
    }

    public function test_all_twelve_cases_exist(): void
    {
        $cases = HookEvent::cases();

        $this->assertCount(12, $cases);

        $expectedValues = [
            'PreToolUse',
            'PostToolUse',
            'PostToolUseFailure',
            'UserPromptSubmit',
            'Notification',
            'SessionStart',
            'SessionEnd',
            'Stop',
            'SubagentStart',
            'SubagentStop',
            'PreCompact',
            'PermissionRequest',
        ];

        $actualValues = array_map(fn(HookEvent $e) => $e->value, $cases);

        foreach ($expectedValues as $expected) {
            $this->assertContains($expected, $actualValues, "Missing HookEvent case: {$expected}");
        }
    }

    public function test_new_cases_can_be_used_in_hook_method(): void
    {
        // Verify these cases are valid enum instances that can be used in match/switch
        $newCases = [
            HookEvent::PostToolUseFailure,
            HookEvent::Notification,
            HookEvent::SessionStart,
            HookEvent::SessionEnd,
            HookEvent::SubagentStart,
            HookEvent::PermissionRequest,
        ];

        foreach ($newCases as $case) {
            $this->assertInstanceOf(HookEvent::class, $case);
            $this->assertNotEmpty($case->value);
        }
    }
}
