<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application;

use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationTypeDirectory;
use App\Shared\Application\Chat\Permissions\ChatPermissionNames;

final class NotificationTypeCatalog implements NotificationTypeDirectory
{
    /**
     * @return list<array{type: string, labelKey: string, descriptionKey: string, bodyPreviewKey: string, bodyPreviewParams: array<string, int|string>, permissionNames: list<string>}>
     */
    public function types(): array
    {
        return [
            [
                'type' => 'chat.meeting.invitation',
                'labelKey' => 'notifications.types.chat.meeting_invitation.label',
                'descriptionKey' => 'notifications.types.chat.meeting_invitation.description',
                'bodyPreviewKey' => 'notifications.meeting.invitation.body',
                'bodyPreviewParams' => ['meeting' => 'Plan tygodnia'],
                'permissionNames' => [ChatPermissionNames::MEETING_SHOW],
            ],
            [
                'type' => 'chat.meeting.updated',
                'labelKey' => 'notifications.types.chat.meeting_updated.label',
                'descriptionKey' => 'notifications.types.chat.meeting_updated.description',
                'bodyPreviewKey' => 'notifications.meeting.updated.body',
                'bodyPreviewParams' => ['meeting' => 'Plan tygodnia'],
                'permissionNames' => [ChatPermissionNames::MEETING_SHOW],
            ],
            [
                'type' => 'chat.meeting.cancelled',
                'labelKey' => 'notifications.types.chat.meeting_cancelled.label',
                'descriptionKey' => 'notifications.types.chat.meeting_cancelled.description',
                'bodyPreviewKey' => 'notifications.meeting.cancelled.body',
                'bodyPreviewParams' => ['meeting' => 'Plan tygodnia'],
                'permissionNames' => [ChatPermissionNames::MEETING_SHOW],
            ],
            [
                'type' => 'chat.call.missed',
                'labelKey' => 'notifications.types.chat.call_missed.label',
                'descriptionKey' => 'notifications.types.chat.call_missed.description',
                'bodyPreviewKey' => 'notifications.chat.call_missed.body',
                'bodyPreviewParams' => ['caller' => 'Anna Kowalska'],
                'permissionNames' => [ChatPermissionNames::CALL_INDEX],
            ],
            [
                'type' => 'report_export.available',
                'labelKey' => 'notifications.types.report_export.available.label',
                'descriptionKey' => 'notifications.types.report_export.available.description',
                'bodyPreviewKey' => 'notifications.exports.available.body',
                'bodyPreviewParams' => ['report_name' => 'Raport czasu pracy'],
                'permissionNames' => ['exports.request', 'exports.data-table'],
            ],
            [
                'type' => 'report_export.failed',
                'labelKey' => 'notifications.types.report_export.failed.label',
                'descriptionKey' => 'notifications.types.report_export.failed.description',
                'bodyPreviewKey' => 'notifications.exports.failed.body',
                'bodyPreviewParams' => ['report_name' => 'Raport czasu pracy'],
                'permissionNames' => ['exports.request', 'exports.data-table'],
            ],
            [
                'type' => 'managed_process.succeeded',
                'labelKey' => 'notifications.types.managed_process.succeeded.label',
                'descriptionKey' => 'notifications.types.managed_process.succeeded.description',
                'bodyPreviewKey' => 'notifications.managed_process.succeeded.body',
                'bodyPreviewParams' => ['process_name' => 'Synchronizacja raportów'],
                'permissionNames' => ['admin.managed-processes.run', 'admin.managed-processes.definitions.run'],
            ],
            [
                'type' => 'managed_process.warning',
                'labelKey' => 'notifications.types.managed_process.warning.label',
                'descriptionKey' => 'notifications.types.managed_process.warning.description',
                'bodyPreviewKey' => 'notifications.managed_process.warning.body',
                'bodyPreviewParams' => ['process_name' => 'Synchronizacja raportów'],
                'permissionNames' => ['admin.managed-processes.run', 'admin.managed-processes.definitions.run'],
            ],
            [
                'type' => 'managed_process.failed',
                'labelKey' => 'notifications.types.managed_process.failed.label',
                'descriptionKey' => 'notifications.types.managed_process.failed.description',
                'bodyPreviewKey' => 'notifications.managed_process.failed.body',
                'bodyPreviewParams' => ['process_name' => 'Synchronizacja raportów'],
                'permissionNames' => ['admin.managed-processes.run', 'admin.managed-processes.definitions.run'],
            ],
            [
                'type' => 'managed_process.finished',
                'labelKey' => 'notifications.types.managed_process.finished.label',
                'descriptionKey' => 'notifications.types.managed_process.finished.description',
                'bodyPreviewKey' => 'notifications.managed_process.finished.body',
                'bodyPreviewParams' => ['process_name' => 'Synchronizacja raportów'],
                'permissionNames' => ['admin.managed-processes.run', 'admin.managed-processes.definitions.run'],
            ],
            [
                'type' => 'time_tracking.admin_action',
                'labelKey' => 'notifications.types.time_tracking.admin_action.label',
                'descriptionKey' => 'notifications.types.time_tracking.admin_action.description',
                'bodyPreviewKey' => 'notifications.time_tracking.admin_action.work_session_terminated.body',
                'bodyPreviewParams' => [],
                'permissionNames' => ['admin.work-time.summary.index'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (array $type): string => $type['type'], $this->types());
    }
}
