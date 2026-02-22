# MCP Servers

The Model Context Protocol lets you connect external tool servers.

## Stdio Transport

The server runs as a subprocess:
```php
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['@modelcontextprotocol/server-database'],
        env: ['DB_URL' => config('database.url')],
    ))
    ->mcpServer('github', McpServerConfig::stdio(
        command: 'npx',
        args: ['@modelcontextprotocol/server-github'],
        env: ['GITHUB_TOKEN' => config('services.github.token')],
    ))
    ->tools(['mcp__database__query', 'mcp__github__list_prs', 'Read']);
```

## SSE Transport

Connect to a remote MCP server over HTTP:
```php
$options = ClaudeAgentOptions::make()
    ->mcpServer('remote-api', McpServerConfig::sse(
        url: 'http://localhost:3000/mcp',
        headers: ['Authorization' => 'Bearer ' . $token],
    ));
```

## Multiple Servers
```php
$options = ClaudeAgentOptions::make()
    ->mcpServer('db', McpServerConfig::stdio('npx', ['@mcp/server-sqlite']))
    ->mcpServer('search', McpServerConfig::sse('https://search.example.com/mcp'))
    ->mcpServer('slack', McpServerConfig::stdio('npx', ['@mcp/server-slack']));
```