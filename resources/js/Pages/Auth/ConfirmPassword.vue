<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.confirm_password.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-400 dark:text-zinc-950 dark:hover:bg-teal-300"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.confirm_password.submitting') : t('auth.confirm_password.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
