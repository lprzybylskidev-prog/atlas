<script setup lang="ts">
import { computed } from 'vue';

interface CodeLine {
    key: string;
    text: string;
    kind: 'frame' | 'heading' | 'plain';
}

const props = withDefaults(
    defineProps<{
        title?: string;
        content: string;
        language?: 'json' | 'log' | 'stack' | 'text' | 'toml';
        maxHeight?: string;
    }>(),
    {
        title: undefined,
        language: 'text',
        maxHeight: 'max-h-96',
    },
);

const lines = computed<CodeLine[]>(() =>
    props.content.split(/\r?\n/).map((line, index) => ({
        key: `${index}-${line}`,
        text: line,
        kind: lineKind(line),
    })),
);

function lineKind(line: string): CodeLine['kind'] {
    if (props.language === 'stack' && line.startsWith('#')) {
        return 'frame';
    }

    if ((props.language === 'log' || props.language === 'stack') && line.startsWith('[') && line.endsWith(']')) {
        return 'heading';
    }

    return 'plain';
}
</script>

<template>
    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900">
        <p v-if="title" class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ title }}</p>
        <div class="overflow-auto font-mono text-xs leading-5 text-zinc-800 dark:text-zinc-100" :class="[maxHeight, title ? 'mt-2' : '']">
            <div
                v-for="line in lines"
                :key="line.key"
                class="whitespace-pre-wrap break-words"
                :class="{
                    'mt-2 font-semibold text-teal-700 dark:text-teal-300': line.kind === 'heading',
                    'pl-3 text-zinc-600 dark:text-zinc-300': line.kind === 'frame',
                }"
            >
                {{ line.text }}
            </div>
        </div>
    </section>
</template>
