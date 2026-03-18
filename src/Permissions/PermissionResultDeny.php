<?php

namespace ClaudeAgentSDK\Permissions;

class PermissionResultDeny extends PermissionResult
{
    /**
     * @param  string  $message  Explanation shown to the agent for why the tool was denied
     * @param  bool  $interrupt  If true, stops agent execution entirely (not just the tool call)
     */
    public function __construct(
        public readonly string $message = '',
        public readonly bool $interrupt = false,
    ) {}

    public function toArray(): array
    {
        $result = [
            'behavior' => 'deny',
            'message' => $this->message,
        ];

        if ($this->interrupt) {
            $result['interrupt'] = true;
        }

        return $result;
    }
}
