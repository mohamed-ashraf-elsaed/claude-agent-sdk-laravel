<?php


namespace ClaudeAgentSDK\Messages;

class GenericMessage extends Message
{
    public function __construct(string $type, array $raw = [])
    {
        parent::__construct($type, $raw);
    }
}