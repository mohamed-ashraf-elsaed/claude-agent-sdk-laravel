# Production Deployment

> Move from development to production with confidence. This guide covers queue integration, process management, resource limits, monitoring, scaling, and operational checklists for running the Claude Agent SDK in a production Laravel application.

## Overview

The SDK spawns a Claude Code CLI subprocess for every query. Each subprocess:

- Takes **5--120 seconds** to complete depending on complexity
- Consumes **50--200 MB of memory**
- Blocks the calling thread until completion

This means **synchronous HTTP requests are not viable** for most use cases. Production deployments should offload agent queries to background jobs via Laravel's queue system.

## Queue Integration

### Why Queues Are Essential

A typical web request has a 30-second timeout. Agent queries routinely exceed that. Running them synchronously will:

- Cause HTTP timeouts and 504 errors
- Block web server workers, reducing throughput
- Leave users staring at a spinner with no feedback

Laravel's queue system solves all three problems: the HTTP request returns immediately, the agent runs in a dedicated worker process, and real-time updates can be broadcast to the client.

### Full Job Example with Streaming Broadcast

```php
use ClaudeAgentSDK\ClaudeAgentManager;
use ClaudeAgentSDK\Messages\AssistantMessage;
use ClaudeAgentSDK\Messages\ResultMessage;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;    // Must exceed process_timeout
    public int $tries = 2;

    public function __construct(
        public string $prompt,
        public int $userId,
        public string $taskId,
    ) {}

    public function handle(ClaudeAgentManager $agent): void
    {
        $options = ClaudeAgentOptions::make()
            ->tools(['Read', 'Grep', 'Glob'])
            ->permission('dontAsk')
            ->maxBudgetUsd(1.00)
            ->maxTurns(15)
            ->user((string) $this->userId);

        try {
            $result = $agent->streamCollect(
                prompt: $this->prompt,
                onMessage: function ($message) {
                    if ($message instanceof AssistantMessage) {
                        broadcast(new AgentProgressEvent(
                            $this->userId,
                            $this->taskId,
                            $message->text(),
                        ));
                    }
                },
                options: $options,
            );

            broadcast(new AgentCompleteEvent(
                $this->userId,
                $this->taskId,
                $result->text(),
                $result->costUsd(),
                $result->turns(),
            ));

            Log::info('Agent job completed', [
                'task_id'  => $this->taskId,
                'cost_usd' => $result->costUsd(),
                'turns'    => $result->turns(),
                'duration' => $result->durationMs(),
            ]);
        } catch (ClaudeAgentException $e) {
            broadcast(new AgentErrorEvent($this->userId, $this->taskId, $e->getMessage()));
            throw $e; // Let the queue retry or fail
        }
    }
}
```

> **Warning:** The job's `$timeout` property must exceed the SDK's `process_timeout` config value. If the queue kills the job before the CLI finishes, you get an orphaned process and a lost result.

### Dispatching from a Controller

```php
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function run(Request $request)
    {
        $request->validate(['prompt' => 'required|string|max:5000']);

        $taskId = (string) Str::uuid();
        RunAgentJob::dispatch($request->prompt, auth()->id(), $taskId);

        return response()->json(['task_id' => $taskId], 202);
    }
}
```

## Async Patterns

### Fire and Forget (Jobs)

The simplest pattern. Dispatch a job and let it run. Results are stored (database, cache, etc.) for later retrieval.

```php
RunAgentJob::dispatch($prompt, $userId, $taskId);
// HTTP response returns immediately
```

### Real-Time Updates (Broadcasting + Jobs)

Combine queue jobs with Laravel Broadcasting for live updates via WebSockets:

```php
// Client-side (Laravel Echo)
Echo.private(`agent.${userId}`)
    .listen('AgentProgressEvent', (e) => appendText(e.text))
    .listen('AgentCompleteEvent', (e) => showResult(e.result))
    .listen('AgentErrorEvent', (e) => showError(e.message));
```

See the full job example above for the server-side broadcasting calls.

### Request-Response with Polling

For simpler frontends that cannot use WebSockets:

```php
// Store result when job completes
Cache::put("agent-result:{$taskId}", [
    'status' => 'complete',
    'text'   => $result->text(),
    'cost'   => $result->costUsd(),
], now()->addHours(1));

// Polling endpoint
Route::get('/agent/status/{taskId}', function (string $taskId) {
    $data = Cache::get("agent-result:{$taskId}", ['status' => 'pending']);
    return response()->json($data);
});
```

```js
const poll = setInterval(async () => {
    const res = await fetch(`/agent/status/${taskId}`);
    const data = await res.json();
    if (data.status === 'complete') {
        clearInterval(poll);
        showResult(data.text);
    }
}, 2000);
```

## Process Management

### Supervisor Configuration

Use [Supervisor](http://supervisord.org/) to keep queue workers running reliably:

```ini
[program:agent-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work --queue=agent --timeout=200 --memory=256 --tries=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/agent-worker.log
stopwaitsecs=210
```

Key settings:

| Setting | Value | Rationale |
|---------|-------|-----------|
| `--timeout` | 200 | Must exceed the longest possible agent query |
| `--memory` | 256 | Worker restarts if PHP process exceeds 256 MB |
| `stopwaitsecs` | 210 | Supervisor waits longer than `--timeout` before force-killing |
| `numprocs` | 4 | Number of parallel agent workers (tune based on server resources) |

> **Tip:** Use a dedicated queue name (e.g. `agent`) so agent jobs do not compete with fast jobs like email sending or notifications.

### Timeout Chain

Timeouts must be ordered correctly to avoid orphaned processes:

```
process_timeout (SDK)  <  job $timeout  <  queue --timeout  <  stopwaitsecs (Supervisor)
        120s                 180s              200s                 210s
```

## Resource Limits

### Per-Query Limits

```php
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

$options = ClaudeAgentOptions::make()
    ->maxBudgetUsd(1.00)       // Hard spend ceiling per query
    ->maxTurns(15)             // Prevent infinite agent loops
    ->maxThinkingTokens(16000); // Cap extended-thinking token usage
```

### Process Timeout

```php
// config/claude-agent.php
'process_timeout' => env('CLAUDE_AGENT_TIMEOUT', 120), // seconds
```

### Rate Limiting

Protect against abuse by rate-limiting agent requests per user:

```php
// In your controller or middleware
RateLimiter::for('agent', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()->id);
});

Route::post('/agent/run', [AgentController::class, 'run'])
    ->middleware('throttle:agent');
```

## Monitoring

### Cost Monitoring

Track spend across all queries and alert on anomalies:

```php
// After each query
$cost = $result->costUsd();

// Store for aggregation
DB::table('agent_costs')->insert([
    'user_id'    => $userId,
    'task_id'    => $taskId,
    'cost_usd'   => $cost,
    'model'      => $result->modelUsage() ? array_key_first($result->modelUsage()) : null,
    'created_at' => now(),
]);

// Alert on high single-query cost
if ($cost > 5.00) {
    Log::warning('High agent cost detected', ['task_id' => $taskId, 'cost' => $cost]);
    // Notify::send(new HighCostAlert($taskId, $cost));
}

// Daily aggregation check (scheduled command)
$dailyCost = DB::table('agent_costs')
    ->whereDate('created_at', today())
    ->sum('cost_usd');

if ($dailyCost > 100.00) {
    Log::critical('Daily agent spend exceeds $100', ['total' => $dailyCost]);
}
```

### Performance Monitoring

```php
Log::info('Agent performance', [
    'task_id'     => $taskId,
    'duration_ms' => $result->durationMs(),
    'turns'       => $result->turns(),
    'cost_usd'    => $result->costUsd(),
    'tools_used'  => collect($result->toolUses())->pluck('name')->countBy()->toArray(),
    'cache_read'  => $result->cacheReadTokens(),
    'cache_write' => $result->cacheCreationTokens(),
]);
```

Track queue depth to detect backlogs:

```php
// Scheduled command running every minute
$pending = DB::table('jobs')->where('queue', 'agent')->count();
if ($pending > 50) {
    Log::warning('Agent queue backlog', ['pending' => $pending]);
}
```

### Error Monitoring

```php
use ClaudeAgentSDK\Exceptions\CliNotFoundException;
use ClaudeAgentSDK\Exceptions\ProcessException;
use ClaudeAgentSDK\Exceptions\ClaudeAgentException;

try {
    $result = $agent->query($prompt, $options);

    if ($result->isError()) {
        Log::warning('Agent returned error result', [
            'task_id' => $taskId,
            'text'    => mb_substr($result->text(), 0, 500),
        ]);
    }
} catch (CliNotFoundException $e) {
    Log::critical('Claude CLI not found -- agent is non-functional', [
        'message' => $e->getMessage(),
    ]);
    // Page on-call: this means no agent queries will work
} catch (ProcessException $e) {
    Log::error('Agent process failed', [
        'task_id'   => $taskId,
        'exit_code' => $e->exitCode,
        'stderr'    => mb_substr($e->stderr, 0, 1000),
    ]);
} catch (ClaudeAgentException $e) {
    Log::error('Agent SDK error', ['task_id' => $taskId, 'error' => $e->getMessage()]);
}
```

> **Tip:** Monitor the `isError()` rate as a percentage of total queries. A sudden spike may indicate a model outage, rate limit, or configuration issue.

## Scaling Considerations

Each agent query spawns a separate CLI process. This has direct implications for scaling:

| Dimension | Consideration |
|-----------|--------------|
| **CPU** | CLI processes are mostly I/O-bound (waiting for API responses), so CPU is rarely the bottleneck |
| **Memory** | Each process uses 50--200 MB. Four concurrent workers need 200--800 MB of headroom |
| **Concurrency** | Scale horizontally by adding more queue workers across multiple servers |
| **Network** | Each process makes outbound API calls. Ensure your network allows sufficient concurrent connections |

> **Warning:** Each agent process consumes 50--200 MB of memory. Running too many concurrent workers on a single server will cause OOM kills. Monitor memory usage and set the `--memory` flag on queue workers.

### Horizontal Scaling

```
Server A: 4 agent workers  (1 GB reserved for agents)
Server B: 4 agent workers  (1 GB reserved for agents)
Server C: 4 agent workers  (1 GB reserved for agents)
= 12 concurrent agent queries
```

Use a shared queue backend (Redis, SQS, database) so all servers pull from the same pool.

## Deployment Checklist

Before going live, verify every item:

- [ ] Claude Code CLI is installed and accessible to the worker user (`which claude`)
- [ ] `ANTHROPIC_API_KEY` (or provider credentials) is set via secrets manager
- [ ] `max_budget_usd` is configured to prevent runaway spend
- [ ] `permission_mode` is set to `dontAsk` with a minimal `allowed_tools` list
- [ ] `process_timeout` is set and the timeout chain is correct
- [ ] Queue workers are running with Supervisor (or equivalent)
- [ ] Monitoring is in place for cost, performance, and errors
- [ ] Error handling catches all `ClaudeAgentException` subtypes
- [ ] Audit logging records every query with user, prompt, cost, and result
- [ ] Input validation sanitizes user-supplied prompts
- [ ] Working directory is scoped (not `/` or a sensitive path)
- [ ] Rate limiting is applied to agent endpoints

## CLI Installation in Production

### System Install

```bash
npm install -g @anthropic-ai/claude-code
which claude  # Verify: /usr/local/bin/claude or similar
```

### Docker Image

```dockerfile
FROM php:8.2-cli

# Install Node.js (required for Claude CLI)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Claude CLI
RUN npm install -g @anthropic-ai/claude-code

# Verify installation
RUN claude --version

# Copy application
COPY . /var/www/app
WORKDIR /var/www/app
```

### Custom Path Configuration

If the CLI is installed in a non-standard location:

```dotenv
CLAUDE_AGENT_CLI_PATH=/opt/claude/bin/claude
```

```php
// config/claude-agent.php
'cli_path' => env('CLAUDE_AGENT_CLI_PATH', null), // null = auto-detect from $PATH
```

## High Availability

### Multiple Workers with Graceful Shutdown

```php
// In your job class
public function handle(ClaudeAgentManager $agent): void
{
    // Register a shutdown handler to stop the CLI process cleanly
    pcntl_signal(SIGTERM, function () use ($agent) {
        Log::info('Received SIGTERM, stopping agent gracefully');
        $agent->stop();
    });

    $result = $agent->query($this->prompt, $options);
    // ...
}
```

The `stop()` method sends `SIGINT` to the CLI subprocess, allowing it to clean up before exiting.

### Retry Strategies

```php
class RunAgentJob implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 30, 60]; // seconds between retries

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(10);
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('Agent job permanently failed', [
            'task_id' => $this->taskId,
            'error'   => $e->getMessage(),
        ]);

        broadcast(new AgentErrorEvent(
            $this->userId,
            $this->taskId,
            'The agent was unable to complete your request. Please try again later.',
        ));
    }
}
```

> **Note:** Not all failures are retryable. A `CliNotFoundException` will fail on every retry -- use the `failed()` method to notify users and operators immediately.

### Health Check Endpoint

```php
use ClaudeAgentSDK\Facades\ClaudeAgent;
use ClaudeAgentSDK\Options\ClaudeAgentOptions;

Route::get('/health/agent', function () {
    try {
        $result = ClaudeAgent::query(
            'Reply with OK',
            ClaudeAgentOptions::make()
                ->maxTurns(1)
                ->maxBudgetUsd(0.01)
                ->tools([]),
        );

        return response()->json([
            'status'  => $result->isSuccess() ? 'healthy' : 'degraded',
            'latency' => $result->durationMs(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error'  => $e->getMessage(),
        ], 503);
    }
});
```

## Next Steps

- [[Security Guide]] -- Permission modes, sandboxing, and input validation
- [[Configuration]] -- Full config reference and environment-specific settings
- [[Streaming]] -- Real-time output patterns for SSE and WebSocket integrations
- [[Error Handling]] -- Comprehensive exception handling strategies
- [[Custom API Providers]] -- Configure Bedrock, Vertex AI, or custom endpoints
- [[Hooks]] -- Pre/post tool-use hooks for validation and auditing
