<?php

namespace ClaudeAgentSDK\Tests\Unit\Data;

use ClaudeAgentSDK\Data\ModelUsage;
use PHPUnit\Framework\TestCase;

class ModelUsageTest extends TestCase
{
    public function test_from_array_camel_case(): void
    {
        $usage = ModelUsage::fromArray([
            'inputTokens' => 100,
            'outputTokens' => 50,
            'cacheReadInputTokens' => 800,
            'cacheCreationInputTokens' => 200,
            'webSearchRequests' => 2,
            'costUSD' => 0.0045,
            'contextWindow' => 200000,
        ]);

        $this->assertSame(100, $usage->inputTokens);
        $this->assertSame(50, $usage->outputTokens);
        $this->assertSame(800, $usage->cacheReadInputTokens);
        $this->assertSame(200, $usage->cacheCreationInputTokens);
        $this->assertSame(2, $usage->webSearchRequests);
        $this->assertSame(0.0045, $usage->costUsd);
        $this->assertSame(200000, $usage->contextWindow);
    }

    public function test_from_array_snake_case(): void
    {
        $usage = ModelUsage::fromArray([
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cache_read_input_tokens' => 800,
            'cache_creation_input_tokens' => 200,
            'web_search_requests' => 1,
            'cost_usd' => 0.003,
            'context_window' => 128000,
        ]);

        $this->assertSame(100, $usage->inputTokens);
        $this->assertSame(800, $usage->cacheReadInputTokens);
        $this->assertSame(200, $usage->cacheCreationInputTokens);
        $this->assertSame(0.003, $usage->costUsd);
    }

    public function test_defaults(): void
    {
        $usage = ModelUsage::fromArray([]);

        $this->assertSame(0, $usage->inputTokens);
        $this->assertSame(0, $usage->outputTokens);
        $this->assertSame(0, $usage->cacheReadInputTokens);
        $this->assertSame(0, $usage->cacheCreationInputTokens);
        $this->assertSame(0, $usage->webSearchRequests);
        $this->assertSame(0.0, $usage->costUsd);
        $this->assertSame(0, $usage->contextWindow);
    }

    public function test_total_input_tokens(): void
    {
        $usage = ModelUsage::fromArray([
            'inputTokens' => 100,
            'cacheReadInputTokens' => 5000,
            'cacheCreationInputTokens' => 200,
        ]);

        $this->assertSame(5300, $usage->totalInputTokens());
    }

    public function test_cache_hit_rate(): void
    {
        $usage = ModelUsage::fromArray([
            'inputTokens' => 50,
            'cacheReadInputTokens' => 950,
            'cacheCreationInputTokens' => 0,
        ]);

        $this->assertSame(0.95, $usage->cacheHitRate());
    }

    public function test_cache_hit_rate_zero_tokens(): void
    {
        $usage = ModelUsage::fromArray([]);

        $this->assertSame(0.0, $usage->cacheHitRate());
    }

    public function test_cache_hit_rate_no_cache(): void
    {
        $usage = ModelUsage::fromArray([
            'inputTokens' => 1000,
            'cacheReadInputTokens' => 0,
            'cacheCreationInputTokens' => 0,
        ]);

        $this->assertSame(0.0, $usage->cacheHitRate());
    }

    public function test_camel_case_takes_precedence_over_snake_case(): void
    {
        $usage = ModelUsage::fromArray([
            'inputTokens' => 999,
            'input_tokens' => 1,
        ]);

        $this->assertSame(999, $usage->inputTokens);
    }
}
