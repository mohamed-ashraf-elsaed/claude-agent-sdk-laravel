<?php


namespace ClaudeAgentSDK\Exceptions;

class CliNotFoundException extends ClaudeAgentException
{
    public function __construct(string $cliPath = 'claude')
    {
        parent::__construct(
            "Claude Code CLI not found at '{$cliPath}'. Install it with: npm install -g @anthropic-ai/claude-code"
        );
    }
}