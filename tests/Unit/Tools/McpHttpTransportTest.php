<?php

namespace ClaudeAgentSDK\Tests\Unit\Tools;

use ClaudeAgentSDK\Tools\McpServerConfig;
use PHPUnit\Framework\TestCase;

class McpHttpTransportTest extends TestCase
{
    public function test_http_factory(): void
    {
        $cfg = McpServerConfig::http(
            'https://mcp.example.com/v1',
            ['Authorization' => 'Bearer sk-test-key'],
        );

        $this->assertSame('https://mcp.example.com/v1', $cfg->command);
        $this->assertSame('http', $cfg->type);
        $this->assertSame([], $cfg->args);
        $this->assertSame(['Authorization' => 'Bearer sk-test-key'], $cfg->env);
    }

    public function test_http_factory_without_headers(): void
    {
        $cfg = McpServerConfig::http('https://mcp.example.com/api');

        $this->assertSame('https://mcp.example.com/api', $cfg->command);
        $this->assertSame('http', $cfg->type);
        $this->assertSame([], $cfg->args);
        $this->assertSame([], $cfg->env);
    }

    public function test_http_to_array(): void
    {
        $cfg = McpServerConfig::http(
            'https://mcp.example.com/v1',
            ['Authorization' => 'Bearer token123', 'X-Custom' => 'value'],
        );

        $arr = $cfg->toArray();

        $this->assertSame('http', $arr['type']);
        $this->assertSame('https://mcp.example.com/v1', $arr['url']);
        $this->assertSame(
            ['Authorization' => 'Bearer token123', 'X-Custom' => 'value'],
            $arr['headers'],
        );
        $this->assertArrayNotHasKey('command', $arr);
        $this->assertArrayNotHasKey('args', $arr);
        $this->assertArrayNotHasKey('env', $arr);
    }

    public function test_http_to_array_without_headers(): void
    {
        $cfg = McpServerConfig::http('https://mcp.example.com/v1');

        $arr = $cfg->toArray();

        $this->assertSame([
            'type' => 'http',
            'url' => 'https://mcp.example.com/v1',
        ], $arr);
        $this->assertArrayNotHasKey('headers', $arr);
    }

    public function test_http_json_serialize(): void
    {
        $cfg = McpServerConfig::http(
            'https://mcp.example.com/api',
            ['Authorization' => 'Bearer abc'],
        );

        $json = json_encode($cfg);
        $decoded = json_decode($json, true);

        $this->assertSame('http', $decoded['type']);
        $this->assertSame('https://mcp.example.com/api', $decoded['url']);
        $this->assertSame(['Authorization' => 'Bearer abc'], $decoded['headers']);
    }

    public function test_http_is_instance_of_mcp_server_config(): void
    {
        $cfg = McpServerConfig::http('https://example.com');

        $this->assertInstanceOf(McpServerConfig::class, $cfg);
        $this->assertInstanceOf(\JsonSerializable::class, $cfg);
    }
}
