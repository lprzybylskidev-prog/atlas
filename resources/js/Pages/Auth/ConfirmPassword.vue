<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

const props = withDefaults(
    defineProps<{
        mfaRequired?: boolean;
        context?: 'default' | 'enter' | 'high_risk';
    }>(),
    {
        mfaRequired: false,
        context: 'default',
    },
);

const form = useForm({
    password: '',
    mfa_code: '',
});

const { t } = useTranslator();

const submit = (): void => {
    form.post('/user/confirm-password', {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'mfa_code'),
    });
};

const titleKey =
    props.context === 'high_risk'
        ? 'auth.confirm_password.high_risk.title'
        : props.context === 'enter'
          ? 'auth.confirm_password.admin.title'
          : 'auth.confirm_password.title';
const subtitleKey =
    props.context === 'high_risk'
        ? 'auth.confirm_password.high_risk.subtitle'
        : props.context === 'enter'
          ? 'auth.confirm_password.admin.subtitle'
          : 'auth.confirm_password.subtitle';
</script>

<template>
    <Head :title="t('auth.confirm_password.head_title')" />
    <AuthLayout :title="t(titleKey)" :subtitle="t(subtitleKey)">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.confirm_password.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <FormInput
                v-if="mfaRequired"
                id="mfa_code"
                v-model="form.mfa_code"
                :label="t('auth.confirm_password.mfa_code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                :error="form.errors.mfa_code"
            />

            <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                {{ form.processing ? t('auth.confirm_password.submitting') : t('auth.confirm_password.submit') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
