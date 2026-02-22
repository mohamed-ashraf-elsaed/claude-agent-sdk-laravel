<?php


namespace ClaudeAgentSDK\Agents;

class AgentDefinition
{
    public function __construct(
        public readonly string $description,
        public readonly string $prompt,
        public readonly ?array $tools = null,
        public readonly ?string $model = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            description: $data['description'],
            prompt: $data['prompt'],
            tools: $data['tools'] ?? null,
            model: $data['model'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'prompt' => $this->prompt,
            'tools' => $this->tools,
            'model' => $this->model,
        ], fn($v) => $v !== null);
    }
}
