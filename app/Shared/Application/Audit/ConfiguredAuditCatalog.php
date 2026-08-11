<?php

declare(strict_types=1);

namespace App\Shared\Application\Audit;

use App\Shared\Application\Audit\Contracts\AuditCatalog;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\AuditResult;
use App\Shared\Application\Audit\Enums\AuditSource;
use InvalidArgumentException;

final readonly class ConfiguredAuditCatalog implements AuditCatalog
{
    /**
     * @param  array<string, array{actions: list<string>, sources: list<string>, target_types: list<string>, aggregate_types: list<string>, metadata_keys: list<string>, security_categories: list<string>}>  $modules
     */
    public function __construct(private array $modules = []) {}

    public function assertRegistered(AuditEvent $event): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $event->module) !== 1) {
            throw new InvalidArgumentException('Audit module must be a registered-style module key.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/', $event->action) !== 1) {
            throw new InvalidArgumentException('Audit action must be a namespaced catalog key.');
        }

        AuditResult::from($event->result);
        AuditSource::from($event->source);

        $module = $this->modules[$event->module] ?? null;

        if ($module === null) {
            throw new InvalidArgumentException(sprintf('Audit module [%s] has no registered catalog.', $event->module));
        }

        if (! in_array($event->action, $module['actions'], true)) {
            throw new InvalidArgumentException(sprintf('Audit action [%s] is not registered by module [%s].', $event->action, $event->module));
        }

        $this->assertAllowed($event->source, $module['sources'], 'source', $event);
        $this->assertOptionalAllowed($event->targetType, $module['target_types'], 'target type', $event);
        $this->assertOptionalAllowed($event->aggregateType, $module['aggregate_types'], 'aggregate type', $event);

        if ($event->securityCategory !== null) {
            $this->assertAllowed($event->securityCategory->value, $module['security_categories'], 'security category', $event);
        }

        $unknownMetadata = array_diff(array_keys($event->metadata), $module['metadata_keys']);

        if ($unknownMetadata !== []) {
            throw new InvalidArgumentException(sprintf(
                'Audit action [%s] contains unregistered metadata keys: %s.',
                $event->action,
                implode(', ', $unknownMetadata),
            ));
        }
    }

    /** @param list<string> $allowed */
    private function assertAllowed(string $value, array $allowed, string $field, AuditEvent $event): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Audit %s [%s] is not registered for action [%s].', $field, $value, $event->action));
        }
    }

    /** @param list<string> $allowed */
    private function assertOptionalAllowed(?string $value, array $allowed, string $field, AuditEvent $event): void
    {
        if ($value !== null && $value !== '') {
            $this->assertAllowed($value, $allowed, $field, $event);
        }
    }
}
