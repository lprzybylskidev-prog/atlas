<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{
                    t('auth.reset_password.email')
                }}</label>
                <div
                    class="mt-2 flex h-11 rounded-lg border border-zinc-300 bg-white shadow-sm transition focus-within:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:focus-within:border-zinc-500"
                >
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="block h-full w-full appearance-none bg-transparent px-3 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-50"
                        :aria-invalid="form.errors.email ? 'true' : 'false'"
                        aria-describedby="email-error"
                    />
                </div>
                <p v-if="form.errors.email" id="email-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{
                    t('auth.reset_password.password')
                }}</label>
                <div
                    class="mt-2 flex h-11 rounded-lg border border-zinc-300 bg-white shadow-sm transition focus-within:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:focus-within:border-zinc-500"
                >
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="block h-full w-full appearance-none bg-transparent px-3 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-50"
                        :aria-invalid="form.errors.password ? 'true' : 'false'"
                        aria-describedby="password-error"
                    />
                </div>
                <p v-if="form.errors.password" id="password-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{
                    t('auth.reset_password.password_confirmation')
                }}</label>
                <div
                    class="mt-2 flex h-11 rounded-lg border border-zinc-300 bg-white shadow-sm transition focus-within:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:focus-within:border-zinc-500"
                >
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="block h-full w-full appearance-none bg-transparent px-3 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-50"
                        :aria-invalid="form.errors.password_confirmation ? 'true' : 'false'"
                        aria-describedby="password-confirmation-error"
                    />
                </div>
                <p
                    v-if="form.errors.password_confirmation"
                    id="password-confirmation-error"
                    class="mt-2 text-sm text-rose-700 dark:text-rose-300"
                >
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

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
