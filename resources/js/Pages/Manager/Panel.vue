<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';

import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';

const { t } = useTranslator();
</script>

<template>
    <Head :title="t('pages.manager_panel.head_title')" />
    <AppLayout :title="t('pages.manager_panel.title')" :title-icon="IconUsersGroup" mode="manager" />
</template>
