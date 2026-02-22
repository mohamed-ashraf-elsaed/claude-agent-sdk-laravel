# Changelog

All notable changes to this project will be documented in this file.

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