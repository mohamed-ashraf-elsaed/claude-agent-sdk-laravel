<?php

namespace ClaudeAgentSDK\Tests\Unit\Agents;

use ClaudeAgentSDK\Agents\AgentDefinition;
use PHPUnit\Framework\TestCase;

class AgentDefinitionTest extends TestCase
{
    public function test_constructor(): void
    {
        $def = new AgentDefinition(
            description: 'Security reviewer',
            prompt: 'Find vulnerabilities',
            tools: ['Read', 'Grep'],
            model: 'sonnet',
        );

        $this->assertSame('Security reviewer', $def->description);
        $this->assertSame('Find vulnerabilities', $def->prompt);
        $this->assertSame(['Read', 'Grep'], $def->tools);
        $this->assertSame('sonnet', $def->model);
    }

    public function test_from_array(): void
    {
        $def = AgentDefinition::fromArray([
            'description' => 'Test writer',
            'prompt' => 'Write tests',
            'tools' => ['Read', 'Write'],
            'model' => 'opus',
        ]);

        $this->assertSame('Test writer', $def->description);
        $this->assertSame(['Read', 'Write'], $def->tools);
        $this->assertSame('opus', $def->model);
    }

    public function test_from_array_minimal(): void
    {
        $def = AgentDefinition::fromArray([
            'description' => 'Helper',
            'prompt' => 'Help out',
        ]);

        $this->assertNull($def->tools);
        $this->assertNull($def->model);
    }

    public function test_to_array(): void
    {
        $def = new AgentDefinition('Desc', 'Prompt', ['Read'], 'sonnet');
        $arr = $def->toArray();

        $this->assertSame([
            'description' => 'Desc',
            'prompt' => 'Prompt',
            'tools' => ['Read'],
            'model' => 'sonnet',
        ], $arr);
    }

    public function test_to_array_filters_null(): void
    {
        $def = new AgentDefinition('Desc', 'Prompt');
        $arr = $def->toArray();

        $this->assertArrayNotHasKey('tools', $arr);
        $this->assertArrayNotHasKey('model', $arr);
    }
}