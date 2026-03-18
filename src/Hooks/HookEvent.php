<?php

namespace ClaudeAgentSDK\Hooks;

enum HookEvent: string
{
    case PreToolUse = 'PreToolUse';
    case PostToolUse = 'PostToolUse';
    case PostToolUseFailure = 'PostToolUseFailure';
    case UserPromptSubmit = 'UserPromptSubmit';
    case Notification = 'Notification';
    case SessionStart = 'SessionStart';
    case SessionEnd = 'SessionEnd';
    case Stop = 'Stop';
    case SubagentStart = 'SubagentStart';
    case SubagentStop = 'SubagentStop';
    case PreCompact = 'PreCompact';
    case PermissionRequest = 'PermissionRequest';
}