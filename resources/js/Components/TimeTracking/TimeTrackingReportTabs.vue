<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { useTranslator } from '../../Localization/translator';

export type TimeTrackingReportSection = 'daily' | 'work_sessions' | 'breaks' | 'other_work' | 'corrections';

defineProps<{ active: TimeTrackingReportSection }>();

const { t } = useTranslator();
const sections: TimeTrackingReportSection[] = ['daily', 'work_sessions', 'breaks', 'other_work', 'corrections'];

function href(section: TimeTrackingReportSection): string {
    const query = new URLSearchParams(window.location.search);
    query.set('section', section);
    query.delete('page');

    return `/user/work-time?${query.toString()}`;
}
</script>

<template>
    <nav
        class="inline-flex flex-wrap rounded-md border border-zinc-200 bg-white p-1 dark:border-zinc-800 dark:bg-zinc-900"
        :aria-label="t('pages.time_tracking.user_report.tabs.label')"
    >
        <Link
            v-for="section in sections"
            :key="section"
            :href="href(section)"
            preserve-scroll
            class="rounded px-3 py-1.5 text-sm font-medium transition focus-visible:outline focus-visible:outline-amber-500"
            :class="
                active === section
                    ? 'bg-teal-600 text-white shadow-sm'
                    : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'
            "
            :aria-current="active === section ? 'page' : undefined"
        >
            {{ t(`pages.time_tracking.user_report.tabs.${section}`) }}
        </Link>
    </nav>
</template>
