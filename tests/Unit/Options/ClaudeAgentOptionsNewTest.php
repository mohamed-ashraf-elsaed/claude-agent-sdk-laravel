<?php

namespace ClaudeAgentSDK\Tests\Unit\Options;

use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use PHPUnit\Framework\TestCase;

class ClaudeAgentOptionsNewTest extends TestCase
{
    public function test_fluent_can_use_tool(): void
    {
        $handler = function (string $toolName, array $toolInput) {
            return true;
        };

        $opts = ClaudeAgentOptions::make()->canUseTool($handler);

        $this->assertSame($handler, $opts->canUseTool);
    }

    public function test_fluent_can_use_tool_with_closure(): void
    {
        $called = false;
        $opts = ClaudeAgentOptions::make()->canUseTool(function () use (&$called) {
            $called = true;
        });

        $this->assertNotNull($opts->canUseTool);
        ($opts->canUseTool)('Bash', ['command' => 'ls']);
        $this->assertTrue($called);
    }

    public function test_fluent_stderr(): void
    {
        $output = '';
        $callback = function (string $data) use (&$output) {
            $output .= $data;
        };

        $opts = ClaudeAgentOptions::make()->stderr($callback);

        $this->assertSame($callback, $opts->stderr);
        ($opts->stderr)('some stderr output');
        $this->assertSame('some stderr output', $output);
    }

    public function test_fluent_permission_prompt_tool_name(): void
    {
        $opts = ClaudeAgentOptions::make()->permissionPromptToolName('mcp__auth__approve');

        $this->assertSame('mcp__auth__approve', $opts->permissionPromptToolName);
    }

    public function test_fluent_allow_dangerously_skip_permissions(): void
    {
        $opts = ClaudeAgentOptions::make()->allowDangerouslySkipPermissions();

        $this->assertTrue($opts->allowDangerouslySkipPermissions);
    }

    public function test_fluent_allow_dangerously_skip_permissions_false(): void
    {
        $opts = ClaudeAgentOptions::make()->allowDangerouslySkipPermissions(false);

        $this->assertFalse($opts->allowDangerouslySkipPermissions);
    }

    public function test_fluent_resume_session_at(): void
    {
        $opts = ClaudeAgentOptions::make()->resumeSessionAt('msg-uuid-abc-123-456');

        $this->assertSame('msg-uuid-abc-123-456', $opts->resumeSessionAt);
    }

    public function test_cli_args_permission_prompt_tool_name(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->permissionPromptToolName('mcp__permissions__check');

        $args = $opts->toCliArgs();

        $this->assertContains('--permission-prompt-tool-name', $args);
        $idx = array_search('--permission-prompt-tool-name', $args);
        $this->assertSame('mcp__permissions__check', $args[$idx + 1]);
    }

    public function test_cli_args_resume_session_at(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->resumeSessionAt('msg-uuid-xyz-789');

        $args = $opts->toCliArgs();

        $this->assertContains('--resume-session-at', $args);
        $idx = array_search('--resume-session-at', $args);
        $this->assertSame('msg-uuid-xyz-789', $args[$idx + 1]);
    }

    public function test_cli_args_absent_for_new_options_when_not_set(): void
    {
        $args = ClaudeAgentOptions::make()->toCliArgs();

        $this->assertNotContains('--permission-prompt-tool-name', $args);
        $this->assertNotContains('--resume-session-at', $args);
    }

    public function test_permission_mode_plan(): void
    {
        $opts = ClaudeAgentOptions::make()->permission('plan');

        $this->assertSame('plan', $opts->permissionMode);

        $args = $opts->toCliArgs();
        $this->assertContains('--permission-mode', $args);
        $idx = array_search('--permission-mode', $args);
        $this->assertSame('plan', $args[$idx + 1]);
    }

    public function test_can_use_tool_null_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertNull($opts->canUseTool);
    }

    public function test_stderr_null_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertNull($opts->stderr);
    }

    public function test_permission_prompt_tool_name_null_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertNull($opts->permissionPromptToolName);
    }

    public function test_allow_dangerously_skip_permissions_false_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertFalse($opts->allowDangerouslySkipPermissions);
    }

    public function test_resume_session_at_null_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertNull($opts->resumeSessionAt);
    }

    public function test_can_use_tool_not_in_cli_args(): void
    {
        // canUseTool is a callback and should NOT appear in CLI args
        $opts = ClaudeAgentOptions::make()->canUseTool(fn() => true);
        $args = $opts->toCliArgs();

        $this->assertNotContains('--can-use-tool', $args);
    }

    public function test_stderr_not_in_cli_args(): void
    {
        // stderr is a callback and should NOT appear in CLI args
        $opts = ClaudeAgentOptions::make()->stderr(fn($data) => null);
        $args = $opts->toCliArgs();

        $this->assertNotContains('--stderr', $args);
    }

    public function test_fluent_chaining_new_options(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->permission('plan')
            ->permissionPromptToolName('mcp__tool')
            ->resumeSessionAt('msg-123')
            ->allowDangerouslySkipPermissions()
            ->canUseTool(fn() => true)
            ->stderr(fn($d) => null);

        $this->assertSame('plan', $opts->permissionMode);
        $this->assertSame('mcp__tool', $opts->permissionPromptToolName);
        $this->assertSame('msg-123', $opts->resumeSessionAt);
        $this->assertTrue($opts->allowDangerouslySkipPermissions);
        $this->assertNotNull($opts->canUseTool);
        $this->assertNotNull($opts->stderr);
    }
}
