<?php

namespace ClaudeAgentSDK\Transport;

use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use Symfony\Component\Process\Process;

class ProcessTransport
{
    private ?Process $process = null;
    private string $cliPath;
    private array $defaultEnv;
    private ?float $timeout;

    public function __construct(array $config = [])
    {
        $this->cliPath = $config['cli_path'] ?? $this->findCli();
        $this->timeout = $config['process_timeout'] ?? null;

        $this->defaultEnv = array_filter([
            'ANTHROPIC_API_KEY' => $config['api_key'] ?? null,
        ]);

        if (! empty($config['providers']['bedrock'])) {
            $this->defaultEnv['CLAUDE_CODE_USE_BEDROCK'] = '1';
        }
        if (! empty($config['providers']['vertex'])) {
            $this->defaultEnv['CLAUDE_CODE_USE_VERTEX'] = '1';
        }
        if (! empty($config['providers']['foundry'])) {
            $this->defaultEnv['CLAUDE_CODE_USE_FOUNDRY'] = '1';
        }
    }

    /**
     * Run a query and return all messages at once.
     *
     * @return Message[]
     */
    public function run(string $prompt, ClaudeAgentOptions $options): array
    {
        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $process->run();

        if (! $process->isSuccessful() && $process->getExitCode() !== 0) {
            // Some exit codes are normal (e.g., max turns reached)
            $stderr = $process->getErrorOutput();
            if (str_contains($stderr, 'not found') || str_contains($stderr, 'command not found')) {
                throw new CliNotFoundException($this->cliPath);
            }
        }

        return $this->parseOutput($process->getOutput());
    }

    /**
     * Run a query and yield messages as they arrive (streaming).
     *
     * @return \Generator<Message>
     */
    public function stream(string $prompt, ClaudeAgentOptions $options): \Generator
    {
        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $this->process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $this->process->start();

        $buffer = '';

        foreach ($this->process as $type => $data) {
            if ($type === Process::OUT) {
                $buffer .= $data;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }

                    try {
                        $parsed = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        yield Message::fromJson($parsed);
                    } catch (\JsonException $e) {
                        // Skip malformed lines, log if needed
                    }
                }
            }
        }

        // Process any remaining buffer
        $line = trim($buffer);
        if ($line !== '') {
            try {
                $parsed = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                yield Message::fromJson($parsed);
            } catch (\JsonException $e) {
                // Skip
            }
        }

        $exitCode = $this->process->getExitCode();
        $this->process = null;

        if ($exitCode !== 0 && $exitCode !== null) {
            // Non-zero exit might be normal (e.g., user interrupt)
            // Only throw for actual errors
        }
    }

    /**
     * Stop the running process.
     */
    public function stop(): void
    {
        if ($this->process && $this->process->isRunning()) {
            $this->process->signal(SIGINT);
        }
    }

    private function buildCommand(string $prompt, ClaudeAgentOptions $options): array
    {
        $args = [$this->cliPath];

        // Add options args
        $args = array_merge($args, $options->toCliArgs());

        // Add the prompt
        $args[] = '--verbose';
        $args[] = '--print';
        $args[] = $prompt;

        return $args;
    }

    /**
     * @return Message[]
     */
    private function parseOutput(string $output): array
    {
        $messages = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            try {
                $parsed = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $messages[] = Message::fromJson($parsed);
            } catch (\JsonException $e) {
                // Skip non-JSON lines (e.g., startup text)
            }
        }

        return $messages;
    }

    private function findCli(): string
    {
        // Check common locations
        $paths = [
            '/usr/local/bin/claude',
            '/usr/bin/claude',
            getenv('HOME') . '/.npm-global/bin/claude',
            getenv('HOME') . '/.local/bin/claude',
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Try which/where
        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where claude 2>NUL' : 'which claude 2>/dev/null';
        $result = trim((string) shell_exec($cmd));

        if ($result !== '' && file_exists($result)) {
            return $result;
        }

        // Default — will fail at runtime if not found
        return 'claude';
    }
}