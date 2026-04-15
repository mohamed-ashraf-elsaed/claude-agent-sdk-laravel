# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel SDK that wraps the Claude Code CLI as a subprocess, exposing it as a PHP library for building AI agents. The SDK spawns `claude` CLI processes via Symfony Process, communicates through JSON-over-stdout, and provides a fluent PHP API for configuration.

## Commands

```bash
# Install dependencies
composer install

# Run full test suite
vendor/bin/phpunit

# Run a single test by name
vendor/bin/phpunit --filter=test_parses_text_block

# Run a test suite (Unit or Feature)
vendor/bin/phpunit --testsuite=Unit

# Run with coverage report
vendor/bin/phpunit --coverage-html coverage/
```

## Architecture

**Entry flow:** `ClaudeAgent` facade → `ClaudeAgentManager` → `ProcessTransport` → Claude Code CLI subprocess

### Core Components

- **`ClaudeAgentManager`** (`src/ClaudeAgentManager.php`) — Main interface. Provides `query()` (sync), `stream()` (generator-based), and `streamCollect()` methods. Registered as a singleton via the service provider.

- **`ProcessTransport`** (`src/Transport/ProcessTransport.php`) — Builds CLI arguments from options, spawns the `claude` process via Symfony Process, parses JSON output line-by-line into Message objects. All CLI interaction is isolated here.

- **`ClaudeAgentOptions`** (`src/Options/ClaudeAgentOptions.php`) — Fluent builder for all configuration (tools, permissions, model, hooks, MCP servers, subagents, etc.). Converts to CLI args via `toArgs()` and environment vars via `toEnv()`.

- **`QueryResult`** (`src/QueryResult.php`) — Wraps the full response. Provides `text()`, `structured()`, cost/turn metrics, and access to individual messages.

### Message/Content Type Hierarchy

`Message::fromJson()` factory dispatches to: `AssistantMessage`, `ResultMessage`, `SystemMessage`, `UserMessage`, `GenericMessage`. Each `AssistantMessage` contains `ContentBlock` objects: `TextBlock`, `ThinkingBlock`, `ToolUseBlock`, `ToolResultBlock`.

### Supporting Systems

- **Hooks** (`src/Hooks/`) — `HookEvent` enum + `HookMatcher` for pre/post tool-use hooks
- **Agents** (`src/Agents/AgentDefinition.php`) — Subagent definitions with model/tools/prompt
- **MCP** (`src/Tools/McpServerConfig.php`) — stdio and SSE server configs
- **Exceptions** (`src/Exceptions/`) — `CliNotFoundException`, `ProcessException`, `JsonParseException`

### Laravel Integration

- **Service Provider** (`src/ClaudeAgentServiceProvider.php`) — Registers singleton, publishes config
- **Facade** (`src/Facades/ClaudeAgent.php`) — Static access via `ClaudeAgent::`
- **Config** (`config/claude-agent.php`) — Defaults loaded from env vars (`ANTHROPIC_API_KEY`, `CLAUDE_AGENT_*`)

## Conventions

- **PHP 8.1+** with readonly properties and named constructor arguments
- **PSR-12** coding standard
- **Naming:** PascalCase classes, camelCase methods/properties, snake_case config keys, snake_case test methods (`test_parses_text_block`)
- **Commit messages:** Conventional Commits — `feat(scope): description`, `fix(scope): description`
- **Branching:** `main` (stable), `develop` (active), `feature/*`, `fix/*`, `docs/*`
- **PRs target `develop`**, not `main`

## CI

GitHub Actions runs PHPUnit across PHP 8.1–8.4 and Laravel 10–13 (excluding PHP 8.1 + Laravel 11/12/13, PHP 8.2 + Laravel 13).
