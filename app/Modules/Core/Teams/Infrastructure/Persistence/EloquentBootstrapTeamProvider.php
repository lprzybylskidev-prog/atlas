<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\DTOs\BootstrapTeam;
use App\Modules\Core\Teams\Domain\ValueObjects\TeamPublicId;

final class EloquentBootstrapTeamProvider implements BootstrapTeamProvider
{
    public function provide(string $name): BootstrapTeam
    {
        $team = Team::query()->firstOrCreate([
            'name' => trim($name) !== '' ? trim($name) : 'Atlas',
        ], [
            'public_id' => TeamPublicId::new()->toString(),
            'is_active' => true,
        ]);

        return new BootstrapTeam(
            publicId: (string) $team->public_id,
            name: $team->name,
        );
    }
}
