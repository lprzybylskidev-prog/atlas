<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUsersGroup } from '@tabler/icons-vue';

import FormInput from '../../../Components/Form/FormInput.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

const { t } = useTranslator('en');
const form = useForm({ name: '' });

function submit(): void {
    form.post('/admin/teams', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.create.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.create.title')" :title-icon="IconUsersGroup">
        <form
            class="max-w-2xl rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
            @submit.prevent="submit"
        >
            <FormInput v-model="form.name" label="Name" :error="form.errors.name" />

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button
                    type="submit"
                    class="inline-flex h-10 items-center rounded-lg bg-teal-700 px-4 text-sm font-medium text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-600 dark:hover:bg-teal-500"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving...' : 'Save team' }}
                </button>
                <Link
                    href="/admin/teams"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                >
                    <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Back to teams
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
