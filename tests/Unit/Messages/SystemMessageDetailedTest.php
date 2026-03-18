<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Messages\SystemMessage;
use PHPUnit\Framework\TestCase;

class SystemMessageDetailedTest extends TestCase
{
    public function test_parse_init_with_tools(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_001',
            'tools' => ['Read', 'Write', 'Edit', 'Bash', 'Glob', 'Grep', 'WebFetch'],
        ]);

        $this->assertTrue($msg->isInit());
        $this->assertSame(
            ['Read', 'Write', 'Edit', 'Bash', 'Glob', 'Grep', 'WebFetch'],
            $msg->tools,
        );
    }

    public function test_parse_init_with_mcp_servers(): void
    {
        $mcpServers = [
            [
                'name' => 'database',
                'status' => 'connected',
                'type' => 'stdio',
                'tools' => ['query', 'insert'],
            ],
            [
                'name' => 'web-search',
                'status' => 'connected',
                'type' => 'http',
                'tools' => ['search'],
            ],
        ];

        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'mcp_servers' => $mcpServers,
        ]);

        $this->assertCount(2, $msg->mcpServers);
        $this->assertSame('database', $msg->mcpServers[0]['name']);
        $this->assertSame('connected', $msg->mcpServers[0]['status']);
        $this->assertSame('web-search', $msg->mcpServers[1]['name']);
    }

    public function test_parse_init_with_model(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'model' => 'claude-sonnet-4-5-20250929',
        ]);

        $this->assertSame('claude-sonnet-4-5-20250929', $msg->model);
    }

    public function test_parse_init_with_permission_mode(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'permissionMode' => 'plan',
        ]);

        $this->assertSame('plan', $msg->permissionMode);
    }

    public function test_parse_init_with_permission_mode_snake_case(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'permission_mode' => 'bypassPermissions',
        ]);

        $this->assertSame('bypassPermissions', $msg->permissionMode);
    }

    public function test_parse_init_with_slash_commands(): void
    {
        $commands = [
            ['name' => '/help', 'description' => 'Show help'],
            ['name' => '/compact', 'description' => 'Compact conversation'],
            ['name' => '/cost', 'description' => 'Show cost summary'],
        ];

        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'slash_commands' => $commands,
        ]);

        $this->assertCount(3, $msg->slashCommands);
        $this->assertSame('/help', $msg->slashCommands[0]['name']);
        $this->assertSame('Compact conversation', $msg->slashCommands[1]['description']);
    }

    public function test_parse_init_with_api_key_source(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'apiKeySource' => 'environment_variable',
        ]);

        $this->assertSame('environment_variable', $msg->apiKeySource);
    }

    public function test_parse_init_with_api_key_source_snake_case(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'api_key_source' => 'config_file',
        ]);

        $this->assertSame('config_file', $msg->apiKeySource);
    }

    public function test_parse_init_with_cwd(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'cwd' => '/home/user/projects/my-app',
        ]);

        $this->assertSame('/home/user/projects/my-app', $msg->sessionCwd);
    }

    public function test_compact_boundary(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'compact_boundary',
            'session_id' => 'sess_002',
            'compact_metadata' => [
                'original_tokens' => 50000,
                'compacted_tokens' => 8000,
                'reason' => 'context_window',
            ],
        ]);

        $this->assertTrue($msg->isCompactBoundary());
        $this->assertFalse($msg->isInit());

        $metadata = $msg->compactMetadata();
        $this->assertNotNull($metadata);
        $this->assertSame(50000, $metadata['original_tokens']);
        $this->assertSame(8000, $metadata['compacted_tokens']);
        $this->assertSame('context_window', $metadata['reason']);
    }

    public function test_compact_metadata(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'compact_boundary',
            'compact_metadata' => [
                'original_tokens' => 120000,
                'compacted_tokens' => 15000,
            ],
        ]);

        $metadata = $msg->compactMetadata();
        $this->assertSame(120000, $metadata['original_tokens']);
        $this->assertSame(15000, $metadata['compacted_tokens']);
    }

    public function test_not_compact_boundary(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_003',
        ]);

        $this->assertFalse($msg->isCompactBoundary());
        $this->assertNull($msg->compactMetadata());
    }

    public function test_backward_compatible(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_compat',
        ]);

        // Original fields still work
        $this->assertSame('init', $msg->subtype);
        $this->assertSame('sess_compat', $msg->sessionId);
        $this->assertTrue($msg->isInit());

        // data contains the raw input
        $this->assertSame('system', $msg->data['type']);
        $this->assertSame('init', $msg->data['subtype']);
        $this->assertSame('sess_compat', $msg->data['session_id']);

        // New fields default gracefully
        $this->assertSame([], $msg->tools);
        $this->assertSame([], $msg->mcpServers);
        $this->assertNull($msg->model);
        $this->assertNull($msg->permissionMode);
        $this->assertSame([], $msg->slashCommands);
        $this->assertNull($msg->apiKeySource);
        $this->assertNull($msg->sessionCwd);
    }

    public function test_parse_init_full_payload(): void
    {
        $msg = SystemMessage::parse([
            'type' => 'system',
            'subtype' => 'init',
            'session_id' => 'sess_full',
            'tools' => ['Read', 'Write', 'Bash'],
            'mcp_servers' => [
                ['name' => 'db', 'status' => 'connected'],
            ],
            'model' => 'claude-opus-4-20250514',
            'permissionMode' => 'acceptEdits',
            'slash_commands' => [
                ['name' => '/help', 'description' => 'Help'],
            ],
            'apiKeySource' => 'environment_variable',
            'cwd' => '/var/www/app',
        ]);

        $this->assertTrue($msg->isInit());
        $this->assertSame('sess_full', $msg->sessionId);
        $this->assertSame(['Read', 'Write', 'Bash'], $msg->tools);
        $this->assertCount(1, $msg->mcpServers);
        $this->assertSame('claude-opus-4-20250514', $msg->model);
        $this->assertSame('acceptEdits', $msg->permissionMode);
        $this->assertCount(1, $msg->slashCommands);
        $this->assertSame('environment_variable', $msg->apiKeySource);
        $this->assertSame('/var/www/app', $msg->sessionCwd);
    }
}
