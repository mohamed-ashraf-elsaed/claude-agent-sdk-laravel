<?php


namespace ClaudeAgentSDK\Exceptions;

class JsonParseException extends ClaudeAgentException
{
    public function __construct(
        public readonly string $line,
        public readonly ?\Throwable $originalError = null,
    ) {
        parent::__construct("Failed to parse JSON line: {$line}", previous: $originalError);
    }
}