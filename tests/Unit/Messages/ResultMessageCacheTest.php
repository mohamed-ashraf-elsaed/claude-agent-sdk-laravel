<?php

namespace ClaudeAgentSDK\Tests\Unit\Messages;

use ClaudeAgentSDK\Data\ModelUsage;
use ClaudeAgentSDK\Messages\ResultMessage;
use PHPUnit\Framework\TestCase;

class ResultMessageCacheTest extends TestCase
{
    public function test_parsed_model_usage(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'model_usage' => [
                'claude-sonnet-4-5-20250929' => [
                    'inputTokens' => 100,
                    'outputTokens' => 50,
                    'cacheReadInputTokens' => 5000,
                    'cacheCreationInputTokens' => 0,
                    'costUSD' => 0.003,
                    'contextWindow' => 200000,
                ],
            ],
        ]);

        $parsed = $msg->parsedModelUsage();

        $this->assertCount(1, $parsed);
        $this->assertArrayHasKey('claude-sonnet-4-5-20250929', $parsed);
        $this->assertInstanceOf(ModelUsage::class, $parsed['claude-sonnet-4-5-20250929']);
        $this->assertSame(5000, $parsed['claude-sonnet-4-5-20250929']->cacheReadInputTokens);
        $this->assertSame(0.003, $parsed['claude-sonnet-4-5-20250929']->costUsd);
    }

    public function test_parsed_model_usage_empty(): void
    {
        $msg = ResultMessage::parse(['type' => 'result']);

        $this->assertSame([], $msg->parsedModelUsage());
    }

    public function test_cache_read_tokens(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'model_usage' => [
                'claude-sonnet-4-5-20250929' => [
                    'cacheReadInputTokens' => 3000,
                    'cacheCreationInputTokens' => 100,
                ],
                'claude-haiku-4-5' => [
                    'cacheReadInputTokens' => 2000,
                    'cacheCreationInputTokens' => 50,
                ],
            ],
        ]);

        $this->assertSame(5000, $msg->cacheReadTokens());
        $this->assertSame(150, $msg->cacheCreationTokens());
    }

    public function test_cache_tokens_without_model_usage(): void
    {
        $msg = ResultMessage::parse(['type' => 'result']);

        $this->assertSame(0, $msg->cacheReadTokens());
        $this->assertSame(0, $msg->cacheCreationTokens());
    }

    public function test_multiple_models_usage(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'model_usage' => [
                'claude-sonnet-4-5-20250929' => [
                    'inputTokens' => 100,
                    'outputTokens' => 50,
                    'costUSD' => 0.003,
                ],
                'claude-haiku-4-5' => [
                    'inputTokens' => 80,
                    'outputTokens' => 30,
                    'costUSD' => 0.001,
                ],
            ],
        ]);

        $parsed = $msg->parsedModelUsage();
        $this->assertCount(2, $parsed);

        $totalCost = array_sum(array_map(fn(ModelUsage $u) => $u->costUsd, $parsed));
        $this->assertSame(0.004, $totalCost);
    }

    public function test_parse_accepts_camel_case_model_usage_key(): void
    {
        $msg = ResultMessage::parse([
            'type' => 'result',
            'subtype' => 'success',
            'modelUsage' => [
                'claude-sonnet-4-5-20250929' => [
                    'inputTokens' => 100,
                    'cacheReadInputTokens' => 500,
                ],
            ],
        ]);

        $parsed = $msg->parsedModelUsage();
        $this->assertCount(1, $parsed);
        $this->assertSame(500, $parsed['claude-sonnet-4-5-20250929']->cacheReadInputTokens);
    }
}
