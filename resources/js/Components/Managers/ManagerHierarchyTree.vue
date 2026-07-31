<script setup lang="ts">
import { IconSitemap } from '@tabler/icons-vue';

import ManagerHierarchyTreeNode from './ManagerHierarchyTreeNode.vue';
import SurfaceCard from '../SurfaceCard.vue';
import UiState from '../UiState.vue';
import { useTranslator } from '../../Localization/translator';

export interface ManagerHierarchyNode {
    userPublicId: string;
    name: string;
    email: string;
    headManager: boolean;
    reports: ManagerHierarchyNode[];
}

defineProps<{
    nodes: ManagerHierarchyNode[];
}>();

const { t } = useTranslator();
</script>

<template>
    <SurfaceCard :title="t('pages.admin.managers.tree.title')" :icon="IconSitemap" tone="teal">
        <UiState
            v-if="nodes.length === 0"
            variant="empty"
            size="compact"
            :title="t('pages.admin.managers.tree.empty_title')"
            :description="t('pages.admin.managers.tree.empty_description')"
        />
        <div v-else class="overflow-x-auto">
            <ul class="min-w-[28rem] space-y-2" :aria-label="t('pages.admin.managers.tree.aria')">
                <li v-for="node in nodes" :key="node.userPublicId">
                    <ManagerHierarchyTreeNode :node="node" />
                </li>
            </ul>
        </div>
    </SurfaceCard>
</template>
