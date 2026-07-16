<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUsersGroup } from '@tabler/icons-vue';

import AdminRecordActions from '../../../Components/AdminRecordActions.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface TeamFormData {
    publicId: string;
    name: string;
    isActive: boolean;
}

const props = defineProps<{
    team: TeamFormData;
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: props.team.name,
});
const recordActions = [
    { key: 'activate', label: 'Activate', method: 'post' as const, href: `/admin/teams/${props.team.publicId}/activate` },
    { key: 'deactivate', label: 'Deactivate', method: 'post' as const, href: `/admin/teams/${props.team.publicId}/deactivate` },
    { key: 'delete', label: 'Delete', method: 'delete' as const, href: `/admin/teams/${props.team.publicId}` },
];

function submit(): void {
    form.patch(`/admin/teams/${props.team.publicId}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.edit.title')" :title-icon="IconUsersGroup">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Actions</h2>
                <AdminRecordActions class="mt-3" :actions="recordActions" />
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <AtlasForm
                    class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                    :processing="form.processing"
                    @submit="submit"
                >
                    <FormInput v-model="form.name" label="Name" :error="form.errors.name" />

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <FormButton type="submit" :loading="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save changes' }}
                        </FormButton>
                        <Link
                            href="/admin/teams"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                        >
                            <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                            Back to teams
                        </Link>
                    </div>
                </AtlasForm>

                <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Team status</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Active</dt>
                            <dd><StatusBadge :value="team.isActive" /></dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Public ID</dt>
                            <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ team.publicId }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
