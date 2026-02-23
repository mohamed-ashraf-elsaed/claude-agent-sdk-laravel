<?php

namespace ClaudeAgentSDK\Tests\Unit\Options;

use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;
use PHPUnit\Framework\TestCase;

class ClaudeAgentOptionsTest extends TestCase
{
    // --- Core fluent setters ---

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

    public function test_fluent_continue_conversation(): void
    {
        $opts = ClaudeAgentOptions::make()->continueConversation();

        $this->assertTrue($opts->continueConversation);
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

    public function test_fluent_settings(): void
    {
        $opts = ClaudeAgentOptions::make()->settings('/path/settings.json');

        $this->assertSame('/path/settings.json', $opts->settings);
    }

    public function test_fluent_add_dir(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->addDir('/extra/dir1')
            ->addDir('/extra/dir2');

        $this->assertSame(['/extra/dir1', '/extra/dir2'], $opts->addDirs);
    }

    public function test_fluent_user(): void
    {
        $opts = ClaudeAgentOptions::make()->user('user_123');

        $this->assertSame('user_123', $opts->user);
    }

    public function test_fluent_extra_arg(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->extraArg('verbose')
            ->extraArg('level', '3');

        $this->assertSame(['verbose' => null, 'level' => '3'], $opts->extraArgs);
    }

    public function test_fluent_enable_file_checkpointing(): void
    {
        $opts = ClaudeAgentOptions::make()->enableFileCheckpointing();

        $this->assertTrue($opts->enableFileCheckpointing);
    }

    public function test_fluent_enable_file_checkpointing_false(): void
    {
        $opts = ClaudeAgentOptions::make()->enableFileCheckpointing(false);

        $this->assertFalse($opts->enableFileCheckpointing);
    }

    public function test_fluent_include_partial_messages(): void
    {
        $opts = ClaudeAgentOptions::make()->includePartialMessages();

        $this->assertTrue($opts->includePartialMessages);
    }

    public function test_fluent_include_partial_messages_false(): void
    {
        $opts = ClaudeAgentOptions::make()->includePartialMessages(false);

        $this->assertFalse($opts->includePartialMessages);
    }

    public function test_fluent_max_budget_usd(): void
    {
        $opts = ClaudeAgentOptions::make()->maxBudgetUsd(5.50);

        $this->assertSame(5.50, $opts->maxBudgetUsd);
    }

    public function test_fluent_max_thinking_tokens(): void
    {
        $opts = ClaudeAgentOptions::make()->maxThinkingTokens(10000);

        $this->assertSame(10000, $opts->maxThinkingTokens);
    }

    public function test_fluent_fallback_model(): void
    {
        $opts = ClaudeAgentOptions::make()->fallbackModel('claude-haiku-4-5');

        $this->assertSame('claude-haiku-4-5', $opts->fallbackModel);
    }

    public function test_fluent_betas(): void
    {
        $opts = ClaudeAgentOptions::make()->betas(['context-1m-2025-08-07']);

        $this->assertSame(['context-1m-2025-08-07'], $opts->betas);
    }

    // --- Hooks ---

    public function test_fluent_hook(): void
    {
        $matcher = HookMatcher::command('php lint.php', '/Edit|Write/');
        $opts = ClaudeAgentOptions::make()->hook(HookEvent::PreToolUse, $matcher);

        $this->assertNotNull($opts->hooks);
        $this->assertArrayHasKey('PreToolUse', $opts->hooks);
        $this->assertCount(1, $opts->hooks['PreToolUse']);
        $this->assertSame($matcher, $opts->hooks['PreToolUse'][0]);
    }

    public function test_fluent_hook_multiple_matchers_same_event(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->hook(HookEvent::PreToolUse, HookMatcher::command('php lint.php', '/Edit/'))
            ->hook(HookEvent::PreToolUse, HookMatcher::command('php audit.php', '/Bash/'));

        $this->assertCount(2, $opts->hooks['PreToolUse']);
    }

    public function test_fluent_hook_multiple_events(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->hook(HookEvent::PreToolUse, HookMatcher::command('php before.php'))
            ->hook(HookEvent::PostToolUse, HookMatcher::command('php after.php'))
            ->hook(HookEvent::Stop, HookMatcher::command('php cleanup.php'));

        $this->assertCount(3, $opts->hooks);
        $this->assertArrayHasKey('PreToolUse', $opts->hooks);
        $this->assertArrayHasKey('PostToolUse', $opts->hooks);
        $this->assertArrayHasKey('Stop', $opts->hooks);
    }

    public function test_fluent_pre_tool_use_shorthand(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->preToolUse('php lint.php', '/Edit|Write/', 30);

        $this->assertCount(1, $opts->hooks['PreToolUse']);
        $matcher = $opts->hooks['PreToolUse'][0];
        $this->assertSame('/Edit|Write/', $matcher->matcher);
        $this->assertSame(['php lint.php'], $matcher->hooks);
        $this->assertSame(30, $matcher->timeout);
    }

    public function test_fluent_post_tool_use_shorthand(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->postToolUse('php notify.php', '/Bash/');

        $this->assertCount(1, $opts->hooks['PostToolUse']);
        $this->assertSame('/Bash/', $opts->hooks['PostToolUse'][0]->matcher);
    }

    public function test_hooks_null_by_default(): void
    {
        $opts = ClaudeAgentOptions::make();

        $this->assertNull($opts->hooks);
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

    public function test_from_array_new_options(): void
    {
        $opts = ClaudeAgentOptions::fromArray([
            'max_budget_usd' => 10.0,
            'max_thinking_tokens' => 5000,
            'fallback_model' => 'claude-haiku-4-5',
            'betas' => ['context-1m-2025-08-07'],
            'include_partial_messages' => true,
        ]);

        $this->assertSame(10.0, $opts->maxBudgetUsd);
        $this->assertSame(5000, $opts->maxThinkingTokens);
        $this->assertSame('claude-haiku-4-5', $opts->fallbackModel);
        $this->assertSame(['context-1m-2025-08-07'], $opts->betas);
        $this->assertTrue($opts->includePartialMessages);
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
        $opts = ClaudeAgentOptions::make()->continueConversation();
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

    public function test_cli_args_hooks(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->hook(HookEvent::PreToolUse, new HookMatcher('/Edit|Write/', ['php lint.php'], 30))
            ->hook(HookEvent::PostToolUse, HookMatcher::command('php notify.php'));

        $args = $opts->toCliArgs();

        $this->assertContains('--hooks', $args);
        $idx = array_search('--hooks', $args);
        $decoded = json_decode($args[$idx + 1], true);

        $this->assertArrayHasKey('PreToolUse', $decoded);
        $this->assertArrayHasKey('PostToolUse', $decoded);
        $this->assertCount(1, $decoded['PreToolUse']);
        $this->assertSame('/Edit|Write/', $decoded['PreToolUse'][0]['matcher']);
        $this->assertSame(['php lint.php'], $decoded['PreToolUse'][0]['hooks']);
        $this->assertSame(30, $decoded['PreToolUse'][0]['timeout']);
    }

    public function test_cli_args_hooks_multiple_matchers(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->preToolUse('php check-edits.php', '/Edit/')
            ->preToolUse('php check-bash.php', '/Bash/', 10);

        $args = $opts->toCliArgs();
        $idx = array_search('--hooks', $args);
        $decoded = json_decode($args[$idx + 1], true);

        $this->assertCount(2, $decoded['PreToolUse']);
        $this->assertSame('/Edit/', $decoded['PreToolUse'][0]['matcher']);
        $this->assertSame('/Bash/', $decoded['PreToolUse'][1]['matcher']);
        $this->assertSame(10, $decoded['PreToolUse'][1]['timeout']);
    }

    public function test_cli_args_hooks_not_present_when_null(): void
    {
        $args = ClaudeAgentOptions::make()->toCliArgs();

        $this->assertNotContains('--hooks', $args);
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
        $opts = ClaudeAgentOptions::make()->enableFileCheckpointing();
        $args = $opts->toCliArgs();

        $this->assertContains('--enable-file-checkpointing', $args);
    }

    public function test_cli_args_include_partial_messages(): void
    {
        $opts = ClaudeAgentOptions::make()->includePartialMessages();
        $args = $opts->toCliArgs();

        $this->assertContains('--include-partial-messages', $args);
    }

    public function test_cli_args_user(): void
    {
        $opts = ClaudeAgentOptions::make()->user('user_123');
        $args = $opts->toCliArgs();

        $this->assertContains('--user', $args);
        $this->assertContains('user_123', $args);
    }

    public function test_cli_args_settings(): void
    {
        $opts = ClaudeAgentOptions::make()->settings('/path/settings.json');
        $args = $opts->toCliArgs();

        $this->assertContains('--settings', $args);
        $idx = array_search('--settings', $args);
        $this->assertSame('/path/settings.json', $args[$idx + 1]);
    }

    public function test_cli_args_add_dirs(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->addDir('/extra/a')
            ->addDir('/extra/b');
        $args = $opts->toCliArgs();

        $indices = array_keys(array_filter($args, fn($v) => $v === '--add-dir'));
        $this->assertCount(2, $indices);
    }

    public function test_cli_args_extra_args(): void
    {
        $opts = ClaudeAgentOptions::make()
            ->extraArg('verbose')
            ->extraArg('level', '3');
        $args = $opts->toCliArgs();

        $this->assertContains('--verbose', $args);
        $this->assertContains('--level', $args);
        $this->assertContains('3', $args);
    }

    public function test_cli_args_max_budget_usd(): void
    {
        $opts = ClaudeAgentOptions::make()->maxBudgetUsd(2.5);
        $args = $opts->toCliArgs();

        $this->assertContains('--max-budget-usd', $args);
        $idx = array_search('--max-budget-usd', $args);
        $this->assertSame('2.5', $args[$idx + 1]);
    }

    public function test_cli_args_max_thinking_tokens(): void
    {
        $opts = ClaudeAgentOptions::make()->maxThinkingTokens(8000);
        $args = $opts->toCliArgs();

        $this->assertContains('--max-thinking-tokens', $args);
        $this->assertContains('8000', $args);
    }

    public function test_cli_args_fallback_model(): void
    {
        $opts = ClaudeAgentOptions::make()->fallbackModel('claude-haiku-4-5');
        $args = $opts->toCliArgs();

        $this->assertContains('--fallback-model', $args);
        $this->assertContains('claude-haiku-4-5', $args);
    }

    public function test_cli_args_betas(): void
    {
        $opts = ClaudeAgentOptions::make()->betas(['context-1m-2025-08-07']);
        $args = $opts->toCliArgs();

        $this->assertContains('--beta', $args);
        $this->assertContains('context-1m-2025-08-07', $args);
    }

    public function test_cli_args_absent_when_not_set(): void
    {
        $args = ClaudeAgentOptions::make()->toCliArgs();

        $absent = [
            '--max-budget-usd', '--max-thinking-tokens', '--fallback-model',
            '--beta', '--settings', '--add-dir', '--user',
            '--enable-file-checkpointing', '--continue', '--hooks',
            '--include-partial-messages', '--model', '--permission-mode',
            '--max-turns', '--resume', '--fork-session', '--system-prompt',
            '--allowed-tools', '--disallowed-tools', '--mcp-servers',
            '--agents', '--plugins', '--sandbox',
        ];

        foreach ($absent as $flag) {
            $this->assertNotContains($flag, $args, "Flag {$flag} should not be present");
        }
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