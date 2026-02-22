<?php

namespace ClaudeAgentSDK;

use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Transport\ProcessTransport;

class ClaudeAgentManager
{
    private ProcessTransport $transport;
    private array $config;
    private ?ClaudeAgentOptions $defaultOptions = null;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->transport = new ProcessTransport($config);
    }

    /**
     * Set default options for all subsequent queries.
     */
    public function withOptions(ClaudeAgentOptions|array $options): static
    {
        $clone = clone $this;
        $clone->defaultOptions = $options instanceof ClaudeAgentOptions
            ? $options
            : ClaudeAgentOptions::fromArray($options);

        return $clone;
    }

    /**
     * Run a query and return the complete result.
     */
    public function query(string $prompt, ClaudeAgentOptions|array|null $options = null): QueryResult
    {
        $opts = $this->resolveOptions($options);
        $messages = $this->transport->run($prompt, $opts);

        return new QueryResult($messages);
    }

    /**
     * Run a query and yield messages as they stream in.
     *
     * @return \Generator<Message>
     */
    public function stream(string $prompt, ClaudeAgentOptions|array|null $options = null): \Generator
    {
        $opts = $this->resolveOptions($options);
        yield from $this->transport->stream($prompt, $opts);
    }

    /**
     * Run a query and collect streamed messages into a QueryResult.
     * Allows you to process messages during streaming via a callback.
     */
    public function streamCollect(
        string $prompt,
        ?callable $onMessage = null,
        ClaudeAgentOptions|array|null $options = null,
    ): QueryResult {
        $messages = [];

        foreach ($this->stream($prompt, $options) as $message) {
            $messages[] = $message;

            if ($onMessage) {
                $onMessage($message);
            }
        }

        return new QueryResult($messages);
    }

    /**
     * Stop any running process.
     */
    public function stop(): void
    {
        $this->transport->stop();
    }

    /**
     * Create a new options builder pre-filled with config defaults.
     */
    public function options(): ClaudeAgentOptions
    {
        $o = ClaudeAgentOptions::make();

        if ($this->config['model'] ?? null) {
            $o->model($this->config['model']);
        }
        if ($this->config['permission_mode'] ?? null) {
            $o->permission($this->config['permission_mode']);
        }
        if ($this->config['cwd'] ?? null) {
            $o->cwd($this->config['cwd']);
        }
        if (! empty($this->config['allowed_tools'])) {
            $o->tools($this->config['allowed_tools']);
        }
        if ($this->config['max_turns'] ?? null) {
            $o->maxTurns($this->config['max_turns']);
        }

        return $o;
    }

    private function resolveOptions(ClaudeAgentOptions|array|null $options): ClaudeAgentOptions
    {
        if ($options instanceof ClaudeAgentOptions) {
            return $this->mergeWithDefaults($options);
        }

        if (is_array($options)) {
            return $this->mergeWithDefaults(ClaudeAgentOptions::fromArray($options));
        }

        if ($this->defaultOptions) {
            return $this->mergeWithDefaults($this->defaultOptions);
        }

        return $this->buildConfigOptions();
    }

    private function mergeWithDefaults(ClaudeAgentOptions $opts): ClaudeAgentOptions
    {
        // Apply config defaults to empty fields
        if (! $opts->model && ($this->config['model'] ?? null)) {
            $opts->model = $this->config['model'];
        }
        if (! $opts->permissionMode && ($this->config['permission_mode'] ?? null)) {
            $opts->permissionMode = $this->config['permission_mode'];
        }
        if (! $opts->cwd) {
            $opts->cwd = $this->config['cwd'] ?? base_path();
        }
        if (empty($opts->allowedTools) && ! empty($this->config['allowed_tools'])) {
            $opts->allowedTools = $this->config['allowed_tools'];
        }
        if (! $opts->maxTurns && ($this->config['max_turns'] ?? null)) {
            $opts->maxTurns = $this->config['max_turns'];
        }

        return $opts;
    }

    private function buildConfigOptions(): ClaudeAgentOptions
    {
        return $this->options();
    }
}