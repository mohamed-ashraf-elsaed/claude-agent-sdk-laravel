<?php

namespace ClaudeAgentSDK\Permissions;

class PermissionResultAllow extends PermissionResult
{
    /**
     * @param  array|null  $updatedInput  Modified tool input to use instead of the original
     * @param  array|null  $updatedPermissions  Permission updates (addRules, removeRules, setMode, etc.)
     */
    public function __construct(
        public readonly ?array $updatedInput = null,
        public readonly ?array $updatedPermissions = null,
    ) {}

    public function toArray(): array
    {
        $result = ['behavior' => 'allow'];

        if ($this->updatedInput !== null) {
            $result['updatedInput'] = $this->updatedInput;
        }

        if ($this->updatedPermissions !== null) {
            $result['updatedPermissions'] = $this->updatedPermissions;
        }

        return $result;
    }
}
