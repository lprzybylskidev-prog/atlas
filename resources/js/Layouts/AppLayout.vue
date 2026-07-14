<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

import MobileNavigation from '../Components/MobileNavigation.vue';
import Sidebar from '../Components/Sidebar.vue';
import TopBar from '../Components/TopBar.vue';

defineProps<{
    title: string;
    uiLocale?: string;
}>();

const page = usePage();
const mobileMenuOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
        <div class="flex min-h-screen">
            <Sidebar :current-path="page.url" :ui-locale="uiLocale" />
            <div class="flex min-w-0 flex-1 flex-col">
                <TopBar :title="title" :ui-locale="uiLocale" @open-mobile-menu="mobileMenuOpen = true" />
                <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>
        <MobileNavigation :open="mobileMenuOpen" :ui-locale="uiLocale" @close="mobileMenuOpen = false" />
    </div>
</template>
