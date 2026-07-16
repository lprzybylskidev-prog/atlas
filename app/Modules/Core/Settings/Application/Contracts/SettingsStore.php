<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Application\Contracts;

use App\Modules\Core\Settings\Application\Enums\GlobalSettingKey;
use App\Modules\Core\Settings\Application\Enums\SecuritySettingKey;
use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;

interface SettingsStore
{
    public function getGlobal(GlobalSettingKey $key): mixed;

    public function putGlobal(GlobalSettingKey $key, mixed $value): void;

    public function getTeam(int $teamId, TeamSettingKey $key): mixed;

    public function putTeam(int $teamId, TeamSettingKey $key, mixed $value): void;

    public function getUser(int $userId, UserSettingKey $key): mixed;

    public function putUser(int $userId, UserSettingKey $key, mixed $value): void;

    public function getSecurity(SecuritySettingKey $key): mixed;

    public function putSecurity(SecuritySettingKey $key, mixed $value, ?string $actorPublicId = null, ?string $reason = null): void;
}
