<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Presentation\Console;

use App\Modules\Core\Notifications\Application\Public\Contracts\RealtimePublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\PublishRealtimeEvent;
use Illuminate\Console\Command;

final class PublishRealtimeEventCommand extends Command
{
    protected $signature = 'realtime:publish
        {topic : Event topic: sessions, system-alerts, operation-progress, notifications, or a documented topic}
        {--event= : Event type}
        {--user= : Optional user public ID}
        {--team= : Optional team public ID}
        {--title= : System alert title}
        {--body= : System alert body or progress message}
        {--severity=info : System alert severity}
        {--operation-type=manual : Progress operation type}
        {--operation-id=manual : Progress operation identifier}
        {--status=running : Progress status}
        {--progress=0 : Progress percent}
        {--session= : Invalidated session ID}';

    protected $description = 'Publish a typed realtime event through the Notifications realtime foundation.';

    public function handle(RealtimePublisher $realtime): int
    {
        $topic = $this->topicArgument();
        $publicId = match ($topic) {
            'sessions' => $realtime->publishSessionInvalidated(
                userPublicId: $this->requiredOption('user'),
                teamPublicId: $this->nullableOption('team'),
                sessionId: $this->nullableOption('session'),
            ),
            'system-alerts' => $realtime->publishSystemAlert(
                title: $this->requiredOption('title'),
                severity: $this->nullableOption('severity') ?? 'info',
                body: $this->nullableOption('body'),
                userPublicId: $this->nullableOption('user'),
                teamPublicId: $this->nullableOption('team'),
            ),
            'operation-progress' => $realtime->publishOperationProgress(
                operationType: $this->nullableOption('operation-type') ?? 'manual',
                operationId: $this->nullableOption('operation-id') ?? 'manual',
                status: $this->nullableOption('status') ?? 'running',
                progressPercent: max(0, min(100, (int) ($this->option('progress') ?? 0))),
                userPublicId: $this->nullableOption('user'),
                teamPublicId: $this->nullableOption('team'),
                message: $this->nullableOption('body'),
            ),
            default => $realtime->publishRealtime(new PublishRealtimeEvent(
                topic: $topic,
                eventType: $this->nullableOption('event') ?? $topic.'.published',
                userPublicId: $this->nullableOption('user'),
                teamPublicId: $this->nullableOption('team'),
                payload: ['source' => 'console'],
            )),
        };

        $this->info(sprintf('Realtime event [%s] was published.', $publicId));

        return self::SUCCESS;
    }

    private function topicArgument(): string
    {
        $topic = $this->argument('topic');

        if (! is_string($topic) || trim($topic) === '') {
            throw new \InvalidArgumentException('The topic argument is required.');
        }

        return trim($topic);
    }

    private function requiredOption(string $name): string
    {
        $value = $this->nullableOption($name);

        if ($value === null) {
            throw new \InvalidArgumentException(sprintf('Option --%s is required.', $name));
        }

        return $value;
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
