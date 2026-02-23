<?php

namespace ClaudeAgentSDK\Hooks;

enum HookEvent: string
{
    case PreToolUse = 'PreToolUse';
    case PostToolUse = 'PostToolUse';
    case UserPromptSubmit = 'UserPromptSubmit';
    case Stop = 'Stop';
    case SubagentStop = 'SubagentStop';
    case PreCompact = 'PreCompact';
}