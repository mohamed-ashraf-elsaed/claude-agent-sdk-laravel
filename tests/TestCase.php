<?php

namespace ClaudeAgentSDK\Tests;

use ClaudeAgentSDK\ClaudeAgentServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ClaudeAgentSDK\Facades\ClaudeAgent;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ClaudeAgentServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ClaudeAgent' => ClaudeAgent::class,
        ];
    }
}