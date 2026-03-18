<?php

namespace ClaudeAgentSDK\Tests\Unit\Permissions;

use ClaudeAgentSDK\Permissions\PermissionResult;
use ClaudeAgentSDK\Permissions\PermissionResultDeny;
use PHPUnit\Framework\TestCase;

class PermissionResultDenyTest extends TestCase
{
    public function test_deny_with_message(): void
    {
        $result = new PermissionResultDeny(message: 'This tool is not allowed in production.');

        $this->assertSame('This tool is not allowed in production.', $result->message);
        $this->assertFalse($result->interrupt);
        $this->assertInstanceOf(PermissionResult::class, $result);
    }

    public function test_deny_with_empty_message(): void
    {
        $result = new PermissionResultDeny();

        $this->assertSame('', $result->message);
        $this->assertFalse($result->interrupt);
    }

    public function test_deny_with_interrupt(): void
    {
        $result = new PermissionResultDeny(
            message: 'Critical security violation detected.',
            interrupt: true,
        );

        $this->assertSame('Critical security violation detected.', $result->message);
        $this->assertTrue($result->interrupt);
    }

    public function test_deny_to_array(): void
    {
        $result = new PermissionResultDeny(message: 'File writes are disabled.');

        $array = $result->toArray();

        $this->assertSame('deny', $array['behavior']);
        $this->assertSame('File writes are disabled.', $array['message']);
        $this->assertArrayNotHasKey('interrupt', $array);
    }

    public function test_deny_to_array_with_interrupt(): void
    {
        $result = new PermissionResultDeny(
            message: 'Abort mission.',
            interrupt: true,
        );

        $array = $result->toArray();

        $this->assertSame('deny', $array['behavior']);
        $this->assertSame('Abort mission.', $array['message']);
        $this->assertTrue($array['interrupt']);
    }

    public function test_deny_to_hook_output(): void
    {
        $result = new PermissionResultDeny(message: 'Tool not allowed.');

        $output = $result->toHookOutput();

        $this->assertArrayHasKey('hookSpecificOutput', $output);
        $this->assertSame('PreToolUse', $output['hookSpecificOutput']['hookEventName']);
        $this->assertSame('deny', $output['hookSpecificOutput']['permissionDecision']);
        $this->assertSame('Tool not allowed.', $output['hookSpecificOutput']['permissionDecisionReason']);
        $this->assertArrayNotHasKey('continue', $output);
        $this->assertArrayNotHasKey('stopReason', $output);
    }

    public function test_deny_to_hook_output_with_empty_message(): void
    {
        $result = new PermissionResultDeny(message: '');

        $output = $result->toHookOutput();

        $this->assertSame('deny', $output['hookSpecificOutput']['permissionDecision']);
        $this->assertArrayNotHasKey('permissionDecisionReason', $output['hookSpecificOutput']);
    }

    public function test_deny_to_hook_output_with_interrupt(): void
    {
        $result = new PermissionResultDeny(
            message: 'Emergency stop triggered.',
            interrupt: true,
        );

        $output = $result->toHookOutput();

        $this->assertSame('deny', $output['hookSpecificOutput']['permissionDecision']);
        $this->assertSame('Emergency stop triggered.', $output['hookSpecificOutput']['permissionDecisionReason']);
        $this->assertFalse($output['continue']);
        $this->assertSame('Emergency stop triggered.', $output['stopReason']);
    }

    public function test_deny_json_serialize(): void
    {
        $result = new PermissionResultDeny(
            message: 'Access denied for Bash tool.',
            interrupt: true,
        );

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame('deny', $decoded['behavior']);
        $this->assertSame('Access denied for Bash tool.', $decoded['message']);
        $this->assertTrue($decoded['interrupt']);
    }

    public function test_deny_implements_json_serializable(): void
    {
        $result = new PermissionResultDeny(message: 'test');

        $this->assertInstanceOf(\JsonSerializable::class, $result);
        $this->assertSame($result->toArray(), $result->jsonSerialize());
    }
}
