<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const { t } = useTranslator();

const submit = (): void => {
    form.post('/reset-password', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('auth.reset_password.head_title')" />
    <AuthLayout :title="t('auth.reset_password.title')" :subtitle="t('auth.reset_password.subtitle')">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <FormInput
                id="email"
                v-model="form.email"
                :label="t('auth.reset_password.email')"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
            />

            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.reset_password.password')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
            />

            <FormInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                :label="t('auth.reset_password.password_confirmation')"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            />

            <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                {{ form.processing ? t('auth.reset_password.submitting') : t('auth.reset_password.submit') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
