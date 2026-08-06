<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck, IconUserScan } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import FormActions from '../../../Components/FormActions.vue';
import NoticeBanner from '../../../Components/NoticeBanner.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { formatEmpty } from '../../../Utils/formatters';

interface TargetUser {
    publicId: string;
    name: string;
    email: string;
    accountSensitivity: string;
}

const props = defineProps<{
    target: TargetUser;
    teams: FormSelectOption[];
    requiresSensitiveOverride: boolean;
}>();

const { t } = useTranslator();
const form = useForm<{
    team_public_id: string;
    reason: string;
    override_sensitive: boolean;
}>({
    team_public_id: String(props.teams[0]?.value ?? ''),
    reason: '',
    override_sensitive: false,
});

const sensitivityLabel = computed(() => {
    const keys: Record<string, string> = {
        integration: 'pages.admin.impersonation.sensitivity.integration',
        normal: 'pages.admin.impersonation.sensitivity.normal',
        sensitive: 'pages.admin.impersonation.sensitivity.sensitive',
        service: 'pages.admin.impersonation.sensitivity.service',
        technical: 'pages.admin.impersonation.sensitivity.technical',
    };

    return keys[props.target.accountSensitivity] === undefined ? props.target.accountSensitivity : t(keys[props.target.accountSensitivity]);
});

function submit(): void {
    form.post(`/admin/users/${encodeURIComponent(props.target.publicId)}/impersonate`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.impersonation.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.impersonation.title')" :title-icon="IconUserScan">
        <PageStack>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <ActionLink href="/admin/users" tone="neutral" :icon="IconArrowLeft">
                    {{ t('actions.back') }}
                </ActionLink>
            </div>

            <NoticeBanner :title="t('pages.admin.impersonation.notice.title')" tone="warning">
                {{ t('pages.admin.impersonation.notice.body') }}
            </NoticeBanner>

            <SurfaceCard :title="t('pages.admin.impersonation.target.title')" :icon="IconUserScan" tone="sky">
                <dl class="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.impersonation.target.name') }}</dt>
                        <dd class="mt-1 text-zinc-950 dark:text-zinc-50">{{ formatEmpty(target.name) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.impersonation.target.email') }}</dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ formatEmpty(target.email) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.impersonation.target.sensitivity') }}
                        </dt>
                        <dd class="mt-1 text-zinc-950 dark:text-zinc-50">{{ sensitivityLabel }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.impersonation.target.public_id') }}</dt>
                        <dd class="mt-1 break-all text-zinc-950 dark:text-zinc-50">{{ target.publicId }}</dd>
                    </div>
                </dl>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.admin.impersonation.form.title')" :icon="IconShieldCheck" tone="amber">
                <AtlasForm :processing="form.processing" @submit="submit">
                    <div class="space-y-4">
                        <FormSelect
                            v-model="form.team_public_id"
                            :label="t('pages.admin.impersonation.form.team')"
                            :options="teams"
                            :error="form.errors.team_public_id"
                        />
                        <FormTextarea
                            v-model="form.reason"
                            :label="t('pages.admin.impersonation.form.reason')"
                            :placeholder="t('pages.admin.impersonation.form.reason_placeholder')"
                            :error="form.errors.reason"
                        />
                        <NoticeBanner
                            v-if="requiresSensitiveOverride"
                            :title="t('pages.admin.impersonation.sensitive.title')"
                            tone="danger"
                        >
                            <div class="space-y-3">
                                <p>{{ t('pages.admin.impersonation.sensitive.body') }}</p>
                                <FormCheckbox
                                    v-model="form.override_sensitive"
                                    :label="t('pages.admin.impersonation.form.override_sensitive')"
                                />
                                <p v-if="form.errors.override_sensitive" class="text-sm font-medium text-rose-700 dark:text-rose-200">
                                    {{ form.errors.override_sensitive }}
                                </p>
                            </div>
                        </NoticeBanner>
                    </div>

                    <FormActions class="mt-5 justify-end">
                        <ActionLink href="/admin/users" tone="neutral">
                            {{ t('modal.cancel') }}
                        </ActionLink>
                        <FormButton type="submit" tone="danger" :icon="IconUserScan" :loading="form.processing">
                            {{ t('pages.admin.impersonation.actions.start') }}
                        </FormButton>
                    </FormActions>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
