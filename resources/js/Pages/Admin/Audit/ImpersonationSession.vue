<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconArrowLeft, IconHistory, IconListDetails, IconShieldCheck, IconUserScan } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import DataTable from '../../../Components/DataTable.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ShellSubnavigationItem } from '../../../Types/navigation';
import { formatDateTime, formatEmpty } from '../../../Utils/formatters';

interface AuditEventRow extends Record<string, unknown> {
    publicId: string;
    occurredAt: string;
    module: string;
    action: string;
    result: string;
    source: string;
    actorPublicId: string;
    actualActorPublicId: string;
    impersonatedUserPublicId: string;
    targetType: string;
    targetPublicId: string;
    teamPublicId: string;
    correlationId: string;
    reason: string;
    security: boolean;
}

interface ImpersonationSession {
    id: string;
    startedAt: string;
    endedAt: string | null;
    actualActorPublicId: string;
    impersonatedUserPublicId: string;
    teamPublicId: string;
    reason: string;
    operationCount: number;
    rejectedCount: number;
    securityCount: number;
}

const props = defineProps<{
    session: ImpersonationSession;
    events: AuditEventRow[];
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();

const subnavigation = computed<ShellSubnavigationItem[]>(() => [
    {
        key: 'audit.events',
        label: t('pages.admin.audit.nav.events'),
        href: '/admin/audit',
        icon: IconHistory,
        active: false,
    },
    {
        key: 'audit.security',
        label: t('pages.admin.audit.nav.security_history'),
        href: '/admin/audit/security-history',
        icon: IconShieldCheck,
        active: false,
    },
]);
const columns = computed<DataTableColumn<AuditEventRow>[]>(() => [
    { key: 'occurredAt', label: t('pages.admin.audit.table.occurred_at'), format: 'datetime' },
    { key: 'module', label: t('pages.admin.audit.table.module'), format: 'status' },
    { key: 'action', label: t('pages.admin.audit.table.action') },
    { key: 'result', label: t('pages.admin.audit.table.result'), format: 'status' },
    { key: 'source', label: t('pages.admin.audit.table.source'), format: 'status' },
    { key: 'actorPublicId', label: t('pages.admin.audit.table.actor') },
    { key: 'targetType', label: t('pages.admin.audit.table.target_type'), format: 'status' },
    { key: 'targetPublicId', label: t('pages.admin.audit.table.target') },
    { key: 'teamPublicId', label: t('pages.admin.audit.table.team') },
    { key: 'reason', label: t('pages.admin.audit.table.reason') },
    { key: 'correlationId', label: t('pages.admin.audit.table.correlation'), hidden: true },
    { key: 'actualActorPublicId', label: t('pages.admin.audit.table.actual_actor'), hidden: true },
    { key: 'impersonatedUserPublicId', label: t('pages.admin.audit.table.impersonated_user'), hidden: true },
    { key: 'security', label: t('pages.admin.audit.table.security'), format: 'boolean', hidden: true },
    { key: 'publicId', label: t('pages.admin.audit.table.public_id'), hidden: true },
]);
const tableFilters = computed(() => ({ session: props.session.id }));

function timestamp(value: string | null): string {
    return formatDateTime(value, locale.value);
}

function text(value: string | null): string {
    return formatEmpty(value);
}
</script>

<template>
    <Head :title="t('pages.admin.audit.impersonation.head_title')" />
    <AdminLayout
        :title="t('pages.admin.audit.impersonation.title')"
        :title-icon="IconUserScan"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.audit.nav.label')"
    >
        <PageStack>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <ActionLink href="/admin/audit" tone="neutral" :icon="IconArrowLeft">
                    {{ t('actions.back') }}
                </ActionLink>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <OperationalMetricTile
                    :label="t('pages.admin.audit.impersonation.metric.operations')"
                    :value="session.operationCount"
                    :icon="IconListDetails"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.impersonation.metric.rejected')"
                    :value="session.rejectedCount"
                    :icon="IconAlertTriangle"
                    :tone="session.rejectedCount > 0 ? 'rose' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.impersonation.metric.security')"
                    :value="session.securityCount"
                    :icon="IconShieldCheck"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.audit.impersonation.metric.status')"
                    :value="
                        session.endedAt === null
                            ? t('pages.admin.audit.impersonation.status.open')
                            : t('pages.admin.audit.impersonation.status.closed')
                    "
                    :icon="IconUserScan"
                    :tone="session.endedAt === null ? 'rose' : 'zinc'"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.audit.impersonation.summary.title')" :icon="IconUserScan" tone="sky">
                <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.session') }}
                        </dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ session.id }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.actual_actor') }}
                        </dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ text(session.actualActorPublicId) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.impersonated_user') }}
                        </dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ text(session.impersonatedUserPublicId) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.team') }}
                        </dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ text(session.teamPublicId) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.started_at') }}
                        </dt>
                        <dd class="mt-1 text-zinc-950 dark:text-zinc-50">{{ timestamp(session.startedAt) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.ended_at') }}
                        </dt>
                        <dd class="mt-1 text-zinc-950 dark:text-zinc-50">{{ timestamp(session.endedAt) }}</dd>
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.audit.impersonation.summary.reason') }}
                        </dt>
                        <dd class="mt-1 text-zinc-950 dark:text-zinc-50">{{ text(session.reason) }}</dd>
                    </div>
                </dl>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.audit.impersonation.events.title')"
                :rows="events"
                :columns="columns"
                row-key="publicId"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.audit.impersonation.events.empty')"
            />
        </PageStack>
    </AdminLayout>
</template>
