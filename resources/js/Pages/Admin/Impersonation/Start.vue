<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUserScan } from '@tabler/icons-vue';

import ActionLink from '../../../Components/ActionLink.vue';
import FormActions from '../../../Components/FormActions.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
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
    requiresSensitiveOverride: boolean;
}>();

const form = useForm({
    team_public_id: props.teams.length === 1 ? props.teams[0].value : '',
    reason: '',
    override_sensitive: props.requiresSensitiveOverride,
});

function submit(): void {
    form.post(`/admin/users/${props.target.public_id}/impersonate`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Start impersonation" />
    <AdminLayout title="Start impersonation" :title-icon="IconUserScan">
        <PageStack>
            <NoticeBanner tone="warning"> Business actions during impersonation are real production actions. </NoticeBanner>

            <SurfaceCard title="Impersonation request" :icon="IconUserScan">
                <AtlasForm :processing="form.processing" @submit="submit">
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
                            v-if="requiresSensitiveOverride"
                            v-model="form.override_sensitive"
                            label="Override sensitive-account block"
                            :error="form.errors.override_sensitive"
                        />
                    </div>

                    <FormActions class="mt-5">
                        <FormButton type="submit" :loading="form.processing" :disabled="teams.length === 0">
                            Start impersonation
                        </FormButton>
                        <ActionLink :href="`/admin/users/${target.public_id}/edit`" :icon="IconArrowLeft"> Back </ActionLink>
                    </FormActions>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AdminLayout>
</template>
