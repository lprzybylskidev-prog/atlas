<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({
    email: 'atlas@example.test',
    password: 'password',
    remember: true,
});

const submit = (): void => {
    form.post('/login', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Logowanie" />
    <AuthLayout title="Zaloguj się" subtitle="Użyj konta demonstracyjnego, żeby obejrzeć pierwsze layouty aplikacji i panelu admina.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    class="mt-2 block h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-950 shadow-sm transition placeholder:text-zinc-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-50"
                    :aria-invalid="form.errors.email ? 'true' : 'false'"
                    aria-describedby="email-error"
                />
                <p v-if="form.errors.email" id="email-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">Hasło</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="mt-2 block h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-950 shadow-sm transition placeholder:text-zinc-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-50"
                    :aria-invalid="form.errors.password ? 'true' : 'false'"
                    aria-describedby="password-error"
                />
                <p v-if="form.errors.password" id="password-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-200">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="h-4 w-4 rounded border-zinc-300 text-teal-700 focus:ring-teal-600 dark:border-zinc-700 dark:bg-zinc-950"
                />
                Zapamiętaj mnie
            </label>

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-400 dark:text-zinc-950 dark:hover:bg-teal-300"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Logowanie...' : 'Zaloguj' }}
            </button>

            <div
                class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs leading-5 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300"
            >
                Demo: <span class="font-medium">atlas@example.test</span> / <span class="font-medium">password</span>
            </div>
        </form>
    </AuthLayout>
</template>
