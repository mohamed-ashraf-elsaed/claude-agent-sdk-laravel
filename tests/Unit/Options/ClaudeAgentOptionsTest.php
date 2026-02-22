<?php

namespace ClaudeAgentSDK\Tests\Unit\Options;

use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;
use PHPUnit\Framework\TestCase;

class ClaudeAgentOptionsTest extends TestCase
{
    public function test_make_creates_instance(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertInstanceOf(ClaudeAgentOptions::class, $opts);
        $this->assertSame([], $opts->allowedTools);
        $this->assertNull($opts->model);
    }

    public function test_fluent_tools(): void
    {
        $opts = ClaudeAgentOptions::make()->tools(['Read', 'Write', 'Bash']);

        $this->assertSame(['Read', 'Write', 'Bash'], $opts->allowedTools);
    }

    public function test_fluent_disallow(): void
    {
        $opts = ClaudeAgentOptions::make()->disallow(['WebFetch']);

        $this->assertSame(['WebFetch'], $opts->disallowedTools);
    }

    public function test_fluent_model(): void
    {
        $opts = ClaudeAgentOptions::make()->model('claude-sonnet-4-5-20250929');

        $this->assertSame('claude-sonnet-4-5-20250929', $opts->model);
    }

    public function test_fluent_permission(): void
    {
        $opts = ClaudeAgentOptions::make()->permission('bypassPermissions');

        $this->assertSame('bypassPermissions', $opts->permissionMode);
    }

    public function test_fluent_max_turns(): void
    {
        $opts = ClaudeAgentOptions::make()->maxTurns(10);

        $this->assertSame(10, $opts->maxTurns);
    }

    public function test_fluent_cwd(): void
    {
        $opts = ClaudeAgentOptions::make()->cwd('/tmp/project');

        $this->assertSame('/tmp/project', $opts->cwd);
    }

    public function test_fluent_resume(): void
    {
        $opts = ClaudeAgentOptions::make()->resume('sess_123');

        $this->assertSame('sess_123', $opts->resume);
        $this->assertFalse($opts->forkSession);
    }

    public function test_fluent_resume_with_fork(): void
    {
        $opts = ClaudeAgentOptions::make()->resume('sess_123', fork: true);

        $this->assertSame('sess_123', $opts->resume);
        $this->assertTrue($opts->forkSession);
    }

    public function test_fluent_system_prompt_string(): void
    {
        $opts = ClaudeAgentOptions::make()->systemPrompt('You are a PHP expert.');

        $this->assertSame('You are a PHP expert.', $opts->systemPrompt);
    }

    public function test_fluent_claude_code_prompt(): void
    {
        $opts = ClaudeAgentOptions::make()->useClaudeCodePrompt();

        $this->assertSame([
            'type' => 'preset',
            'preset' => 'claude_code',
        ], $opts->systemPrompt);
    }

    public function test_fluent_claude_code_prompt_with_append(): void
    {
        $opts = ClaudeAgentOptions::make()->useClaudeCodePrompt('Follow PSR-12.');

        $this->assertSame([
            'type' => 'preset',
            'preset' => 'claude_code',
            'append' => 'Follow PSR-12.',
        ], $opts->systemPrompt);
    }

    public function test_fluent_output_format(): void
    {
        $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]];
        $opts = ClaudeAgentOptions::make()->outputFormat($schema);

        $this->assertSame([
            'type' => 'json_schema',
            'schema' => $schema,
        ], $opts->outputFormat);
    }

    public function test_fluent_mcp_server(): void
    {
        $config = McpServerConfig::stdio('npx', ['server-db']);
        $opts = ClaudeAgentOptions::make()->mcpServer('db', $config);

        $this->assertArrayHasKey('db', $opts->mcpServers);
        $this->assertSame($config, $opts->mcpServers['db']);
    }

    public function test_fluent_agent(): void
    {
        $def = new AgentDefinition('A reviewer', 'Review code', ['Read']);
        $opts = ClaudeAgentOptions::make()->agent('reviewer', $def);

        $this->assertArrayHasKey('reviewer', $opts->agents);
        $this->assertSame($def, $opts->agents['reviewer']);
    }

    public function test_fluent_agent_from_array(): void
    {
        $opts = ClaudeAgentOptions::make()->agent('reviewer', [
            'description' => 'Reviews code',
            'prompt' => 'Review it',
        ]);

        $this->assertInstanceOf(AgentDefinition::class, $opts->agents['reviewer']);
        $this->assertSame('Reviews code', $opts->agents['reviewer']->description);
    }

    public function test_fluent_env(): void
    {
        $opts = ClaudeAgentOptions::make()->env('MY_KEY', 'my_val');

        $this->assertSame(['MY_KEY' => 'my_val'], $opts->env);
    }

    public function test_fluent_setting_sources(): void
    {
        $opts = ClaudeAgentOptions::make()->settingSources(['project', 'user']);

        $this->assertSame(['project', 'user'], $opts->settingSources);
    }

    public function test_fluent_sandbox(): void
    {
        $opts = ClaudeAgentOptions::make()->sandbox(['type' => 'docker']);

        $this->assertSame(['type' => 'docker'], $opts->sandbox);
    }

    public function test_fluent_plugin(): void
    {
        $opts = ClaudeAgentOptions::make()->plugin('/path/to/plugin');

        $this->assertSame([['type' => 'local', 'path' => '/path/to/plugin']], $opts->plugins);
    }

    // --- fromArray ---

    public function test_from_array_snake_case(): void
    {
        $opts = ClaudeAgentOptions::fromArray([
            'allowed_tools' => ['Read', 'Bash'],
            'permission_mode' => 'acceptEdits',
            'max_turns' => 5,
            'model' => 'claude-sonnet-4-5-20250929',
        ]);

        $this->assertSame(['Read', 'Bash'], $opts->allowedTools);
        $this->assertSame('acceptEdits', $opts->permissionMode);
        $this->assertSame(5, $opts->maxTurns);
        $this->assertSame('claude-sonnet-4-5-20250929', $opts->model);
    }

    public function test_from_array_ignores_unknown_keys(): void
    {
        $opts = ClaudeAgentOptions::fromArray([
            'nonexistent_option' => 'value',
            'model' => 'test',
        ]);

        $this->assertSame('test', $opts->model);
    }

    // --- toCliArgs ---

    public function test_cli_args_minimal(): void
    {
        $args = ClaudeAgentOptions::make()->toCliArgs();

        $this->assertSame(['--output-format', 'stream-json'], $args);
    }

    public function test_cli_args_full(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->model('claude-sonnet-4-5-20250929')
            ->permission('acceptEdits')
            ->maxTurns(10)
            ->tools(['Read', 'Write'])
            ->disallow(['Bash'])
            ->resume('sess_1');

        $args = $opts->toCliArgs();

        $this->assertContains('--model', $args);
        $this->assertContains('claude-sonnet-4-5-20250929', $args);
        $this->assertContains('--permission-mode', $args);
        $this->assertContains('acceptEdits', $args);
        $this->assertContains('--max-turns', $args);
        $this->assertContains('10', $args);
        $this->assertContains('--allowed-tools', $args);
        $this->assertContains('Read,Write', $args);
        $this->assertContains('--disallowed-tools', $args);
        $this->assertContains('Bash', $args);
        $this->assertContains('--resume', $args);
        $this->assertContains('sess_1', $args);
    }

    public function test_cli_args_system_prompt_string(): void
    {
        $opts = ClaudeAgentOptions::make()->systemPrompt('Be helpful');
        $args = $opts->toCliArgs();

        $idx = array_search('--system-prompt', $args);
        $this->assertNotFalse($idx);
        $this->assertSame('Be helpful', $args[$idx + 1]);
    }

    public function test_cli_args_system_prompt_array(): void
    {
        $opts = ClaudeAgentOptions::make()->useClaudeCodePrompt('Extra');
        $args = $opts->toCliArgs();

        $idx = array_search('--system-prompt', $args);
        $this->assertNotFalse($idx);
        $decoded = json_decode($args[$idx + 1], true);
        $this->assertSame('claude_code', $decoded['preset']);
        $this->assertSame('Extra', $decoded['append']);
    }

    public function test_cli_args_fork_session(): void
    {
        $opts = ClaudeAgentOptions::make()->resume('sess_1', fork: true);
        $args = $opts->toCliArgs();

        $this->assertContains('--fork-session', $args);
    }

    public function test_cli_args_continue(): void
    {
        $opts = ClaudeAgentOptions::make();
        $opts->continueConversation = true;
        $args = $opts->toCliArgs();

        $this->assertContains('--continue', $args);
    }

    public function test_cli_args_output_format_json_schema(): void
    {
        $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]];
        $opts = ClaudeAgentOptions::make()->outputFormat($schema);
        $args = $opts->toCliArgs();

        $this->assertContains('--output-format-json-schema', $args);
        $idx = array_search('--output-format-json-schema', $args);
        $decoded = json_decode($args[$idx + 1], true);
        $this->assertSame($schema, $decoded);
    }

    public function test_cli_args_agents(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->agent('reviewer', new AgentDefinition('Reviews', 'Review code', ['Read']));
        $args = $opts->toCliArgs();

        $this->assertContains('--agents', $args);
        $idx = array_search('--agents', $args);
        $decoded = json_decode($args[$idx + 1], true);
        $this->assertArrayHasKey('reviewer', $decoded);
        $this->assertSame('Reviews', $decoded['reviewer']['description']);
    }

    public function test_cli_args_mcp_servers(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->mcpServer('db', McpServerConfig::stdio('npx', ['server']));
        $args = $opts->toCliArgs();

        $this->assertContains('--mcp-servers', $args);
    }

    public function test_cli_args_enable_file_checkpointing(): void
    {
        $opts = ClaudeAgentOptions::make();
        $opts->enableFileCheckpointing = true;
        $args = $opts->toCliArgs();

        $this->assertContains('--enable-file-checkpointing', $args);
    }

    public function test_cli_args_user(): void
    {
        $opts = ClaudeAgentOptions::make();
        $opts->user = 'user_123';
        $args = $opts->toCliArgs();

        $this->assertContains('--user', $args);
        $this->assertContains('user_123', $args);
    }

    public function test_cli_args_extra_args(): void
    {
        $opts = ClaudeAgentOptions::make();
        $opts->extraArgs = ['verbose' => null, 'level' => '3'];
        $args = $opts->toCliArgs();

        $this->assertContains('--verbose', $args);
        $this->assertContains('--level', $args);
        $this->assertContains('3', $args);
    }

    // --- toEnv ---

    public function test_to_env_merges_defaults(): void
    {
        $opts = ClaudeAgentOptions::make()->env('MY_KEY', 'val');
        $env = $opts->toEnv(['ANTHROPIC_API_KEY' => 'sk-test']);

        $this->assertSame('sk-test', $env['ANTHROPIC_API_KEY']);
        $this->assertSame('val', $env['MY_KEY']);
    }

    public function test_to_env_option_overrides_default(): void
    {
        $opts = ClaudeAgentOptions::make()->env('KEY', 'override');
        $env = $opts->toEnv(['KEY' => 'default']);

        $this->assertSame('override', $env['KEY']);
    }
}