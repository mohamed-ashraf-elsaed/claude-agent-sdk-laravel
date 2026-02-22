<?php

namespace ClaudeAgentSDK\Tests\Unit\Tools;

use ClaudeAgentSDK\Tools\McpServerConfig;
use PHPUnit\Framework\TestCase;

class McpServerConfigTest extends TestCase
{
    public function test_stdio_factory(): void
    {
        $cfg = McpServerConfig::stdio('npx', ['@mcp/server-db'], ['DB_URL' => 'localhost']);

        $this->assertSame('npx', $cfg->command);
        $this->assertSame(['@mcp/server-db'], $cfg->args);
        $this->assertSame(['DB_URL' => 'localhost'], $cfg->env);
        $this->assertSame('stdio', $cfg->type);
    }

    public function test_sse_factory(): void
    {
        $cfg = McpServerConfig::sse('http://localhost:3000', ['Authorization' => 'Bearer x']);

        $this->assertSame('http://localhost:3000', $cfg->command);
        $this->assertSame('sse', $cfg->type);
        $this->assertSame(['Authorization' => 'Bearer x'], $cfg->env);
    }

    public function test_stdio_to_array(): void
    {
        $cfg = McpServerConfig::stdio('npx', ['server'], ['KEY' => 'val']);
        $arr = $cfg->toArray();

        $this->assertSame('npx', $arr['command']);
        $this->assertSame(['server'], $arr['args']);
        $this->assertSame(['KEY' => 'val'], $arr['env']);
        $this->assertArrayNotHasKey('type', $arr);
    }

    public function test_stdio_to_array_filters_empty(): void
    {
        $cfg = McpServerConfig::stdio('npx');
        $arr = $cfg->toArray();

        $this->assertSame(['command' => 'npx'], $arr);
    }

    public function test_sse_to_array(): void
    {
        $cfg = McpServerConfig::sse('http://localhost:3000');
        $arr = $cfg->toArray();

        $this->assertSame([
            'type' => 'sse',
            'url' => 'http://localhost:3000',
        ], $arr);
    }

    public function test_sse_to_array_with_headers(): void
    {
        $cfg = McpServerConfig::sse('http://localhost', ['Auth' => 'Bearer x']);
        $arr = $cfg->toArray();

        $this->assertSame('sse', $arr['type']);
        $this->assertSame('http://localhost', $arr['url']);
        $this->assertSame(['Auth' => 'Bearer x'], $arr['headers']);
    }

    public function test_json_serialize(): void
    {
        $cfg = McpServerConfig::stdio('npx', ['server']);

        $json = json_encode($cfg);
        $decoded = json_decode($json, true);

        $this->assertSame('npx', $decoded['command']);
        $this->assertSame(['server'], $decoded['args']);
    }
}