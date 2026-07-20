<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconShieldSearch } from '@tabler/icons-vue';
import { computed, reactive, watch } from 'vue';

import CardHeader from '../../../Components/CardHeader.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatTimestamp } from '../../../Utils/formatters';

interface SecurityHistoryUser {
    publicId: string;
    name: string;
    email: string;
    context: string;
}

interface SecurityHistoryEvent extends Record<string, unknown> {
    publicId: string;
    occurredAt: string;
    user: SecurityHistoryUser;
    module: string;
    action: string;
    result: string;
    source: string;
    actorPublicId: string;
    actualActorPublicId: string;
    impersonatedUserPublicId: string;
    impersonationSessionId: string;
    targetType: string;
    targetPublicId: string;
    teamPublicId: string;
    reason: string;
}

interface SecurityHistoryFilters {
    userPublicId: string;
}

interface SecurityHistoryOption {
    value: string;
    label: string;
}

const props = defineProps<{
    events: SecurityHistoryEvent[];
    filters: SecurityHistoryFilters;
    userOptions: SecurityHistoryOption[];
}>();

const { t } = useTranslator('en');
const filters = reactive<SecurityHistoryFilters>({ ...props.filters });
const userOptions = [{ value: '', label: 'All users' }, ...props.userOptions];
const appliedUserPublicId = computed(() => props.filters.userPublicId);
const showUserColumn = () => appliedUserPublicId.value === '';

function applyFilters(): void {
    router.get(
        '/admin/audit/security-history',
        {
            user: filters.userPublicId,
        },
        { preserveScroll: true, preserveState: false, replace: true },
    );
}

function clearFilters(): void {
    filters.userPublicId = '';
    applyFilters();
}

watch(
    () => props.filters,
    (nextFilters) => {
        Object.assign(filters, nextFilters);
    },
    { deep: true },
);

function eventUserName(event: SecurityHistoryEvent): string {
    return event.user.name !== '' ? event.user.name : event.user.publicId;
}

function eventUserEmail(event: SecurityHistoryEvent): string {
    if (event.user.email !== '') {
        return event.user.email;
    }

    return event.user.publicId;
}

function eventTimestamp(event: SecurityHistoryEvent): string {
    return formatTimestamp(event.occurredAt, 'en');
}
</script>

<template>
    <Head :title="t('pages.security_history.head_title')" />
    <AdminLayout :title="t('pages.security_history.title')" :title-icon="IconShieldSearch">
        <section class="space-y-5">
            <FilterPanel @apply="applyFilters" @clear="clearFilters">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <FormSelect v-model="filters.userPublicId" class="mt-1" label="User" aria-label="User" :options="userOptions" />
                </div>
            </FilterPanel>
            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <CardHeader :title="t('pages.security_history.title')" :icon="IconShieldSearch" />
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                            <tr>
                                <th v-if="showUserColumn()" scope="col" class="px-4 py-3">User</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.occurred_at') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.action') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.result') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.source') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.team') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.impersonation_session') }}</th>
                                <th scope="col" class="px-4 py-3">{{ t('pages.security_history.table.reason') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <tr v-if="props.events.length === 0">
                                <td
                                    :colspan="showUserColumn() ? 8 : 7"
                                    class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400"
                                >
                                    {{ t('pages.security_history.empty') }}
                                </td>
                            </tr>
                            <tr v-for="event in props.events" v-else :key="event.publicId" class="align-top">
                                <td v-if="showUserColumn()" class="min-w-72 px-4 py-3">
                                    <div class="max-w-80">
                                        <p class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ eventUserName(event) }}</p>
                                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ eventUserEmail(event) }}</p>
                                        <p v-if="event.user.context" class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                            {{ event.user.context }}
                                        </p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ eventTimestamp(event) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-700 dark:text-zinc-300">
                                    {{ event.action }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ event.result }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ event.source }}</td>
                                <td class="break-all px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ event.teamPublicId }}
                                </td>
                                <td class="break-all px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ event.impersonationSessionId }}
                                </td>
                                <td class="min-w-56 px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ event.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
