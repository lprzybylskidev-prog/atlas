<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Core\Teams\Domain\ValueObjects\TeamPublicId;
use Illuminate\Database\Eloquent\Model;

final class Team extends Model
{
    protected $table = TeamsDatabaseTable::TEAMS;

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'name',
        'display_name',
        'inactivity_timeout_minutes',
        'session_max_lifetime_minutes',
        'is_active',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $team): void {
            $publicId = $team->getAttribute('public_id');

            if (! is_string($publicId) || $publicId === '') {
                $team->public_id = TeamPublicId::new()->toString();
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'inactivity_timeout_minutes' => 'integer',
            'session_max_lifetime_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
