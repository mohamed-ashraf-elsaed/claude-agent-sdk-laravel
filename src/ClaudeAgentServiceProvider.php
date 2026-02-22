<?php

namespace ClaudeAgentSDK;

use Illuminate\Support\ServiceProvider;

class ClaudeAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/claude-agent.php', 'claude-agent');

        $this->app->singleton(ClaudeAgentManager::class, function ($app) {
            return new ClaudeAgentManager($app['config']['claude-agent']);
        });

        $this->app->alias(ClaudeAgentManager::class, 'claude-agent');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/claude-agent.php' => config_path('claude-agent.php'),
            ], 'claude-agent-config');
        }
    }
}