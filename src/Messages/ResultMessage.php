<?php

namespace ClaudeAgentSDK\Messages;

use ClaudeAgentSDK\Data\ModelUsage;

class ResultMessage extends Message
{
    /** Result subtypes */
    public const SUBTYPE_SUCCESS = 'success';
    public const SUBTYPE_ERROR_MAX_TURNS = 'error_max_turns';
    public const SUBTYPE_ERROR_DURING_EXECUTION = 'error_during_execution';
    public const SUBTYPE_ERROR_MAX_BUDGET = 'error_max_budget_usd';
    public const SUBTYPE_ERROR_MAX_STRUCTURED_OUTPUT_RETRIES = 'error_max_structured_output_retries';

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
        /** @var array[] Permission denials that occurred during the query */
        public readonly array   $permissionDenials = [],
        /** @var array[] Errors encountered during execution */
        public readonly array   $errors = [],
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
            permissionDenials: $data['permission_denials'] ?? [],
            errors: $data['errors'] ?? [],
            raw: $data,
        );
    }

    public function isSuccess(): bool
    {
        return $this->subtype === self::SUBTYPE_SUCCESS;
    }

    /**
     * Check if the agent stopped because it reached the max turns limit.
     */
    public function isMaxTurnsError(): bool
    {
        return $this->subtype === self::SUBTYPE_ERROR_MAX_TURNS;
    }

    /**
     * Check if the agent stopped due to a budget limit.
     */
    public function isBudgetError(): bool
    {
        return $this->subtype === self::SUBTYPE_ERROR_MAX_BUDGET;
    }

    /**
     * Check if the agent stopped due to an execution error.
     */
    public function isExecutionError(): bool
    {
        return $this->subtype === self::SUBTYPE_ERROR_DURING_EXECUTION;
    }

    /**
     * Check if the agent stopped because structured output validation failed.
     */
    public function isStructuredOutputError(): bool
    {
        return $this->subtype === self::SUBTYPE_ERROR_MAX_STRUCTURED_OUTPUT_RETRIES;
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
