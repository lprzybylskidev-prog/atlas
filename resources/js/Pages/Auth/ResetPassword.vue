<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
        <form class="space-y-5" novalidate @submit.prevent="submit">
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

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-400 dark:text-zinc-950 dark:hover:bg-teal-300"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.reset_password.submitting') : t('auth.reset_password.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
