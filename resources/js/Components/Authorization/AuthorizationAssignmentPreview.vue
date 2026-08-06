<script setup lang="ts">
defineProps<{
    title: string;
    rolesLabel: string;
    directPermissionsLabel: string;
    effectivePermissionsLabel: string;
    roles: string;
    directPermissions: string;
    effectivePermissions: string;
}>();
</script>

<template>
    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
        <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
            {{ title }}
        </h3>
        <div class="mt-3 grid gap-4 xl:grid-cols-3">
            <div>
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                    {{ rolesLabel }}
                </p>
                <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                    {{ roles }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                    {{ directPermissionsLabel }}
                </p>
                <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                    {{ directPermissions }}
                </p>
            </div>
            <div>
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                    {{ effectivePermissionsLabel }}
                </p>
                <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                    {{ effectivePermissions }}
                </p>
            </div>
        </div>
    </section>
</template>
