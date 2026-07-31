<script setup lang="ts">
import { IconCheck, IconClipboard, IconTextWrap, IconTextWrapDisabled } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import Tooltip from './Tooltip.vue';

interface CodeLine {
    key: string;
    number: number;
    text: string;
    kind: 'error' | 'frame' | 'heading' | 'json-key' | 'plain';
}

const props = withDefaults(
    defineProps<{
        title?: string;
        content: string;
        language?: 'json' | 'log' | 'stack' | 'text' | 'toml';
        maxHeight?: string;
        copyLabel?: string;
        copiedLabel?: string;
        wrapLabel?: string;
        unwrapLabel?: string;
        showLineNumbers?: boolean;
    }>(),
    {
        title: undefined,
        language: 'text',
        maxHeight: 'max-h-96',
        copyLabel: 'Copy',
        copiedLabel: 'Copied',
        wrapLabel: 'Wrap lines',
        unwrapLabel: 'Do not wrap lines',
        showLineNumbers: true,
    },
);

const copied = ref(false);
const wrapLines = ref(props.language !== 'json');
const lines = computed<CodeLine[]>(() =>
    props.content.split(/\r?\n/).map((line, index) => ({
        key: `${index}-${line}`,
        number: index + 1,
        text: line,
        kind: lineKind(line),
    })),
);

function lineKind(line: string): CodeLine['kind'] {
    if (props.language === 'json' && /^\s*"[^"]+":/.test(line)) {
        return 'json-key';
    }

    if ((props.language === 'log' || props.language === 'stack') && /\b(error|exception|failed|failure)\b/i.test(line)) {
        return 'error';
    }

    if (props.language === 'stack' && line.startsWith('#')) {
        return 'frame';
    }

    if ((props.language === 'log' || props.language === 'stack') && line.startsWith('[') && line.endsWith(']')) {
        return 'heading';
    }

    return 'plain';
}

async function copyContent(): Promise<void> {
    if (!navigator.clipboard) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.content);
    } catch {
        return;
    }

    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1400);
}
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
        <div
            v-if="title || content"
            class="flex min-w-0 items-center justify-between gap-3 border-b border-zinc-200 bg-white px-3 py-2 dark:border-zinc-800 dark:bg-zinc-950"
        >
            <p v-if="title" class="truncate text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ title }}</p>
            <div class="ml-auto flex shrink-0 items-center gap-1">
                <Tooltip :text="wrapLines ? unwrapLabel : wrapLabel" placement="top">
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                        :aria-label="wrapLines ? unwrapLabel : wrapLabel"
                        @click="wrapLines = !wrapLines"
                    >
                        <IconTextWrap v-if="!wrapLines" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                        <IconTextWrapDisabled v-else aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </Tooltip>
                <Tooltip :text="copied ? copiedLabel : copyLabel" placement="top">
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                        :aria-label="copied ? copiedLabel : copyLabel"
                        @click="copyContent"
                    >
                        <IconCheck
                            v-if="copied"
                            aria-hidden="true"
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-300"
                            :stroke-width="1.8"
                        />
                        <IconClipboard v-else aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </Tooltip>
            </div>
        </div>
        <div class="overflow-auto py-4 font-mono text-xs leading-5 text-zinc-800 dark:text-zinc-100" :class="[maxHeight, title ? '' : '']">
            <div
                v-for="line in lines"
                :key="line.key"
                class="grid border-b border-zinc-200/60 last:border-b-0 dark:border-zinc-800/70"
                :class="[
                    wrapLines ? 'w-full min-w-0' : 'min-w-max',
                    showLineNumbers ? 'grid-cols-[3.5rem_1fr]' : 'grid-cols-1',
                    {
                        'bg-rose-50/70 dark:bg-rose-950/20': line.kind === 'error',
                        'bg-teal-50/70 dark:bg-teal-950/20': line.kind === 'heading',
                    },
                ]"
            >
                <span
                    v-if="showLineNumbers"
                    class="select-none border-r border-zinc-200 px-3 py-1 text-right text-zinc-400 dark:border-zinc-800"
                >
                    {{ line.number }}
                </span>
                <span v-else class="hidden" />
                <span
                    class="min-w-0 px-3 py-1"
                    :class="[
                        wrapLines ? 'whitespace-pre-wrap break-words' : 'whitespace-pre',
                        {
                            'font-semibold text-teal-700 dark:text-teal-300': line.kind === 'heading',
                            'pl-6 text-zinc-600 dark:text-zinc-300': line.kind === 'frame',
                            'text-rose-800 dark:text-rose-200': line.kind === 'error',
                            'text-sky-800 dark:text-sky-200': line.kind === 'json-key',
                        },
                    ]"
                >
                    {{ line.text === '' ? ' ' : line.text }}
                </span>
            </div>
        </div>
    </section>
</template>
