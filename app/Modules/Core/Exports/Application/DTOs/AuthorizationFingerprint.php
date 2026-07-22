<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\DTOs;

final readonly class AuthorizationFingerprint
{
    /**
     * @param  list<string>  $permissionNames
     * @param  list<string>  $allowedColumns
     */
    public function __construct(
        public string $moduleKey,
        public ?string $activeTeamPublicId,
        public string $requestingUserPublicId,
        public array $permissionNames,
        public array $allowedColumns,
        public string $ruleVersion,
    ) {}

    /**
     * @return array{module_key: string, active_team_public_id: string|null, requesting_user_public_id: string, permission_names: list<string>, allowed_columns: list<string>, rule_version: string}
     */
    public function toArray(): array
    {
        $permissionNames = array_values(array_unique($this->permissionNames));
        $allowedColumns = array_values(array_unique($this->allowedColumns));

        sort($permissionNames);
        sort($allowedColumns);

        return [
            'module_key' => $this->moduleKey,
            'active_team_public_id' => $this->activeTeamPublicId,
            'requesting_user_public_id' => $this->requestingUserPublicId,
            'permission_names' => $permissionNames,
            'allowed_columns' => $allowedColumns,
            'rule_version' => $this->ruleVersion,
        ];
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }
}
