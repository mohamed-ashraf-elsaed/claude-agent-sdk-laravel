<?php


namespace ClaudeAgentSDK\Exceptions;

class ProcessException extends ClaudeAgentException
{
    public function __construct(
        string $message,
        public readonly ?int $exitCode = null,
        public readonly ?string $stderr = null,
    ) {
        parent::__construct($message);
    }
}