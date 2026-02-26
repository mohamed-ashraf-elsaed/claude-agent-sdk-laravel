# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-02-26

### Added
- **Custom API provider support** — first-class config for Anthropic-compatible API providers
    - `api_base_url` config key (`ANTHROPIC_BASE_URL` env var) — override the default Anthropic API endpoint
    - `auth_token` config key (`ANTHROPIC_AUTH_TOKEN` env var) — override the auth token for custom providers
- `ProcessTransport` now forwards `ANTHROPIC_BASE_URL` and `ANTHROPIC_AUTH_TOKEN` to the CLI subprocess
- Updated wiki Configuration page with new provider options

## [1.1.0] - 2025-02-23

### Added
- **ModelUsage data class** (`src/Data/ModelUsage.php`) — typed value object for per-model token usage, cost, and cache metrics
    - `totalInputTokens()` — sum of input + cache read + cache creation tokens
    - `cacheHitRate()` — ratio of cache-read tokens to total input (0.0–1.0)
    - Supports both camelCase and snake_case field names with camelCase precedence
- **Budget control** — `maxBudgetUsd` option and config key (`CLAUDE_AGENT_MAX_BUDGET_USD`)
- **Extended thinking control** — `maxThinkingTokens` option and config key (`CLAUDE_AGENT_MAX_THINKING_TOKENS`)
- **Fallback model** — `fallbackModel()` option for automatic model fallback (`--fallback-model`)
- **Beta features** — `betas()` option to enable beta features (`--beta`)
- **Cache token tracking** on `ResultMessage` and `QueryResult`:
    - `parsedModelUsage()` — per-model usage as typed `ModelUsage` objects
    - `cacheReadTokens()` — total cache-read tokens across all models
    - `cacheCreationTokens()` — total cache-creation tokens across all models
- **New fluent methods on `ClaudeAgentOptions`:**
    - `continueConversation()` — fluent setter for `--continue`
    - `settings(string $path)` — fluent setter for `--settings`
    - `addDir(string $path)` — fluent setter for `--add-dir` (supports multiple calls)
    - `user(string $userId)` — fluent setter for `--user`
    - `extraArg(string $flag, ?string $value)` — fluent setter for arbitrary CLI flags
    - `enableFileCheckpointing(bool)` — fluent setter for `--enable-file-checkpointing`
    - `includePartialMessages(bool)` — fluent setter for `--include-partial-messages`
    - `hook(HookEvent, HookMatcher)` — register hooks for any event
    - `preToolUse(string $command, ?string $matcher, ?int $timeout)` — shorthand for pre-tool-use hooks
    - `postToolUse(string $command, ?string $matcher, ?int $timeout)` — shorthand for post-tool-use hooks
    - `maxBudgetUsd(float)` — set max budget per query
    - `maxThinkingTokens(int)` — set max thinking tokens
    - `fallbackModel(string)` — set fallback model
    - `betas(array)` — enable beta features
- **HookMatcher factory methods:**
    - `HookMatcher::command(string $command, ?string $matcher, ?int $timeout)` — create from shell command
    - `HookMatcher::phpScript(string $scriptPath, ?string $matcher, ?int $timeout)` — create from PHP script path
    - `toArray()` and `JsonSerializable` support for CLI serialization
- Hooks are now serialized to `--hooks` CLI argument as JSON
- `ResultMessage::parse()` now accepts camelCase `modelUsage` key alongside `model_usage`
- New config keys: `max_budget_usd`, `max_thinking_tokens`
- `ClaudeAgentManager` merges `maxBudgetUsd` and `maxThinkingTokens` from config defaults

### Changed
- **Breaking: `HookMatcher` constructor** — `$hooks` parameter changed from `callable[]` to `string[]` (shell commands). The CLI executes hook commands as subprocesses, not PHP callables.
- `ProcessTransport::buildCommand()` now automatically includes `--verbose` flag
- `ProcessTransport::run()` now throws `ProcessException` when the CLI exits with a non-zero code and no result messages are found
- `ProcessTransport::run()` now throws `JsonParseException` when JSON-looking output lines fail to parse
- `ProcessTransport::stream()` improved error handling — throws `CliNotFoundException` or `ProcessException` on failure when no messages were yielded
- Extracted `parseLine()` and `looksLikeJson()` private helpers in `ProcessTransport` for cleaner code

### Fixed
- `ProcessTransport` streaming no longer silently swallows all non-zero exit codes — it now distinguishes between normal completion with results and actual failures

## [1.0.0] - 2025-XX-XX

### Added
- Initial release
- ClaudeAgentManager with query, stream, and streamCollect methods
- Fluent ClaudeAgentOptions builder
- Full message parsing (Assistant, System, Result, User, Generic)
- Content block parsing (Text, Thinking, ToolUse, ToolResult)
- QueryResult with helper methods
- Subagent support via AgentDefinition
- MCP server configuration (stdio + SSE)
- Hook system (HookEvent + HookMatcher)
- Session resumption and forking
- Structured output with JSON schema
- Laravel service provider with config publishing
- Facade support
- Full test suite