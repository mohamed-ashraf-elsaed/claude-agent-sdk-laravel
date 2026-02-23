<?php

namespace ClaudeAgentSDK\Tests\Feature;

use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_manager_is_bound(): void
    {
        $manager = $this->app->make(ClaudeAgentManager::class);

        $this->assertInstanceOf(ClaudeAgentManager::class, $manager);
    }

    public function test_manager_is_singleton(): void
    {
        $a = $this->app->make(ClaudeAgentManager::class);
        $b = $this->app->make(ClaudeAgentManager::class);

        $this->assertSame($a, $b);
    }

    public function test_alias_resolves(): void
    {
        $manager = $this->app->make('claude-agent');

        $this->assertInstanceOf(ClaudeAgentManager::class, $manager);
    }

    public function test_config_is_merged(): void
    {
        $config = $this->app['config']['claude-agent'];

        $this->assertArrayHasKey('cli_path', $config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('permission_mode', $config);
        $this->assertArrayHasKey('allowed_tools', $config);
        $this->assertArrayHasKey('process_timeout', $config);
        $this->assertArrayHasKey('providers', $config);
    }

    public function test_default_permission_mode(): void
    {
        $this->assertSame('default', $this->app['config']['claude-agent.permission_mode']);
    }

    public function test_new_config_keys_are_merged(): void
    {
        $config = $this->app['config']['claude-agent'];

        $this->assertArrayHasKey('max_budget_usd', $config);
        $this->assertArrayHasKey('max_thinking_tokens', $config);
    }
}