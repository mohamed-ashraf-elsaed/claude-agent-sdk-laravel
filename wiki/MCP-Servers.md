# MCP Servers

> Connect Claude to external tool servers using the Model Context Protocol (MCP), giving your agent access to databases, APIs, and third-party services through a standardized interface.

## Overview

The Model Context Protocol (MCP) is an open standard that lets you extend Claude's capabilities by connecting it to external tool servers. Instead of building custom tool integrations, you configure MCP servers that expose tools Claude can call automatically.

The SDK supports two MCP transports:

| Transport | How it works | Best for |
|---|---|---|
| **Stdio** | Spawns a subprocess; communicates over stdin/stdout | Local tools, CLI-based servers, database access |
| **SSE** | Connects to a remote HTTP endpoint via Server-Sent Events | Remote APIs, shared servers, cloud-hosted tools |

Both transports are configured through the `McpServerConfig` class and passed to `ClaudeAgentOptions` via the `mcpServer()` method. The SDK serializes them as the `--mcp-servers` CLI argument.

## Stdio Transport

Stdio servers run as subprocesses alongside the Claude CLI process. The SDK spawns the server command, and Claude communicates with it over standard input/output.

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-sqlite', '/path/to/database.db'],
        env: ['NODE_ENV' => 'production'],
    ))
    ->tools(['mcp__database__read_query', 'mcp__database__list_tables']);

$result = ClaudeAgent::query('What tables exist and how many users are there?', $options);
echo $result->text();
```

The `McpServerConfig::stdio()` factory creates a config that serializes to:

```json
{"command": "npx", "args": ["-y", "@modelcontextprotocol/server-sqlite", "/path/to/database.db"], "env": {"NODE_ENV": "production"}}
```

## SSE Transport

SSE servers run as remote HTTP services. Claude connects to the server's endpoint and communicates via Server-Sent Events. This is ideal for shared infrastructure, cloud-hosted tools, or servers that need to persist state across multiple queries.

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('remote-api', McpServerConfig::sse(
        url: 'https://mcp.example.com/sse',
        headers: ['Authorization' => 'Bearer ' . config('services.mcp.token')],
    ))
    ->tools(['mcp__remote-api__search', 'mcp__remote-api__fetch']);

$result = ClaudeAgent::query('Search for recent orders', $options);
```

The `McpServerConfig::sse()` factory creates a config that serializes to:

```json
{"type": "sse", "url": "https://mcp.example.com/sse", "headers": {"Authorization": "Bearer ..."}}
```

> **Tip:** SSE servers must be running and reachable before the query starts. The SDK does not manage their lifecycle.

## Referencing MCP Tools

MCP tools follow a strict naming convention: `mcp__<server-name>__<tool-name>`. The server name is the first argument you pass to `mcpServer()`, and the tool name is defined by the MCP server itself.

```php
$options = ClaudeAgentOptions::make()
    ->mcpServer('github', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-github'],
        env: ['GITHUB_TOKEN' => config('services.github.token')],
    ))
    ->tools([
        'mcp__github__list_pull_requests',
        'mcp__github__get_issue',
        'mcp__github__create_issue',
        'Read',   // You can mix MCP tools with built-in tools
    ]);
```

> **Note:** If you do not restrict tools with `tools()`, Claude will have access to all tools exposed by every connected MCP server. Explicitly listing tools is recommended for security and cost control.

## Multiple Servers

You can connect several MCP servers in a single query, mixing stdio and SSE transports freely:

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('db', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-sqlite', database_path('app.db')],
    ))
    ->mcpServer('search', McpServerConfig::sse(
        url: 'https://search.internal.example.com/mcp',
    ))
    ->mcpServer('slack', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-slack'],
        env: ['SLACK_BOT_TOKEN' => config('services.slack.bot_token')],
    ))
    ->tools([
        'mcp__db__read_query',
        'mcp__search__search_documents',
        'mcp__slack__post_message',
    ]);
```

> **Tip:** Each server name must be unique. The name is used in the `mcp__<name>__<tool>` convention, so choose short, descriptive names like `db`, `github`, or `slack`.

## Common MCP Servers

### Database (SQLite / PostgreSQL)

Query a database directly from your agent:

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Tools\McpServerConfig;

$options = ClaudeAgentOptions::make()
    ->mcpServer('database', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-postgres'],
        env: ['DATABASE_URL' => config('database.connections.pgsql.url')],
    ))
    ->tools([
        'mcp__database__query',
        'mcp__database__list_tables',
        'mcp__database__describe_table',
    ]);

$result = ClaudeAgent::query(
    'Show me the top 10 customers by total order value this month',
    $options,
);
```

### GitHub

Let Claude interact with GitHub repositories, issues, and pull requests:

```php
$options = ClaudeAgentOptions::make()
    ->mcpServer('github', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-github'],
        env: ['GITHUB_TOKEN' => config('services.github.token')],
    ))
    ->tools([
        'mcp__github__list_pull_requests',
        'mcp__github__get_pull_request',
        'mcp__github__create_issue',
        'mcp__github__list_issues',
    ]);

$result = ClaudeAgent::query('Summarize all open PRs in the repo', $options);
```

### Slack

Send messages and read channels through the Slack MCP server:

```php
$options = ClaudeAgentOptions::make()
    ->mcpServer('slack', McpServerConfig::stdio(
        command: 'npx',
        args: ['-y', '@anthropic/mcp-server-slack'],
        env: ['SLACK_BOT_TOKEN' => config('services.slack.bot_token')],
    ))
    ->tools(['mcp__slack__post_message', 'mcp__slack__list_channels']);

$result = ClaudeAgent::query('Post a summary of today\'s deploys to #engineering', $options);
```

## Authentication Patterns

### API Keys via Environment Variables (Stdio)

Pass API keys and secrets as environment variables to stdio servers. These are scoped to the subprocess and never exposed to Claude's context:

```php
McpServerConfig::stdio(
    command: 'npx',
    args: ['-y', '@modelcontextprotocol/server-github'],
    env: [
        'GITHUB_TOKEN' => config('services.github.token'),
    ],
)
```

### Bearer Tokens via Headers (SSE)

SSE servers accept authentication through HTTP headers:

```php
McpServerConfig::sse(
    url: 'https://mcp.example.com/sse',
    headers: [
        'Authorization' => 'Bearer ' . config('services.mcp.api_key'),
        'X-Workspace-Id' => config('services.mcp.workspace'),
    ],
)
```

> **Warning:** Never hardcode API keys or tokens directly in your source code. Always pull credentials from environment variables or Laravel's `config()` helper, which reads from your `.env` file.

## Troubleshooting MCP

**Server not starting** -- Verify the MCP server command runs successfully outside the SDK. For npx-based servers, ensure Node.js is installed and the package name is correct. Check that the `command` path is accessible to the user running your Laravel application.

**Tool not found** -- Double-check the tool naming convention: `mcp__<server-name>__<tool-name>`. The server name must exactly match the first argument to `mcpServer()`. Run the MCP server independently to list its available tools.

**Timeout issues** -- MCP servers that take a long time to start (e.g., downloading npm packages on first run) may cause the query to time out. Pre-install packages or increase the `process_timeout` in your [[Configuration]].

**Authentication failures** -- Ensure environment variables and headers contain valid, non-expired credentials. For SSE servers, verify the endpoint URL is reachable from your server.

> **Tip:** Test your MCP server commands directly in the terminal before configuring them in the SDK. For example, run `npx -y @modelcontextprotocol/server-sqlite /path/to/db` and verify it starts without errors.

## Next Steps

- [[Options Reference]] -- Full list of all available fluent options
- [[Basic Usage]] -- Core query and result handling
- [[Subagents]] -- Delegate tasks to specialized agents
- [[Configuration]] -- Set timeouts, models, and global defaults
