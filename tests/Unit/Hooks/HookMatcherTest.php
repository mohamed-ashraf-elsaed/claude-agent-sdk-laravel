<?php

namespace ClaudeAgentSDK\Tests\Unit\Hooks;

use ClaudeAgentSDK\Hooks\HookMatcher;
use PHPUnit\Framework\TestCase;

class HookMatcherTest extends TestCase
{
    public function test_constructor(): void
    {
        $hook = fn() => true;
        $matcher = new HookMatcher('/Read|Write/', [$hook], 30);

        $this->assertSame('/Read|Write/', $matcher->matcher);
        $this->assertCount(1, $matcher->hooks);
        $this->assertSame(30, $matcher->timeout);
    }

    public function test_defaults(): void
    {
        $matcher = new HookMatcher();

        $this->assertNull($matcher->matcher);
        $this->assertSame([], $matcher->hooks);
        $this->assertNull($matcher->timeout);
    }
}