# Claude Agent SDK for Laravel

[![Tests](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/actions) [![Latest Version](https://img.shields.io/packagist/v/mohamed-ashraf-elsaed/claude-agent-sdk-laravel)](https://packagist.org/packages/mohamed-ashraf-elsaed/claude-agent-sdk-laravel) [![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE) [![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](https://php.net) [![Laravel 10/11/12](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-FF2D20)](https://laravel.com)

> Build AI agents powered by Claude Code directly in your Laravel applications.

The Claude Agent SDK for Laravel provides a fluent PHP interface for the Claude Code CLI, enabling your applications to leverage Claude's full tool-use capabilities — file operations, code analysis, web search, shell commands, and more — all from clean, idiomatic Laravel code.

## Quick Example

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$result = ClaudeAgent::query(
    'Analyze the app/Models directory for security issues',
    ClaudeAgentOptions::make()
        ->tools(['Read', 'Grep', 'Glob'])
        ->maxTurns(10)
        ->maxBudgetUsd(5.00)
);

echo $result->text();           // Analysis summary
echo $result->costUsd();        // e.g. 0.42
echo $result->turns();          // e.g. 7
```

## Highlights

- **Fluent Options Builder** — 30+ chainable methods for tools, models, prompts, budgets, and more
- **Real-Time Streaming** — Process messages as they arrive via Generator or callback
- **Typed Message Objects** — AssistantMessage, ResultMessage, SystemMessage with content blocks
- **Session Management** — Resume, fork, and continue multi-turn conversations
- **Subagent Orchestration** — Define specialized agents that Claude delegates tasks to
- **Structured Output** — Get validated JSON responses conforming to a JSON Schema
- **MCP Server Integration** — Connect external tools via stdio and SSE transports
- **Lifecycle Hooks** — Run shell commands before/after tool execution
- **Budget and Cost Control** — Per-query budget limits with per-model usage tracking and cache metrics
- **Custom API Providers** — AWS Bedrock, Google Vertex, Azure Foundry, or any Anthropic-compatible endpoint
- **Production Ready** — Queue integration, sandboxing, permission modes, and comprehensive error handling

## Documentation

### Getting Started

| Page | Description |
|------|-------------|
| [[Installation]] | Prerequisites, Composer setup, CLI installation, Docker and CI guidance |
| [[Configuration]] | Config file reference, environment variables, override priority |
| [[Getting Started]] | 5-minute tutorial — your first query to full result |

### Core Concepts

| Page | Description |
|------|-------------|
| [[Architecture]] | How the SDK works — data flow, components, process lifecycle |
| [[Basic Usage]] | Facade, dependency injection, Artisan commands, Jobs, result handling |
| [[Streaming]] | Real-time messages, SSE endpoints, WebSocket broadcasting, Livewire |
| [[Working with Messages]] | Message types, content blocks, ModelUsage, filtering patterns |

### Features

| Page | Description |
|------|-------------|
| [[Options Reference]] | Complete fluent API — every method, grouped by category |
| [[System Prompts]] | Custom prompts, Claude Code preset, prompt engineering tips and templates |
| [[Structured Output]] | JSON Schema validation, DTO mapping, schema examples |
| [[Session Management]] | Resume, fork, and continue conversations with storage patterns |
| [[Subagents]] | Specialized agents, orchestration patterns, model selection strategy |
| [[Hooks]] | Pre/post tool-use hooks, all 6 events, PHP script integration |
| [[MCP Servers]] | Model Context Protocol — stdio and SSE transports, common servers |
| [[Budget and Cost Management]] | Budget limits, token usage, cache optimization, cost monitoring |

### Advanced

| Page | Description |
|------|-------------|
| [[Custom API Providers]] | AWS Bedrock, Google Vertex, Azure Foundry, custom base URL |
| [[Security Guide]] | Permission modes, tool restrictions, sandboxing, input validation |
| [[Production Deployment]] | Queue integration, Supervisor, scaling, monitoring, deployment checklist |

### Reference

| Page | Description |
|------|-------------|
| [[Error Handling]] | Exception types, retry strategies, graceful degradation |
| [[Testing Your Integration]] | Facade mocking, fixture builders, streaming tests |
| [[API Reference]] | Every class, method, and property with full signatures |
| [[Troubleshooting and FAQ]] | Common issues, solutions, and frequently asked questions |

## Requirements

| Requirement | Version |
|------------|---------|
| PHP | 8.1, 8.2, 8.3, 8.4 |
| Laravel | 10, 11, 12 |
| Claude Code CLI | Latest (`npm install -g @anthropic-ai/claude-code`) |
| Anthropic API Key | From [console.anthropic.com](https://console.anthropic.com) |

## License

This package is open-sourced software licensed under the [MIT License](https://github.com/mohamed-ashraf-elsaed/claude-agent-sdk-laravel/blob/main/LICENSE).
