<?php

namespace ClaudeAgentSDK\Permissions;

use JsonSerializable;

abstract class PermissionResult implements JsonSerializable
{
    abstract public function toArray(): array;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert to hook-compatible output format.
     */
    public function toHookOutput(): array
    {
        $output = [
            'hookSpecificOutput' => [
                'hookEventName' => 'PreToolUse',
            ],
        ];

        if ($this instanceof PermissionResultAllow) {
            $output['hookSpecificOutput']['permissionDecision'] = 'allow';
            if ($this->updatedInput !== null) {
                $output['hookSpecificOutput']['updatedInput'] = $this->updatedInput;
            }
        } else {
            /** @var PermissionResultDeny $this */
            $output['hookSpecificOutput']['permissionDecision'] = 'deny';
            if ($this->message !== '') {
                $output['hookSpecificOutput']['permissionDecisionReason'] = $this->message;
            }
            if ($this->interrupt) {
                $output['continue'] = false;
                $output['stopReason'] = $this->message;
            }
        }

        return $output;
    }
}
