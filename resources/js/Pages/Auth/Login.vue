<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthLayout from '../../Layouts/AuthLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/login', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Logowanie" />
    <AuthLayout title="Zaloguj się" subtitle="Uzyskaj dostęp do swojego obszaru pracy, aktywnego zespołu oraz narzędzi operacyjnych Atlas.">
        <form class="space-y-5" novalidate @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">Email</label>
                <div class="auth-input-frame mt-2">
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        class="auth-input"
                        :aria-invalid="form.errors.email ? 'true' : 'false'"
                        aria-describedby="email-error"
                    />
                </div>
                <p v-if="form.errors.email" id="email-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">Hasło</label>
                <div class="auth-input-frame mt-2">
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="auth-input"
                        :aria-invalid="form.errors.password ? 'true' : 'false'"
                        aria-describedby="password-error"
                    />
                </div>
                <p v-if="form.errors.password" id="password-error" class="mt-2 text-sm text-rose-700 dark:text-rose-300">
                    {{ form.errors.password }}
                </p>
            </div>

            <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-200">
                <input v-model="form.remember" type="checkbox" class="auth-checkbox" />
                Zapamiętaj mnie
            </label>

            <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-400 dark:text-zinc-950 dark:hover:bg-teal-300"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Logowanie...' : 'Zaloguj' }}
            </button>
        </form>
    </AuthLayout>
</template>
