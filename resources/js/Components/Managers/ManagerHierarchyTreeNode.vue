<script setup lang="ts">
import { IconStarFilled, IconUser } from '@tabler/icons-vue';

import TextBadge from '../TextBadge.vue';
import { useTranslator } from '../../Localization/translator';

interface ManagerHierarchyNode {
    userPublicId: string;
    name: string;
    email: string;
    headManager: boolean;
    reports: ManagerHierarchyNode[];
}

defineProps<{
    node: ManagerHierarchyNode;
}>();

const { t } = useTranslator();
</script>

<template>
    <article class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950">
        <div class="flex min-w-0 items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <span
                    class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                    :class="
                        node.headManager
                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200'
                            : 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200'
                    "
                >
                    <IconStarFilled v-if="node.headManager" aria-hidden="true" class="h-4 w-4" />
                    <IconUser v-else aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                </span>
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ node.name }}</h3>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ node.email }}</p>
                </div>
            </div>
            <TextBadge
                :label="node.headManager ? t('pages.admin.managers.tree.head_manager') : t('pages.admin.managers.tree.manager')"
                :tone="node.headManager ? 'warning' : 'info'"
            />
        </div>

        <ul v-if="node.reports.length > 0" class="mt-3 ml-4 space-y-2 border-l border-zinc-200 pl-4 dark:border-zinc-800">
            <li v-for="report in node.reports" :key="report.userPublicId">
                <ManagerHierarchyTreeNode :node="report" />
            </li>
        </ul>
    </article>
</template>
