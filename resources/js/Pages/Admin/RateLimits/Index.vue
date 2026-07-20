<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconInfoCircle, IconLockAccess, IconX } from '@tabler/icons-vue';
import { nextTick, ref } from 'vue';

import CardHeader from '../../../Components/CardHeader.vue';
import DataTable from '../../../Components/DataTable.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import IconButton from '../../../Components/IconButton.vue';
import Tooltip from '../../../Components/Tooltip.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';

interface RateLimitPolicyRow extends Record<string, unknown> {
    publicId: string;
    policy: string;
    maxAttempts: number;
    decaySeconds: number;
    keyParts: string;
    progressiveDelays: string;
    temporaryLockSeconds: number | null;
    rejections: number;
    distinctKeys: number;
    lastRejectedAt: string | null;
}

interface PolicyOption {
    value: string;
    label: string;
}

defineProps<{
    policies: RateLimitPolicyRow[];
    table: DataTableMeta;
    policyOptions: PolicyOption[];
}>();

const { t } = useTranslator('en');
const instructionsOpen = ref(false);
const instructionsDialog = ref<HTMLElement | null>(null);
const instructionsTrigger = ref<HTMLElement | null>(null);
const form = useForm({
    policy: '',
    limiter_key: '',
    reason: '',
});

const columns: DataTableColumn<RateLimitPolicyRow>[] = [
    { key: 'publicId', label: 'Public ID', hidden: true },
    { key: 'policy', label: 'Policy' },
    { key: 'maxAttempts', label: 'Max attempts', format: 'number' },
    { key: 'decaySeconds', label: 'Decay seconds', format: 'number' },
    { key: 'keyParts', label: 'Key parts' },
    { key: 'progressiveDelays', label: 'Progressive delays', hidden: true },
    { key: 'temporaryLockSeconds', label: 'Temporary lock seconds', format: 'number', hidden: true },
    { key: 'rejections', label: 'Rejections', format: 'number' },
    { key: 'distinctKeys', label: 'Distinct keys', format: 'number' },
    { key: 'lastRejectedAt', label: 'Last rejected at', format: 'datetime', hidden: true },
];

function resetCounter(): void {
    form.post('/admin/rate-limits/reset', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('limiter_key', 'reason');
        },
    });
}

function openInstructions(event: MouseEvent): void {
    instructionsTrigger.value = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
    instructionsOpen.value = true;

    void nextTick(() => instructionsDialog.value?.focus());
}

function closeInstructions(): void {
    instructionsOpen.value = false;
    instructionsTrigger.value?.focus();
    instructionsTrigger.value = null;
}

function handleInstructionsKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeInstructions();
    }
}
</script>

<template>
    <Head :title="t('pages.admin.rate_limits.head_title')" />
    <AdminLayout :title="t('pages.admin.rate_limits.title')" :title-icon="IconLockAccess">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <CardHeader
                        title="Reset one counter"
                        :icon="IconLockAccess"
                        subtitle="Enter the exact limiter key produced by the policy key parts. Thresholds remain read-only and cannot be edited here."
                    />
                    <Tooltip text="How to reset a counter" placement="top">
                        <IconButton
                            label="How to reset a counter"
                            :icon="IconInfoCircle"
                            class="h-9 w-9 shrink-0"
                            @click="openInstructions"
                        />
                    </Tooltip>
                </div>

                <AtlasForm
                    class="grid gap-4 xl:grid-cols-[minmax(12rem,0.8fr)_minmax(0,1.2fr)_minmax(0,1.6fr)_auto] xl:items-end"
                    :processing="form.processing"
                    @submit="resetCounter"
                >
                    <FormSelect v-model="form.policy" label="Policy" :options="policyOptions" :error="form.errors.policy" />
                    <FormInput v-model="form.limiter_key" label="Exact limiter key" :error="form.errors.limiter_key" />
                    <FormInput v-model="form.reason" label="Reset reason" :error="form.errors.reason" />
                    <FormButton
                        type="submit"
                        tone="danger"
                        class="w-full xl:w-auto"
                        :loading="form.processing"
                        :disabled="!form.policy || !form.limiter_key.trim() || !form.reason.trim()"
                    >
                        Reset counter
                    </FormButton>
                </AtlasForm>
            </section>

            <DataTable title="Rate-limit policies" :rows="policies" :columns="columns" row-key="publicId" :table="table" ui-locale="en" />
        </section>

        <Teleport to="body">
            <div v-if="instructionsOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="presentation">
                <button
                    type="button"
                    class="absolute inset-0 cursor-default bg-zinc-950/60"
                    aria-label="Close instructions"
                    @click="closeInstructions"
                />
                <section
                    ref="instructionsDialog"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="rate-limit-reset-instructions-title"
                    tabindex="-1"
                    class="relative w-full max-w-xl rounded-lg border border-zinc-200 bg-white p-5 shadow-xl outline-none dark:border-zinc-800 dark:bg-zinc-950"
                    @keydown="handleInstructionsKeydown"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300"
                        >
                            <IconInfoCircle aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 id="rate-limit-reset-instructions-title" class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                                Reset one rate-limit counter
                            </h2>
                            <div class="mt-3 space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                                <p>
                                    Use this only after verifying that a legitimate user, IP address, API client, or team operation was
                                    blocked by a rate limit.
                                </p>
                                <ol class="list-decimal space-y-2 pl-5">
                                    <li>Select the policy that owns the counter, such as <span class="font-mono">auth.login</span>.</li>
                                    <li>
                                        Enter the exact limiter key generated from that policy key, for example
                                        <span class="font-mono">auth.login|user:name@example.test|ip:127.0.0.1</span>.
                                    </li>
                                    <li>
                                        Provide a concrete operational reason. The reset is recorded in security audit with the policy, key,
                                        actor, reason, and correlation ID.
                                    </li>
                                </ol>
                                <p>
                                    Resetting a counter does not edit thresholds, add policies, remove policies, or disable rate limiting.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-900 dark:hover:text-zinc-200"
                            aria-label="Close instructions"
                            @click="closeInstructions"
                        >
                            <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </AdminLayout>
</template>
