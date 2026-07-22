<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconInfoCircle, IconLockAccess } from '@tabler/icons-vue';
import { ref } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import IconButton from '../../../Components/IconButton.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
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

function openInstructions(): void {
    instructionsOpen.value = true;
}
</script>

<template>
    <Head :title="t('pages.admin.rate_limits.head_title')" />
    <AdminLayout :title="t('pages.admin.rate_limits.title')" :title-icon="IconLockAccess">
        <PageStack>
            <SurfaceCard
                title="Reset one counter"
                :icon="IconLockAccess"
                subtitle="Enter the exact limiter key produced by the policy key parts. Thresholds remain read-only and cannot be edited here."
            >
                <template #actions>
                    <Tooltip text="How to reset a counter" placement="top">
                        <IconButton
                            label="How to reset a counter"
                            :icon="IconInfoCircle"
                            class="h-9 w-9 shrink-0"
                            @click="openInstructions"
                        />
                    </Tooltip>
                </template>

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
            </SurfaceCard>

            <DataTable title="Rate-limit policies" :rows="policies" :columns="columns" row-key="publicId" :table="table" ui-locale="en" />
        </PageStack>

        <DialogPanel
            v-model:open="instructionsOpen"
            title="Reset one rate-limit counter"
            :icon="IconInfoCircle"
            close-label="Close instructions"
        >
            <div class="space-y-3">
                <p>
                    Use this only after verifying that a legitimate user, IP address, API client, or team operation was blocked by a rate
                    limit.
                </p>
                <ol class="list-decimal space-y-2 pl-5">
                    <li>Select the policy that owns the counter, such as <span class="font-mono">auth.login</span>.</li>
                    <li>
                        Enter the exact limiter key generated from that policy key, for example
                        <span class="font-mono">auth.login|user:name@example.test|ip:127.0.0.1</span>.
                    </li>
                    <li>
                        Provide a concrete operational reason. The reset is recorded in security audit with the policy, key, actor, reason,
                        and correlation ID.
                    </li>
                </ol>
                <p>Resetting a counter does not edit thresholds, add policies, remove policies, or disable rate limiting.</p>
            </div>
        </DialogPanel>
    </AdminLayout>
</template>
