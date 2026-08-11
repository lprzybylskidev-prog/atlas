<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Console;

use App\Modules\Core\Identity\Application\Public\Contracts\UserLookup;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use Illuminate\Console\Command;

final class SendNotificationCommand extends Command
{
    protected $signature = 'notifications:send
        {--user= : Recipient user public ID}
        {--email= : Recipient user email}
        {--team= : Optional team public ID}
        {--type=manual.notification : Notification type}
        {--severity=info : Severity: info, success, warning, error}
        {--title= : Notification title}
        {--body= : Notification body}
        {--title-pl= : Polish title used when APP_LOCALE is pl}
        {--body-pl= : Polish body used when APP_LOCALE is pl}
        {--title-en= : English title used when APP_LOCALE is en}
        {--body-en= : English body used when APP_LOCALE is en}
        {--link= : Optional deep link URL}
        {--email-channel : Request email delivery}';

    protected $description = 'Send a typed notification to a user.';

    public function handle(NotificationPublisher $notifications, UserLookup $users): int
    {
        $userPublicId = $this->recipientUserPublicId($users);

        if ($userPublicId === null) {
            $this->error('Provide an existing recipient with --user=PUBLIC_ID or --email=EMAIL.');

            return self::FAILURE;
        }

        $title = $this->localizedOption('title');

        if ($title === '') {
            $this->error('Provide --title or locale-specific --title-pl/--title-en.');

            return self::FAILURE;
        }

        $publicId = $notifications->publish(new CreateNotification(
            type: $this->stringOption('type', 'manual.notification'),
            title: $title,
            body: $this->optionalLocalizedOption('body'),
            recipientUserPublicId: $userPublicId,
            teamPublicId: $this->nullableStringOption('team'),
            severity: $this->stringOption('severity', 'info'),
            deepLinkUrl: $this->nullableStringOption('link'),
            data: [
                'source' => 'console',
                'title_pl' => $this->nullableStringOption('title-pl'),
                'title_en' => $this->nullableStringOption('title-en'),
                'body_pl' => $this->nullableStringOption('body-pl'),
                'body_en' => $this->nullableStringOption('body-en'),
            ],
            emailRequested: (bool) $this->option('email-channel'),
        ));

        $this->info(sprintf('Notification [%s] was queued for delivery.', $publicId));

        return self::SUCCESS;
    }

    private function recipientUserPublicId(UserLookup $users): ?string
    {
        $user = $this->nullableStringOption('user');

        if ($user !== null) {
            return $users->internalIdForPublicId($user) === null ? null : $user;
        }

        $email = $this->nullableStringOption('email');

        if ($email === null) {
            return null;
        }

        return $users->publicIdForEmail($email);
    }

    private function localizedOption(string $name): string
    {
        return $this->optionalLocalizedOption($name) ?? '';
    }

    private function optionalLocalizedOption(string $name): ?string
    {
        $locale = config()->string('app.locale');
        $localized = $this->nullableStringOption($name.'-'.$locale);

        return $localized ?? $this->nullableStringOption($name);
    }

    private function stringOption(string $name, string $fallback): string
    {
        return $this->nullableStringOption($name) ?? $fallback;
    }

    private function nullableStringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
