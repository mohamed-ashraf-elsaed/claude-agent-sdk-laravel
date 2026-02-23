<?php

namespace ClaudeAgentSDK\Messages;

use ClaudeAgentSDK\Data\ModelUsage;

class ResultMessage extends Message
{
    public function __construct(
        public readonly string  $subtype,
        public readonly ?string $result = null,
        public readonly ?string $sessionId = null,
        public readonly int     $durationMs = 0,
        public readonly int     $durationApiMs = 0,
        public readonly bool    $isError = false,
        public readonly int     $numTurns = 0,
        public readonly ?float  $totalCostUsd = null,
        public readonly ?array  $usage = null,
        public readonly ?array  $modelUsage = null,
        public readonly ?array  $structuredOutput = null,
        array                   $raw = [],
    ) {
        parent::__construct('result', $raw);
    }

    public static function parse(array $data): static
    {
        return new static(
            subtype: $data['subtype'] ?? '',
            result: $data['result'] ?? null,
            sessionId: $data['session_id'] ?? null,
            durationMs: $data['duration_ms'] ?? 0,
            durationApiMs: $data['duration_api_ms'] ?? 0,
            isError: $data['is_error'] ?? false,
            numTurns: $data['num_turns'] ?? 0,
            totalCostUsd: $data['total_cost_usd'] ?? null,
            usage: $data['usage'] ?? null,
            modelUsage: $data['model_usage'] ?? $data['modelUsage'] ?? null,
            structuredOutput: $data['structured_output'] ?? null,
            raw: $data,
        );
    }

    public function isSuccess(): bool
    {
        return $this->subtype === 'success';
    }

    /**
     * Get per-model usage as typed objects.
     *
     * @return array<string, ModelUsage>
     */
    public function parsedModelUsage(): array
    {
        if (! $this->modelUsage) {
            return [];
        }

        return array_map(
            fn(array $usage) => ModelUsage::fromArray($usage),
            $this->modelUsage,
        );
    }

    /**
     * Total cache-read tokens across all models.
     */
    public function cacheReadTokens(): int
    {
        return (int) array_sum(array_map(
            fn(ModelUsage $u) => $u->cacheReadInputTokens,
            $this->parsedModelUsage(),
        ));
    }

    /**
     * Total cache-creation tokens across all models.
     */
    public function cacheCreationTokens(): int
    {
        return (int) array_sum(array_map(
            fn(ModelUsage $u) => $u->cacheCreationInputTokens,
            $this->parsedModelUsage(),
        ));
    }
}