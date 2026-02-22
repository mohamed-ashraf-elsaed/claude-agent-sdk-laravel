# Options Reference

## All Available Options

| Method                  | Description                              | CLI Flag                    |
|-------------------------|------------------------------------------|-----------------------------|
| `tools(array)`          | Allowed tool names                       | `--allowed-tools`           |
| `disallow(array)`       | Disallowed tool names                    | `--disallowed-tools`        |
| `model(string)`         | Model to use                             | `--model`                   |
| `permission(string)`    | Permission mode                          | `--permission-mode`         |
| `maxTurns(int)`         | Max conversation turns                   | `--max-turns`               |
| `cwd(string)`           | Working directory                        | Process cwd                 |
| `systemPrompt(string)`  | Custom system prompt                     | `--system-prompt`           |
| `useClaudeCodePrompt()` | Use Claude Code's built-in prompt        | `--system-prompt` (JSON)    |
| `resume(string)`        | Resume a session by ID                   | `--resume`                  |
| `resume(string, true)`  | Fork a session                           | `--resume` + `--fork-session`|
| `outputFormat(array)`   | JSON schema for structured output        | `--output-format-json-schema`|
| `mcpServer(name, cfg)`  | Add MCP server                           | `--mcp-servers`             |
| `agent(name, def)`      | Add subagent definition                  | `--agents`                  |
| `settingSources(array)` | Load settings from sources               | `--setting-source`          |
| `sandbox(array)`        | Sandbox configuration                    | `--sandbox`                 |
| `plugin(string)`        | Add a local plugin                       | `--plugins`                 |
| `env(key, value)`       | Set environment variable                 | Process env                 |

## Available Tools

Common Claude Code tools you can allow:

- `Read` — Read file contents
- `Write` — Write/create files
- `Edit` — Edit existing files
- `Bash` — Run shell commands
- `Grep` — Search file contents
- `Glob` — Find files by pattern
- `WebFetch` — Fetch web URLs
- `WebSearch` — Search the web
- `Task` — Delegate to subagents (required for subagents)

MCP tools use the format: `mcp__servername__toolname`