<?php

namespace ClaudeAgentSDK\Tests\Unit\Permissions;

use ClaudeAgentSDK\Permissions\PermissionResult;
use ClaudeAgentSDK\Permissions\PermissionResultAllow;
use PHPUnit\Framework\TestCase;

class PermissionResultAllowTest extends TestCase
{
    public function test_allow_with_no_args(): void
    {
        $result = new PermissionResultAllow();

        $this->assertNull($result->updatedInput);
        $this->assertNull($result->updatedPermissions);
        $this->assertInstanceOf(PermissionResult::class, $result);
    }

    public function test_allow_with_updated_input(): void
    {
        $input = ['command' => 'ls -la /tmp', 'timeout' => 30];
        $result = new PermissionResultAllow(updatedInput: $input);

        $this->assertSame($input, $result->updatedInput);
        $this->assertNull($result->updatedPermissions);
    }

    public function test_allow_with_updated_permissions(): void
    {
        $permissions = [
            'addRules' => ['allow Bash(**)'],
            'removeRules' => ['deny Edit(/etc/*)'],
            'setMode' => 'acceptEdits',
        ];
        $result = new PermissionResultAllow(updatedPermissions: $permissions);

        $this->assertNull($result->updatedInput);
        $this->assertSame($permissions, $result->updatedPermissions);
    }

    public function test_allow_to_array(): void
    {
        $input = ['file_path' => '/src/app.php'];
        $permissions = ['addRules' => ['allow Read(**)']];
        $result = new PermissionResultAllow(
            updatedInput: $input,
            updatedPermissions: $permissions,
        );

        $array = $result->toArray();

        $this->assertSame('allow', $array['behavior']);
        $this->assertSame($input, $array['updatedInput']);
        $this->assertSame($permissions, $array['updatedPermissions']);
    }

    public function test_allow_to_array_minimal(): void
    {
        $result = new PermissionResultAllow();
        $array = $result->toArray();

        $this->assertSame(['behavior' => 'allow'], $array);
        $this->assertArrayNotHasKey('updatedInput', $array);
        $this->assertArrayNotHasKey('updatedPermissions', $array);
    }

    public function test_allow_to_hook_output(): void
    {
        $input = ['command' => 'cat /etc/hosts'];
        $result = new PermissionResultAllow(updatedInput: $input);

        $output = $result->toHookOutput();

        $this->assertArrayHasKey('hookSpecificOutput', $output);
        $this->assertSame('PreToolUse', $output['hookSpecificOutput']['hookEventName']);
        $this->assertSame('allow', $output['hookSpecificOutput']['permissionDecision']);
        $this->assertSame($input, $output['hookSpecificOutput']['updatedInput']);
        $this->assertArrayNotHasKey('continue', $output);
    }

    public function test_allow_to_hook_output_without_updated_input(): void
    {
        $result = new PermissionResultAllow();

        $output = $result->toHookOutput();

        $this->assertSame('allow', $output['hookSpecificOutput']['permissionDecision']);
        $this->assertArrayNotHasKey('updatedInput', $output['hookSpecificOutput']);
    }

    public function test_allow_json_serialize(): void
    {
        $result = new PermissionResultAllow(
            updatedInput: ['path' => '/tmp/test.txt'],
        );

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertSame('allow', $decoded['behavior']);
        $this->assertSame('/tmp/test.txt', $decoded['updatedInput']['path']);
    }

    public function test_allow_implements_json_serializable(): void
    {
        $result = new PermissionResultAllow();

        $this->assertInstanceOf(\JsonSerializable::class, $result);
        $this->assertSame($result->toArray(), $result->jsonSerialize());
    }
}
