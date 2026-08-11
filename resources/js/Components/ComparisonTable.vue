<script setup lang="ts" generic="Row extends Record<string, unknown>">
export interface ComparisonTableColumn<Row> {
    key: keyof Row & string;
    label: string;
}

defineProps<{
    columns: ComparisonTableColumn<Row>[];
    rows: Row[];
    rowKey: keyof Row & string;
}>();
</script>

<template>
    <div class="overflow-x-auto rounded-md border border-zinc-200 dark:border-zinc-800">
        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
            <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th v-for="column in columns" :key="column.key" scope="col" class="px-3 py-2">
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                <tr v-for="row in rows" :key="String(row[rowKey])">
                    <td v-for="(column, index) in columns" :key="column.key" class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                        <span :class="index === 0 ? 'font-medium text-zinc-950 dark:text-zinc-50' : ''">
                            {{ row[column.key] }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
