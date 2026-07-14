<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { IconAlertTriangle, IconClockHour4, IconInbox, IconProgressCheck } from '@tabler/icons-vue';

import StatusPill from '../Components/StatusPill.vue';
import AppLayout from '../Layouts/AppLayout.vue';

const cards = [
    { label: 'Kolejka operacji', value: '12', helper: 'Zadania oczekujące na wykonanie', icon: IconProgressCheck, tone: 'info' },
    { label: 'Powiadomienia', value: '4', helper: 'Wymagają przeczytania', icon: IconInbox, tone: 'warning' },
    { label: 'Ostatnie zdarzenia', value: '8', helper: 'Bezpieczeństwo i sesje', icon: IconClockHour4, tone: 'neutral' },
    { label: 'Alerty', value: '1', helper: 'Wymaga weryfikacji admina', icon: IconAlertTriangle, tone: 'warning' },
] as const;
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard operacyjny">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="space-y-4">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Atlas Operations</p>
                            <h2 class="mt-1 text-xl font-semibold text-zinc-950 dark:text-zinc-50">Pierwszy shell aplikacji</h2>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                Ten ekran jest makietą operacyjną pod ocenę layoutu: sidebar, topbar, aktywny zespół, motywy i zachowanie
                                mobilne.
                            </p>
                        </div>
                        <StatusPill tone="success">Ready to review</StatusPill>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in cards"
                        :key="card.label"
                        class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ card.label }}</p>
                                <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ card.value }}</p>
                            </div>
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-lg bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-100"
                            >
                                <component :is="card.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ card.helper }}</p>
                    </article>
                </div>

                <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                        <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Widok modułowy</h2>
                    </div>
                    <div class="grid gap-0 md:grid-cols-3">
                        <div class="border-b border-zinc-200 p-4 dark:border-zinc-800 md:border-b-0 md:border-r">
                            <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Element niezależny</p>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                Każdy panel będzie miał własne loading, empty i error state.
                            </p>
                        </div>
                        <div class="border-b border-zinc-200 p-4 dark:border-zinc-800 md:border-b-0 md:border-r">
                            <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Stały układ</p>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                Brak drag and drop, personalizacji i ukrywania elementów w obecnym zakresie.
                            </p>
                        </div>
                        <div class="p-4">
                            <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">Gotowe pod bramki</p>
                            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                Prawdziwe uprawnienia i module gates podłączymy w późniejszych fazach.
                            </p>
                        </div>
                    </div>
                </section>
            </section>

            <aside class="space-y-4">
                <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Aktywny zespół</h2>
                    <p class="mt-3 rounded-lg bg-teal-50 px-3 py-2 text-sm font-medium text-teal-900 dark:bg-teal-950 dark:text-teal-100">
                        Atlas Operations
                    </p>
                    <p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        Docelowo kontekst zespołu będzie zawsze mocno widoczny, żeby ograniczyć pomyłki operacyjne.
                    </p>
                </section>

                <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Następne kroki UI</h2>
                    <ul class="mt-3 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                        <li>Centralne breadcrumbs z nazwanych tras.</li>
                        <li>Pełny composable-view contract.</li>
                        <li>Shared forms, dialogs, toasts i table wrapper.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
