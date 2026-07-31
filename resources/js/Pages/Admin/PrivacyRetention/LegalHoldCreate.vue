<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPlus, IconShieldCheck } from '@tabler/icons-vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormDateInput from '../../../Components/Form/FormDateInput.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import { usePrivacyRetentionSubnavigation } from '../../../Composables/usePrivacyRetentionSubnavigation';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

const props = defineProps<{
    formDefaults: {
        subject_type: string;
        subject_identifier: string;
        reason: string;
        expires_on: string;
    };
    subjectTypeOptions: FormSelectOption[];
}>();

const { t } = useTranslator();
const subnavigation = usePrivacyRetentionSubnavigation('/admin/privacy-retention/legal-holds', t);
const form = useForm({
    subject_type: props.formDefaults.subject_type,
    subject_identifier: props.formDefaults.subject_identifier,
    reason: props.formDefaults.reason,
    expires_on: props.formDefaults.expires_on,
});

function createHold(): void {
    form.post('/admin/privacy-retention/legal-holds', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.privacy_retention.legal_holds.create.head_title')" />
    <AdminLayout
        :title="t('pages.admin.privacy_retention.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.privacy_retention.nav.label')"
    >
        <PageStack>
            <div class="flex justify-start">
                <ActionLink href="/admin/privacy-retention/legal-holds" :icon="IconArrowLeft" tone="neutral">
                    {{ t('pages.admin.privacy_retention.legal_holds.actions.back_to_index') }}
                </ActionLink>
            </div>

            <SurfaceCard :title="t('pages.admin.privacy_retention.legal_holds.form.title')" :icon="IconPlus" tone="rose">
                <AtlasForm :processing="form.processing" @submit="createHold">
                    <div class="grid gap-3 md:grid-cols-2">
                        <FormSelect
                            v-model="form.subject_type"
                            :label="t('pages.admin.privacy_retention.legal_holds.form.subject_type')"
                            :options="subjectTypeOptions"
                            :error="form.errors.subject_type"
                        />
                        <FormInput
                            v-model="form.subject_identifier"
                            :label="t('pages.admin.privacy_retention.legal_holds.form.subject_identifier')"
                            :error="form.errors.subject_identifier"
                        />
                        <FormDateInput
                            v-model="form.expires_on"
                            :label="t('pages.admin.privacy_retention.legal_holds.form.expires_on')"
                            :error="form.errors.expires_on"
                        />
                        <div class="hidden md:block" aria-hidden="true"></div>
                        <FormTextarea
                            v-model="form.reason"
                            class="md:col-span-2"
                            :label="t('pages.admin.privacy_retention.legal_holds.form.reason')"
                            :placeholder="t('pages.admin.privacy_retention.legal_holds.form.reason_placeholder')"
                            :error="form.errors.reason"
                        />
                    </div>
                    <div class="mt-5 flex justify-end">
                        <FormButton type="submit" tone="danger" :icon="IconPlus" :loading="form.processing">
                            {{ t('pages.admin.privacy_retention.legal_holds.form.submit') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AdminLayout>
</template>
