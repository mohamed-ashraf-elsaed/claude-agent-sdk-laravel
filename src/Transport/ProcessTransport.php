<?php

namespace ClaudeAgentSDK\Transport;

use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\JsonParseException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Hooks\HookEvent;
use ClaudeAgentSDK\Hooks\HookMatcher;
use ClaudeAgentSDK\Messages\Message;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Permissions\PermissionResultAllow;
use Generator;
use JsonException;
use Symfony\Component\Process\Process;

class ProcessTransport
{
    private ?Process $process = null;
    private string $cliPath;
    private array $defaultEnv;
    private ?float $timeout;

    /** @var string|null IPC directory for canUseTool communication */
    private ?string $ipcDir = null;

    public function __construct(array $config = [])
    {
        $this->cliPath = $config['cli_path'] ?? $this->findCli();
        $this->timeout = $config['process_timeout'] ?? null;

        $this->defaultEnv = array_filter([
            'ANTHROPIC_API_KEY' => $config['api_key'] ?? null,
            'ANTHROPIC_BASE_URL' => $config['api_base_url'] ?? null,
            'ANTHROPIC_AUTH_TOKEN' => $config['auth_token'] ?? null,
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
        $hasCanUseTool = $options->canUseTool !== null;

        // When canUseTool is set, use interactive mode to avoid deadlock
        if ($hasCanUseTool) {
            return $this->runWithCanUseTool($prompt, $options);
        }

        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $process->run();

        $output = $process->getOutput();
        $messages = $this->parseOutput($output);
        $exitCode = $process->getExitCode();

        // Capture stderr for callback
        $stderr = trim($process->getErrorOutput());
        if ($stderr !== '' && $options->stderr) {
            ($options->stderr)($stderr);
        }

        if ($exitCode !== 0 && $exitCode !== null) {
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
     * Run a query with canUseTool callback via IPC.
     * Uses start() + polling instead of blocking run() to handle IPC requests.
     *
     * @return Message[]
     */
    private function runWithCanUseTool(string $prompt, ClaudeAgentOptions $options): array
    {
        $this->setupCanUseToolIpc($options);

        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $process->start();

        $buffer = '';
        $messages = [];

        while ($process->isRunning()) {
            // Read stdout
            $output = $process->getIncrementalOutput();
            if ($output !== '') {
                $buffer .= $output;

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    $message = $this->parseLine($line);
                    if ($message) {
                        $messages[] = $message;
                    }
                }
            }

            // Handle stderr callback
            $errorOutput = $process->getIncrementalErrorOutput();
            if ($errorOutput !== '' && $options->stderr) {
                ($options->stderr)($errorOutput);
            }

            // Handle canUseTool IPC
            $this->processCanUseToolIpc($options->canUseTool);

            usleep(10000); // 10ms
        }

        // Process remaining buffer
        $message = $this->parseLine($buffer);
        if ($message) {
            $messages[] = $message;
        }

        // Final stderr
        $stderr = trim($process->getErrorOutput());
        if ($stderr !== '' && $options->stderr) {
            ($options->stderr)($stderr);
        }

        $this->cleanupIpc();

        $exitCode = $process->getExitCode();

        if ($exitCode !== 0 && $exitCode !== null) {
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
        $hasCanUseTool = $options->canUseTool !== null;

        if ($hasCanUseTool) {
            $this->setupCanUseToolIpc($options);
        }

        $args = $this->buildCommand($prompt, $options);
        $env = $options->toEnv($this->defaultEnv);

        $this->process = new Process($args, $options->cwd, $env, null, $this->timeout);
        $this->process->start();

        $buffer = '';
        $hasMessages = false;

        while ($this->process->isRunning()) {
            $output = $this->process->getIncrementalOutput();
            if ($output !== '') {
                $buffer .= $output;

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

            // Handle stderr callback
            $errorOutput = $this->process->getIncrementalErrorOutput();
            if ($errorOutput !== '' && $options->stderr) {
                ($options->stderr)($errorOutput);
            }

            // Handle canUseTool IPC
            if ($hasCanUseTool) {
                $this->processCanUseToolIpc($options->canUseTool);
            }

            usleep(10000); // 10ms
        }

        // Process remaining buffer
        $message = $this->parseLine($buffer);
        if ($message) {
            $hasMessages = true;
            yield $message;
        }

        $exitCode = $this->process->getExitCode();
        $stderr = trim($this->process->getErrorOutput());

        // Final stderr callback
        if ($stderr !== '' && $options->stderr) {
            ($options->stderr)($stderr);
        }

        $this->process = null;

        if ($hasCanUseTool) {
            $this->cleanupIpc();
        }

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
     * Stop the running process gracefully (interrupt).
     */
    public function stop(): void
    {
        if ($this->process && $this->process->isRunning()) {
            $this->process->signal(SIGINT);
        }
    }

    /**
     * Send an interrupt signal to the running process.
     * Unlike stop(), interrupt allows the agent to finish its current thought
     * before stopping.
     */
    public function interrupt(): void
    {
        $this->stop();
    }

    /**
     * Check if a process is currently running.
     */
    public function isRunning(): bool
    {
        return $this->process !== null && $this->process->isRunning();
    }

    // ─── canUseTool IPC ──────────────────────────────────────────────

    /**
     * Set up IPC directory and hook for canUseTool callback.
     */
    private function setupCanUseToolIpc(ClaudeAgentOptions $options): void
    {
        $this->ipcDir = sys_get_temp_dir() . '/claude_ipc_' . bin2hex(random_bytes(8));
        @mkdir($this->ipcDir, 0700, true);

        // Generate the hook script
        $scriptPath = $this->ipcDir . '/hook.php';
        $ipcDirEscaped = addslashes($this->ipcDir);

        $script = <<<PHP
<?php
// Auto-generated by ClaudeAgentSDK canUseTool IPC - Do not edit
\$ipcDir = '{$ipcDirEscaped}';
\$requestId = uniqid('req_', true);

// Read hook event from stdin
\$event = json_decode(file_get_contents('php://stdin'), true);

// Write request
file_put_contents("\$ipcDir/\$requestId.req", json_encode(\$event));

// Wait for response (poll with timeout)
\$timeout = 60;
\$start = microtime(true);
while (!file_exists("\$ipcDir/\$requestId.res")) {
    if (microtime(true) - \$start > \$timeout) {
        echo json_encode(['hookSpecificOutput' => [
            'hookEventName' => 'PreToolUse',
            'permissionDecision' => 'allow',
        ]]);
        exit(0);
    }
    usleep(10000);
}

\$response = file_get_contents("\$ipcDir/\$requestId.res");
@unlink("\$ipcDir/\$requestId.res");
echo \$response;
PHP;

        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);

        // Register as a PreToolUse hook
        $hookCommand = PHP_BINARY . ' ' . escapeshellarg($scriptPath);
        $options->hook(
            HookEvent::PreToolUse,
            new HookMatcher(matcher: null, hooks: [$hookCommand], timeout: 60),
        );
    }

    /**
     * Process pending canUseTool IPC requests.
     */
    private function processCanUseToolIpc(?callable $canUseTool): void
    {
        if (! $this->ipcDir || ! $canUseTool || ! is_dir($this->ipcDir)) {
            return;
        }

        $requestFiles = glob($this->ipcDir . '/*.req');
        if (empty($requestFiles)) {
            return;
        }

        foreach ($requestFiles as $reqFile) {
            $eventJson = @file_get_contents($reqFile);
            @unlink($reqFile);

            if ($eventJson === false) {
                continue;
            }

            $event = json_decode($eventJson, true);
            if (! is_array($event)) {
                continue;
            }

            $toolName = $event['tool_name'] ?? '';
            $toolInput = $event['tool_input'] ?? [];

            $result = $canUseTool($toolName, $toolInput);

            $hookOutput = method_exists($result, 'toHookOutput')
                ? $result->toHookOutput()
                : [
                    'hookSpecificOutput' => [
                        'hookEventName' => 'PreToolUse',
                        'permissionDecision' => $result instanceof PermissionResultAllow ? 'allow' : 'deny',
                    ],
                ];

            $resFile = str_replace('.req', '.res', $reqFile);
            file_put_contents($resFile, json_encode($hookOutput));
        }
    }

    /**
     * Clean up IPC directory and files.
     */
    private function cleanupIpc(): void
    {
        if (! $this->ipcDir || ! is_dir($this->ipcDir)) {
            return;
        }

        $files = glob($this->ipcDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->ipcDir);

        $this->ipcDir = null;
    }

    // ─── Command building ────────────────────────────────────────────

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
