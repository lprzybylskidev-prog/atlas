<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

const form = useForm({
    password: '',
});

const { t } = useTranslator();

const submit = (): void => {
    form.post('/user/confirm-password', {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="t('auth.confirm_password.head_title')" />
    <AuthLayout :title="t('auth.confirm_password.title')" :subtitle="t('auth.confirm_password.subtitle')">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.confirm_password.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                {{ form.processing ? t('auth.confirm_password.submitting') : t('auth.confirm_password.submit') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
