<?php

namespace ClaudeAgentSDK\Options;

use ClaudeAgentSDK\Agents\AgentDefinition;
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;
use ClaudeAgentSDK\Tools\McpServerConfig;

class ClaudeAgentOptions
{
    /** @var string[] */
    public array $allowedTools = [];

    /** @var string[] */
    public array $disallowedTools = [];

    public string|array|null $systemPrompt = null;

    /** @var array<string, McpServerConfig|array> */
    public array $mcpServers = [];

    public ?string $permissionMode = null;

    public bool $continueConversation = false;

    public ?string $resume = null;

    public bool $forkSession = false;

    public ?int $maxTurns = null;

    public ?string $model = null;

    public ?array $outputFormat = null;

    public ?string $cwd = null;

    public ?string $settings = null;

    /** @var string[] */
    public array $addDirs = [];

    /** @var array<string, string> */
    public array $env = [];

    /** @var array<string, string|null> */
    public array $extraArgs = [];

    /** @var array<string, HookMatcher[]>|null */
    public ?array $hooks = null;

    public ?string $user = null;

    public bool $includePartialMessages = false;

    /** @var array<string, AgentDefinition>|null */
    public ?array $agents = null;

    /** @var string[]|null */
    public ?array $settingSources = null;

    public ?array $sandbox = null;

    /** @var array<array{type: string, path: string}> */
    public array $plugins = [];

    public bool $enableFileCheckpointing = false;

    public ?float $maxBudgetUsd = null;

    public ?int $maxThinkingTokens = null;

    public ?string $fallbackModel = null;

    /** @var string[] */
    public array $betas = [];

    public static function make(): static
    {
        return new static();
    }

    public static function fromArray(array $data): static
    {
        $o = new static();

        foreach ($data as $key => $value) {
            $prop = lcfirst(str_replace('_', '', ucwords($key, '_')));
            if (property_exists($o, $prop)) {
                $o->{$prop} = $value;
            }
        }

        return $o;
    }

    public function tools(array $tools): static
    {
        $this->allowedTools = $tools;
        return $this;
    }

    public function disallow(array $tools): static
    {
        $this->disallowedTools = $tools;
        return $this;
    }

    public function systemPrompt(string|array $prompt): static
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function useClaudeCodePrompt(?string $append = null): static
    {
        $this->systemPrompt = [
            'type' => 'preset',
            'preset' => 'claude_code',
        ];
        if ($append) {
            $this->systemPrompt['append'] = $append;
        }
        return $this;
    }

    public function mcpServer(string $name, McpServerConfig|array $config): static
    {
        $this->mcpServers[$name] = $config;
        return $this;
    }

    public function permission(string $mode): static
    {
        $this->permissionMode = $mode;
        return $this;
    }

    public function resume(string $sessionId, bool $fork = false): static
    {
        $this->resume = $sessionId;
        $this->forkSession = $fork;
        return $this;
    }

    public function continueConversation(): static
    {
        $this->continueConversation = true;
        return $this;
    }

    public function maxTurns(int $turns): static
    {
        $this->maxTurns = $turns;
        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function cwd(string $path): static
    {
        $this->cwd = $path;
        return $this;
    }

    public function settings(string $path): static
    {
        $this->settings = $path;
        return $this;
    }

    public function addDir(string $path): static
    {
        $this->addDirs[] = $path;
        return $this;
    }

    public function outputFormat(array $schema): static
    {
        $this->outputFormat = [
            'type' => 'json_schema',
            'schema' => $schema,
        ];
        return $this;
    }

    public function agent(string $name, AgentDefinition|array $definition): static
    {
        $this->agents ??= [];
        $this->agents[$name] = $definition instanceof AgentDefinition
            ? $definition
            : AgentDefinition::fromArray($definition);
        return $this;
    }

    public function settingSources(array $sources): static
    {
        $this->settingSources = $sources;
        return $this;
    }

    public function sandbox(array $settings): static
    {
        $this->sandbox = $settings;
        return $this;
    }

    public function plugin(string $path): static
    {
        $this->plugins[] = ['type' => 'local', 'path' => $path];
        return $this;
    }

    public function env(string $key, string $value): static
    {
        $this->env[$key] = $value;
        return $this;
    }

    public function user(string $userId): static
    {
        $this->user = $userId;
        return $this;
    }

    public function extraArg(string $flag, ?string $value = null): static
    {
        $this->extraArgs[$flag] = $value;
        return $this;
    }

    public function enableFileCheckpointing(bool $enable = true): static
    {
        $this->enableFileCheckpointing = $enable;
        return $this;
    }

    public function includePartialMessages(bool $include = true): static
    {
        $this->includePartialMessages = $include;
        return $this;
    }

    /**
     * Add a hook for a specific event.
     */
    public function hook(HookEvent $event, HookMatcher $matcher): static
    {
        $this->hooks ??= [];
        $this->hooks[$event->value] ??= [];
        $this->hooks[$event->value][] = $matcher;
        return $this;
    }

    /**
     * Add a pre-tool-use hook.
     */
    public function preToolUse(string $command, ?string $matcher = null, ?int $timeout = null): static
    {
        return $this->hook(
            HookEvent::PreToolUse,
            HookMatcher::command($command, $matcher, $timeout),
        );
    }

    /**
     * Add a post-tool-use hook.
     */
    public function postToolUse(string $command, ?string $matcher = null, ?int $timeout = null): static
    {
        return $this->hook(
            HookEvent::PostToolUse,
            HookMatcher::command($command, $matcher, $timeout),
        );
    }

    public function maxBudgetUsd(float $amount): static
    {
        $this->maxBudgetUsd = $amount;
        return $this;
    }

    public function maxThinkingTokens(int $tokens): static
    {
        $this->maxThinkingTokens = $tokens;
        return $this;
    }

    public function fallbackModel(string $model): static
    {
        $this->fallbackModel = $model;
        return $this;
    }

    public function betas(array $betas): static
    {
        $this->betas = $betas;
        return $this;
    }

    /**
     * Build CLI arguments from these options.
     */
    public function toCliArgs(): array
    {
        $args = ['--output-format', 'stream-json'];

        if ($this->model) {
            $args[] = '--model';
            $args[] = $this->model;
        }

        if ($this->permissionMode) {
            $args[] = '--permission-mode';
            $args[] = $this->permissionMode;
        }

        if ($this->maxTurns) {
            $args[] = '--max-turns';
            $args[] = (string) $this->maxTurns;
        }

        if ($this->resume) {
            $args[] = '--resume';
            $args[] = $this->resume;
        }

        if ($this->forkSession) {
            $args[] = '--fork-session';
        }

        if ($this->continueConversation) {
            $args[] = '--continue';
        }

        if ($this->systemPrompt) {
            if (is_string($this->systemPrompt)) {
                $args[] = '--system-prompt';
                $args[] = $this->systemPrompt;
            } elseif (is_array($this->systemPrompt)) {
                $args[] = '--system-prompt';
                $args[] = json_encode($this->systemPrompt);
            }
        }

        if (! empty($this->allowedTools)) {
            $args[] = '--allowed-tools';
            $args[] = implode(',', $this->allowedTools);
        }

        if (! empty($this->disallowedTools)) {
            $args[] = '--disallowed-tools';
            $args[] = implode(',', $this->disallowedTools);
        }

        if (! empty($this->addDirs)) {
            foreach ($this->addDirs as $dir) {
                $args[] = '--add-dir';
                $args[] = $dir;
            }
        }

        if ($this->outputFormat) {
            $args[] = '--output-format-json-schema';
            $args[] = json_encode($this->outputFormat['schema']);
        }

        if ($this->settings) {
            $args[] = '--settings';
            $args[] = $this->settings;
        }

        if (! empty($this->mcpServers)) {
            $args[] = '--mcp-servers';
            $args[] = json_encode($this->mcpServers);
        }

        if ($this->settingSources) {
            foreach ($this->settingSources as $source) {
                $args[] = '--setting-source';
                $args[] = $source;
            }
        }

        if ($this->agents) {
            $agentsPayload = [];
            foreach ($this->agents as $name => $agent) {
                $agentsPayload[$name] = $agent instanceof AgentDefinition
                    ? $agent->toArray()
                    : $agent;
            }
            $args[] = '--agents';
            $args[] = json_encode($agentsPayload);
        }

        if ($this->hooks) {
            $hooksPayload = [];
            foreach ($this->hooks as $event => $matchers) {
                $hooksPayload[$event] = array_map(
                    fn(HookMatcher $m) => $m->toArray(),
                    $matchers,
                );
            }
            $args[] = '--hooks';
            $args[] = json_encode($hooksPayload);
        }

        if (! empty($this->plugins)) {
            $args[] = '--plugins';
            $args[] = json_encode($this->plugins);
        }

        if ($this->sandbox) {
            $args[] = '--sandbox';
            $args[] = json_encode($this->sandbox);
        }

        if ($this->enableFileCheckpointing) {
            $args[] = '--enable-file-checkpointing';
        }

        if ($this->includePartialMessages) {
            $args[] = '--include-partial-messages';
        }

        if ($this->user) {
            $args[] = '--user';
            $args[] = $this->user;
        }

        if ($this->maxBudgetUsd !== null) {
            $args[] = '--max-budget-usd';
            $args[] = (string) $this->maxBudgetUsd;
        }

        if ($this->maxThinkingTokens !== null) {
            $args[] = '--max-thinking-tokens';
            $args[] = (string) $this->maxThinkingTokens;
        }

        if ($this->fallbackModel) {
            $args[] = '--fallback-model';
            $args[] = $this->fallbackModel;
        }

        if (! empty($this->betas)) {
            foreach ($this->betas as $beta) {
                $args[] = '--beta';
                $args[] = $beta;
            }
        }

        foreach ($this->extraArgs as $key => $value) {
            $args[] = "--{$key}";
            if ($value !== null) {
                $args[] = $value;
            }
        }

        return $args;
    }

    /**
     * Build environment variables for the CLI process.
     */
    public function toEnv(array $defaults = []): array
    {
        return array_merge($defaults, $this->env);
    }
}