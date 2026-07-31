<script setup lang="ts">
import { IconServerCog } from '@tabler/icons-vue';

import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useManagedProcessSubnavigation } from '../../Composables/useManagedProcessUi';
import { useTranslator } from '../../Localization/translator';

const props = defineProps<{
    title: string;
    currentPath: string;
}>();

const { t } = useTranslator();
const subnavigation = useManagedProcessSubnavigation(props.currentPath, t);
</script>

<template>
    <AdminLayout
        :title="title"
        :title-icon="IconServerCog"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.managed_processes.nav.label')"
    >
        <slot />
    </AdminLayout>
</template>
