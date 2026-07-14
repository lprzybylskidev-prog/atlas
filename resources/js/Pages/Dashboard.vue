<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppLayout from '../Layouts/AppLayout.vue';
import { useTranslator } from '../Localization/translator';

const { t } = useTranslator();
</script>

<template>
    <Head :title="t('pages.dashboard.head_title')" />
    <AppLayout :title="t('pages.dashboard.title')" />
</template>
