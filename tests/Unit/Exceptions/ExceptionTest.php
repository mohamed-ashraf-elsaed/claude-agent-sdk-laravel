<?php

namespace ClaudeAgentSDK\Tests\Unit\Exceptions;

use ClaudeAgentSDK\Exceptions\ClaudeAgentException;
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionTest extends TestCase
{
    public function test_claude_agent_exception(): void
    {
        $e = new ClaudeAgentException('Something went wrong');

        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('Something went wrong', $e->getMessage());
    }

    public function test_cli_not_found_exception(): void
    {
        $e = new CliNotFoundException('/usr/bin/claude');

        $this->assertStringContainsString('/usr/bin/claude', $e->getMessage());
        $this->assertStringContainsString('npm install', $e->getMessage());
    }

    public function test_cli_not_found_default_path(): void
    {
        $e = new CliNotFoundException();

        $this->assertStringContainsString('claude', $e->getMessage());
    }

    public function test_json_parse_exception(): void
    {
        $prev = new RuntimeException('bad json');
        $e = new JsonParseException('{invalid', $prev);

        $this->assertSame('{invalid', $e->rawLine);
        $this->assertSame($prev, $e->originalError);
        $this->assertStringContainsString('{invalid', $e->getMessage());
    }

    public function test_process_exception(): void
    {
        $e = new ProcessException('Process failed', 1, 'error output');

        $this->assertSame(1, $e->exitCode);
        $this->assertSame('error output', $e->stderr);
        $this->assertSame('Process failed', $e->getMessage());
    }
}