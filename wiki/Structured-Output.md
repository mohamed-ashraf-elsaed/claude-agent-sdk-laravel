# Structured Output

Get validated JSON responses that conform to a JSON Schema.

## Basic Usage
```php
$options = ClaudeAgentOptions::make()
    ->tools(['Read', 'Grep'])
    ->outputFormat([
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'files_analyzed' => ['type' => 'number'],
            'issues' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'file' => ['type' => 'string'],
                        'line' => ['type' => 'number'],
                        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['file', 'severity', 'message'],
                ],
            ],
        ],
        'required' => ['summary', 'files_analyzed', 'issues'],
    ]);

$result = ClaudeAgent::query('Audit the codebase for security issues', $options);

// Access as typed array
$data = $result->structured();
echo "Found {$data['files_analyzed']} files with issues:\n";
foreach ($data['issues'] as $issue) {
    echo "[{$issue['severity']}] {$issue['file']}: {$issue['message']}\n";
}

// text() still returns the raw result string
echo $result->text();
```