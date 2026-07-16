<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck } from '@tabler/icons-vue';

import FormCheckbox from '../../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';

defineProps<{
    permissionOptions: string[];
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: '',
    permissions: [] as string[],
});

function submit(): void {
    form.post('/admin/authorization/roles', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.roles.create.head_title')" />
    <AdminLayout :title="t('pages.admin.roles.create.title')" :title-icon="IconShieldCheck">
        <form class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,32rem)]" @submit.prevent="submit">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <FormInput v-model="form.name" label="Name" :error="form.errors.name" monospace />
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Permissions</p>
                <div
                    class="mt-2 grid max-h-[32rem] grid-cols-1 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                >
                    <FormCheckbox
                        v-for="permission in permissionOptions"
                        :key="permission"
                        v-model="form.permissions"
                        class="w-full"
                        :value="permission"
                        align="start"
                    >
                        <span class="break-all font-mono text-xs">{{ permission }}</span>
                    </FormCheckbox>
                </div>
                <p v-if="form.errors.permissions" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.permissions }}
                </p>
            </section>

            <div class="flex flex-wrap items-center gap-2 xl:col-span-2">
                <button
                    type="submit"
                    class="inline-flex h-10 items-center rounded-lg bg-teal-700 px-4 text-sm font-medium text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-600 dark:hover:bg-teal-500"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving...' : 'Save role' }}
                </button>
                <Link
                    href="/admin/authorization/roles"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                >
                    <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Back to roles
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
