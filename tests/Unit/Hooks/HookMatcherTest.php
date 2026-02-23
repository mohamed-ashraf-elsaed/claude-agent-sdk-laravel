<?php

namespace ClaudeAgentSDK\Tests\Unit\Hooks;

use ClaudeAgentSDK\Hooks\HookMatcher;
use PHPUnit\Framework\TestCase;

class HookMatcherTest extends TestCase
{
    public function test_constructor(): void
    {
        $matcher = new HookMatcher('/Read|Write/', ['php /hooks/lint.php'], 30);

        $this->assertSame('/Read|Write/', $matcher->matcher);
        $this->assertSame(['php /hooks/lint.php'], $matcher->hooks);
        $this->assertSame(30, $matcher->timeout);
    }

    public function test_defaults(): void
    {
        $matcher = new HookMatcher();

        $this->assertNull($matcher->matcher);
        $this->assertSame([], $matcher->hooks);
        $this->assertNull($matcher->timeout);
    }

    public function test_command_factory(): void
    {
        $matcher = HookMatcher::command('eslint --fix', '/Edit|Write/', 60);

        $this->assertSame('/Edit|Write/', $matcher->matcher);
        $this->assertSame(['eslint --fix'], $matcher->hooks);
        $this->assertSame(60, $matcher->timeout);
    }

    public function test_command_factory_minimal(): void
    {
        $matcher = HookMatcher::command('echo done');

        $this->assertNull($matcher->matcher);
        $this->assertSame(['echo done'], $matcher->hooks);
        $this->assertNull($matcher->timeout);
    }

    public function test_php_script_factory(): void
    {
        $matcher = HookMatcher::phpScript('/hooks/validate.php', '/Bash/', 10);

        $this->assertSame('/Bash/', $matcher->matcher);
        $this->assertSame(10, $matcher->timeout);
        $this->assertCount(1, $matcher->hooks);
        $this->assertStringContainsString('php', $matcher->hooks[0]);
        $this->assertStringContainsString('/hooks/validate.php', $matcher->hooks[0]);
    }

    public function test_to_array_full(): void
    {
        $matcher = new HookMatcher('/Edit/', ['php lint.php', 'echo ok'], 30);
        $arr = $matcher->toArray();

        $this->assertSame([
            'matcher' => '/Edit/',
            'hooks' => ['php lint.php', 'echo ok'],
            'timeout' => 30,
        ], $arr);
    }

    public function test_to_array_filters_empty(): void
    {
        $matcher = new HookMatcher(null, ['echo ok']);
        $arr = $matcher->toArray();

        $this->assertArrayNotHasKey('matcher', $arr);
        $this->assertArrayNotHasKey('timeout', $arr);
        $this->assertSame(['echo ok'], $arr['hooks']);
    }

    public function test_to_array_filters_empty_hooks(): void
    {
        $matcher = new HookMatcher('/Edit/');
        $arr = $matcher->toArray();

        $this->assertArrayNotHasKey('hooks', $arr);
        $this->assertSame('/Edit/', $arr['matcher']);
    }

    public function test_json_serialize(): void
    {
        $matcher = new HookMatcher('/Bash/', ['php check.php'], 15);
        $json = json_encode($matcher);
        $decoded = json_decode($json, true);

        $this->assertSame('/Bash/', $decoded['matcher']);
        $this->assertSame(['php check.php'], $decoded['hooks']);
        $this->assertSame(15, $decoded['timeout']);
    }

    public function test_multiple_hook_commands(): void
    {
        $matcher = new HookMatcher(
            '/Write/',
            ['php /hooks/backup.php', 'php /hooks/validate.php', 'php /hooks/notify.php'],
            120,
        );

        $this->assertCount(3, $matcher->hooks);
        $arr = $matcher->toArray();
        $this->assertCount(3, $arr['hooks']);
    }
}