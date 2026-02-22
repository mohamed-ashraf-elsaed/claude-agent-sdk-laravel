<?php

namespace ClaudeAgentSDK;

use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Messages\SystemMessage;

class QueryResult
{
    /** @var Message[] */
    public readonly array $messages;

    public readonly ?ResultMessage $result;

    public readonly ?string $sessionId;

    /**
     * @param Message[] $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;

        // Extract result
        $results = array_filter($messages, fn($m) => $m instanceof ResultMessage);
        $this->result = ! empty($results) ? end($results) : null;

        // Extract session ID
        $sessionId = null;
        foreach ($messages as $msg) {
            if ($msg instanceof SystemMessage && $msg->sessionId) {
                $sessionId = $msg->sessionId;
                break;
            }
            if ($msg instanceof ResultMessage && $msg->sessionId) {
                $sessionId = $msg->sessionId;
                break;
            }
        }
        $this->sessionId = $sessionId;
    }

    /**
     * Get the final text result.
     */
    public function text(): ?string
    {
        return $this->result?->result;
    }

    /**
     * Get structured output if output format was specified.
     */
    public function structured(): ?array
    {
        return $this->result?->structuredOutput;
    }

    /**
     * Check if the query was successful.
     */
    public function isSuccess(): bool
    {
        return $this->result?->isSuccess() ?? false;
    }

    /**
     * Check if the query had an error.
     */
    public function isError(): bool
    {
        return $this->result?->isError ?? true;
    }

    /**
     * Get total cost in USD.
     */
    public function costUsd(): ?float
    {
        return $this->result?->totalCostUsd;
    }

    /**
     * Get the number of turns used.
     */
    public function turns(): int
    {
        return $this->result?->numTurns ?? 0;
    }

    /**
     * Get duration in milliseconds.
     */
    public function durationMs(): int
    {
        return $this->result?->durationMs ?? 0;
    }

    /**
     * Get all assistant messages.
     *
     * @return AssistantMessage[]
     */
    public function assistantMessages(): array
    {
        return array_values(
            array_filter($this->messages, fn($m) => $m instanceof AssistantMessage)
        );
    }

    /**
     * Get concatenated text from all assistant messages.
     */
    public function fullText(): string
    {
        $parts = [];
        foreach ($this->assistantMessages() as $msg) {
            $text = $msg->text();
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return implode("\n", $parts);
    }

    /**
     * Get all tool uses from assistant messages.
     */
    public function toolUses(): array
    {
        $uses = [];
        foreach ($this->assistantMessages() as $msg) {
            $uses = array_merge($uses, $msg->toolUses());
        }
        return $uses;
    }
}