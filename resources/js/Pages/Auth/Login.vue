<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormCheckbox from '../../Components/Form/FormCheckbox.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const { t } = useTranslator();

const submit = (): void => {
    form.post('/login', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t('auth.login.head_title')" />
    <AuthLayout :title="t('auth.login.title')" :subtitle="t('auth.login.subtitle')">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <FormInput
                id="email"
                v-model="form.email"
                :label="t('auth.login.email')"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
            />

            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.login.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <FormCheckbox v-model="form.remember" :label="t('auth.login.remember')" />

            <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                {{ form.processing ? t('auth.login.submitting') : t('auth.login.submit') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
