<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Infrastructure\Persistence;

use App\Modules\Core\Teams\Domain\ValueObjects\TeamPublicId;
use Illuminate\Database\Eloquent\Model;

final class Team extends Model
{
    protected $table = 'teams';

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'name',
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
            'is_active' => 'boolean',
        ];
    }
}
