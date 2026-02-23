<?php

namespace ClaudeAgentSDK\Transport;

use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use Generator;
use JsonException;
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
     *
     * @throws CliNotFoundException
     * @throws ProcessException
     * @throws JsonParseException
     */
    public function run(string $prompt, ClaudeAgentOptions $options): array
    {
        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $process->run();

        $output = $process->getOutput();
        $messages = $this->parseOutput($output);
        $exitCode = $process->getExitCode();

        if ($exitCode !== 0 && $exitCode !== null) {
            $stderr = trim($process->getErrorOutput());

            if (str_contains($stderr, 'not found') || str_contains($stderr, 'command not found')) {
                throw new CliNotFoundException($this->cliPath);
            }

            $hasResult = ! empty(array_filter($messages, fn($m) => $m instanceof ResultMessage));

            if (! $hasResult) {
                throw new ProcessException(
                    "Claude CLI process failed with exit code {$exitCode}",
                    $exitCode,
                    $stderr ?: null,
                );
            }
        }

        // If we got no messages and there was output, it likely failed to parse
        if (empty($messages) && trim($output) !== '') {
            $firstLine = strtok(trim($output), "\n");
            if ($this->looksLikeJson($firstLine)) {
                throw new JsonParseException($firstLine);
            }
        }

        return $messages;
    }

    /**
     * Run a query and yield messages as they arrive (streaming).
     *
     * @return Generator<Message>
     *
     * @throws CliNotFoundException
     * @throws ProcessException
     */
    public function stream(string $prompt, ClaudeAgentOptions $options): Generator
    {
        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $this->process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $this->process->start();

        $buffer = '';
        $hasMessages = false;

        foreach ($this->process as $type => $data) {
            if ($type === Process::OUT) {
                $buffer .= $data;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $message = $this->parseLine($line);
                    if ($message) {
                        $hasMessages = true;
                        yield $message;
                    }
                }
            }
        }

        // Process any remaining buffer
        $message = $this->parseLine($buffer);
        if ($message) {
            $hasMessages = true;
            yield $message;
        }

        $exitCode = $this->process->getExitCode();
        $stderr = trim($this->process->getErrorOutput());
        $this->process = null;

        if ($exitCode !== 0 && $exitCode !== null && ! $hasMessages) {
            if (str_contains($stderr, 'not found') || str_contains($stderr, 'command not found')) {
                throw new CliNotFoundException($this->cliPath);
            }

            throw new ProcessException(
                "Claude CLI process failed with exit code {$exitCode}",
                $exitCode,
                $stderr ?: null,
            );
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
        $args = array_merge($args, $options->toCliArgs());
        $args[] = '--verbose';
        $args[] = '--print';
        $args[] = $prompt;

        return $args;
    }

    /**
     * Parse a single line into a Message, or return null if not valid JSON message.
     */
    private function parseLine(string $line): ?Message
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        try {
            $parsed = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            return Message::fromJson($parsed);
        } catch (JsonException) {
            // Non-JSON lines (CLI startup text, progress) are expected — skip silently.
            // Only JSON-looking lines that fail to parse are noteworthy.
            return null;
        }
    }

    /**
     * @return Message[]
     *
     * @throws JsonParseException When a JSON-looking line fails to parse
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
            } catch (JsonException $e) {
                // If it looks like JSON but failed, throw — this is a real parse error.
                // Plain text (CLI startup, warnings) is skipped silently.
                if ($this->looksLikeJson($line)) {
                    throw new JsonParseException($line, $e);
                }
            }
        }

        return $messages;
    }

    /**
     * Check if a line appears to be JSON (starts with { or [).
     */
    private function looksLikeJson(string $line): bool
    {
        return str_starts_with($line, '{') || str_starts_with($line, '[');
    }

    private function findCli(): string
    {
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

        $cmd = PHP_OS_FAMILY === 'Windows' ? 'where claude 2>NUL' : 'which claude 2>/dev/null';
        $result = trim((string) shell_exec($cmd));

        if ($result !== '' && file_exists($result)) {
            return $result;
        }

        return 'claude';
    }
}