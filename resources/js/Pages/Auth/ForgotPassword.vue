<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import ActionLink from '../../Components/ActionLink.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import NoticeBanner from '../../Components/NoticeBanner.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

defineProps<{ status?: string | null }>();

const { t } = useTranslator();
const form = useForm({ email: '' });

function submit(): void {
    form.post('/forgot-password', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('auth.forgot_password.head_title')" />
    <AuthLayout :title="t('auth.forgot_password.title')" :subtitle="t('auth.forgot_password.subtitle')">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <NoticeBanner v-if="status" :title="t('auth.forgot_password.sent_title')" tone="success" role="status">
                {{ t('auth.forgot_password.sent') }}
            </NoticeBanner>
            <FormInput
                v-model="form.email"
                :label="t('auth.forgot_password.email')"
                type="email"
                autocomplete="email"
                :error="form.errors.email"
            />
            <div class="space-y-3">
                <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                    {{ form.processing ? t('auth.forgot_password.submitting') : t('auth.forgot_password.submit') }}
                </FormButton>
                <ActionLink href="/login" class="w-full justify-center">{{ t('auth.forgot_password.back_to_login') }}</ActionLink>
            </div>
        </AtlasForm>
    </AuthLayout>
</template>
