<?php

namespace ClaudeAgentSDK\Data;

class ModelUsage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadInputTokens = 0,
        public readonly int $cacheCreationInputTokens = 0,
        public readonly int $webSearchRequests = 0,
        public readonly float $costUsd = 0.0,
        public readonly int $contextWindow = 0,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            inputTokens: $data['inputTokens'] ?? $data['input_tokens'] ?? 0,
            outputTokens: $data['outputTokens'] ?? $data['output_tokens'] ?? 0,
            cacheReadInputTokens: $data['cacheReadInputTokens'] ?? $data['cache_read_input_tokens'] ?? 0,
            cacheCreationInputTokens: $data['cacheCreationInputTokens'] ?? $data['cache_creation_input_tokens'] ?? 0,
            webSearchRequests: $data['webSearchRequests'] ?? $data['web_search_requests'] ?? 0,
            costUsd: (float) ($data['costUSD'] ?? $data['cost_usd'] ?? 0.0),
            contextWindow: $data['contextWindow'] ?? $data['context_window'] ?? 0,
        );
    }

    /**
     * Total input tokens including cached reads and writes.
     */
    public function totalInputTokens(): int
    {
        return $this->inputTokens + $this->cacheReadInputTokens + $this->cacheCreationInputTokens;
    }

    /**
     * Ratio of cache-read tokens to total input tokens (0.0–1.0).
     */
    public function cacheHitRate(): float
    {
        $total = $this->totalInputTokens();

        return $total > 0 ? $this->cacheReadInputTokens / $total : 0.0;
    }
}