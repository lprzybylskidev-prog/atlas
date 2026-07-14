<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ t('auth.login.email') }}</label>
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
                <label for="password" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ t('auth.login.password') }}</label>
                <div
                    class="mt-2 flex h-11 rounded-lg border border-zinc-300 bg-white shadow-sm transition focus-within:border-zinc-500 dark:border-zinc-700 dark:bg-zinc-950 dark:focus-within:border-zinc-500"
                >
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="block h-full w-full appearance-none bg-transparent px-3 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 dark:text-zinc-50"
                        :aria-invalid="form.errors.password ? 'true' : 'false'"
                        aria-describedby="password-error"
                    />
                </div>
                <p v-if="form.errors.password" id="password-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-200">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 accent-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 dark:border-zinc-700 dark:bg-zinc-950"
                />
                {{ t('auth.login.remember') }}
            </label>

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-400 dark:text-zinc-950 dark:hover:bg-teal-300"
                :disabled="form.processing"
            >
                {{ form.processing ? t('auth.login.submitting') : t('auth.login.submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
