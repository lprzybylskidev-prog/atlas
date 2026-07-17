<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUserScan } from '@tabler/icons-vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

const props = defineProps<{
    target: {
        public_id: string;
        name: string;
        email: string;
        account_sensitivity: string;
    };
    teams: FormSelectOption[];
}>();

const form = useForm({
    team_public_id: props.teams.length === 1 ? props.teams[0].value : '',
    reason: '',
    override_sensitive: false,
});

function submit(): void {
    form.post(`/admin/users/${props.target.public_id}/impersonate`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Start impersonation" />
    <AdminLayout title="Start impersonation" :title-icon="IconUserScan">
        <section class="mx-auto max-w-2xl space-y-5">
            <div
                class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                Business actions during impersonation are real production actions.
            </div>

            <AtlasForm
                class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                :processing="form.processing"
                @submit="submit"
            >
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ target.name }}</p>
                        <p class="break-all text-sm text-zinc-500 dark:text-zinc-400">{{ target.email }}</p>
                        <p class="mt-1 text-xs uppercase text-zinc-500 dark:text-zinc-400">{{ target.account_sensitivity }}</p>
                    </div>

                    <FormSelect
                        v-model="form.team_public_id"
                        label="Active team"
                        :options="teams"
                        placeholder="Select team"
                        :error="form.errors.team_public_id"
                    />
                    <FormTextarea v-model="form.reason" label="Reason" :rows="4" :error="form.errors.reason" />
                    <FormCheckbox
                        v-model="form.override_sensitive"
                        label="Override sensitive-account block"
                        :error="form.errors.override_sensitive"
                    />
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <FormButton type="submit" :loading="form.processing" :disabled="teams.length === 0"> Start impersonation </FormButton>
                    <Link
                        :href="`/admin/users/${target.public_id}/edit`"
                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                    >
                        <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        Back
                    </Link>
                </div>
            </AtlasForm>
        </section>
    </AdminLayout>
</template>
