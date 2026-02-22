<?php

namespace ClaudeAgentSDK\Exceptions;

use Throwable;

class JsonParseException extends ClaudeAgentException
{
    public readonly string $rawLine;
    public readonly ?Throwable $originalError;

    public function __construct(string $rawLine, ?Throwable $originalError = null)
    {
        $this->rawLine = $rawLine;
        $this->originalError = $originalError;

        parent::__construct(
            "Failed to parse JSON line: {$rawLine}",
            0,
            $originalError,
        );
    }
}