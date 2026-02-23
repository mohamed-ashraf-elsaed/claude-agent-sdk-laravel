<?php

namespace ClaudeAgentSDK\Tests\Unit\Transport;

use ClaudeAgentSDK\Transport\ProcessTransport;
use PHPUnit\Framework\TestCase;

class ProcessTransportTest extends TestCase
{
    public function test_constructor_with_config(): void
    {
        $transport = new ProcessTransport([
            'cli_path' => '/usr/local/bin/claude',
            'api_key' => 'sk-test',
            'process_timeout' => 120,
        ]);

        $this->assertInstanceOf(ProcessTransport::class, $transport);
    }

    public function test_constructor_with_providers(): void
    {
        $transport = new ProcessTransport([
            'providers' => [
                'bedrock' => true,
                'vertex' => false,
                'foundry' => true,
            ],
        ]);

        $this->assertInstanceOf(ProcessTransport::class, $transport);
    }

    public function test_stop_without_running_process(): void
    {
        $transport = new ProcessTransport([]);

        // Should not throw
        $transport->stop();

        $this->assertTrue(true);
    }

    public function test_constructor_with_empty_config(): void
    {
        $transport = new ProcessTransport([]);

        $this->assertInstanceOf(ProcessTransport::class, $transport);
    }
}