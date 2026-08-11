<?php

declare(strict_types=1);

namespace App\Modules\Core\Settings\Infrastructure\Mail;

use App\Modules\Core\Settings\Application\Enums\TeamSettingKey;
use App\Modules\Core\Settings\Application\Enums\UserSettingKey;
use App\Modules\Core\Settings\Infrastructure\Persistence\TableNames\SettingsDatabaseTable;
use App\Shared\Application\Mail\Contracts\MailLocaleSelector;
use App\Shared\Application\Mail\DTOs\MailLocaleOrder;
use Illuminate\Database\ConnectionInterface;
use JsonException;

final readonly class DatabaseMailLocaleSelector implements MailLocaleSelector
{
    public function __construct(private ConnectionInterface $db) {}

    public function select(?int $userId = null, ?int $teamId = null): MailLocaleOrder
    {
        $locale = $userId === null ? null : $this->storedLocale(
            SettingsDatabaseTable::SETTINGS_USER_VALUES,
            ['user_id' => $userId, 'key' => UserSettingKey::UiLocale->value],
        );

        if ($locale === null && $teamId !== null) {
            $locale = $this->storedLocale(
                SettingsDatabaseTable::SETTINGS_TEAM_VALUES,
                ['team_id' => $teamId, 'key' => TeamSettingKey::DefaultLocale->value],
            );
        }

        $locale ??= $this->supported(config('app.locale'));
        $locale ??= $this->supported(config('app.fallback_locale'));
        $locale ??= 'pl';

        return new MailLocaleOrder($locale, $locale === 'pl' ? 'en' : 'pl');
    }

    /** @param array<string, int|string> $identity */
    private function storedLocale(string $table, array $identity): ?string
    {
        $query = $this->db->table($table);

        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $value = $query->value('value');

        if (! is_string($value)) {
            return null;
        }

        try {
            return $this->supported(json_decode($value, true, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    private function supported(mixed $locale): ?string
    {
        return is_string($locale) && in_array($locale, ['pl', 'en'], true) ? $locale : null;
    }
}
