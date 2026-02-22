<?php

namespace ClaudeAgentSDK\Tests\Unit;

use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use PHPUnit\Framework\TestCase;

class ClaudeAgentManagerTest extends TestCase
{
    public function test_options_applies_config_defaults(): void
    {
        $manager = new ClaudeAgentManager([
            'model' => 'claude-sonnet-4-5-20250929',
            'permission_mode' => 'acceptEdits',
            'cwd' => '/tmp/project',
            'allowed_tools' => ['Read', 'Bash'],
            'max_turns' => 15,
        ]);

        $opts = $manager->options();

        $this->assertSame('claude-sonnet-4-5-20250929', $opts->model);
        $this->assertSame('acceptEdits', $opts->permissionMode);
        $this->assertSame('/tmp/project', $opts->cwd);
        $this->assertSame(['Read', 'Bash'], $opts->allowedTools);
        $this->assertSame(15, $opts->maxTurns);
    }

    public function test_options_handles_empty_config(): void
    {
        $manager = new ClaudeAgentManager([]);
        $opts = $manager->options();

        $this->assertNull($opts->model);
        $this->assertNull($opts->permissionMode);
    }

    public function test_with_options_returns_clone(): void
    {
        $manager = new ClaudeAgentManager([]);
        $clone = $manager->withOptions(ClaudeAgentOptions::make()->model('test'));

        $this->assertNotSame($manager, $clone);
    }

    public function test_with_options_accepts_array(): void
    {
        $manager = new ClaudeAgentManager([]);
        $clone = $manager->withOptions(['model' => 'test']);

        $this->assertNotSame($manager, $clone);
    }
}