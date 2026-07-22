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

const { t } = useTranslator();
const instructionsOpen = ref(false);
const form = useForm({
    policy: '',
    limiter_key: '',
    reason: '',
});

const columns: DataTableColumn<RateLimitPolicyRow>[] = [
    { key: 'publicId', label: t('pages.admin.rate_limits.public_id'), hidden: true },
    { key: 'policy', label: t('pages.admin.rate_limits.policy') },
    { key: 'maxAttempts', label: t('pages.admin.rate_limits.max_attempts'), format: 'number' },
    { key: 'decaySeconds', label: t('pages.admin.rate_limits.decay_seconds'), format: 'number' },
    { key: 'keyParts', label: t('pages.admin.rate_limits.key_parts') },
    { key: 'progressiveDelays', label: t('pages.admin.rate_limits.progressive_delays'), hidden: true },
    { key: 'temporaryLockSeconds', label: t('pages.admin.rate_limits.temporary_lock_seconds'), format: 'number', hidden: true },
    { key: 'rejections', label: t('pages.admin.rate_limits.rejections'), format: 'number' },
    { key: 'distinctKeys', label: t('pages.admin.rate_limits.distinct_keys'), format: 'number' },
    { key: 'lastRejectedAt', label: t('pages.admin.rate_limits.last_rejected_at'), format: 'datetime', hidden: true },
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
                :title="t('pages.admin.rate_limits.reset_one_counter')"
                :icon="IconLockAccess"
                :subtitle="t('pages.admin.rate_limits.reset_one_counter_subtitle')"
            >
                <template #actions>
                    <Tooltip :text="t('pages.admin.rate_limits.instructions')" placement="top">
                        <IconButton
                            :label="t('pages.admin.rate_limits.instructions')"
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
                    <FormSelect
                        v-model="form.policy"
                        :label="t('pages.admin.rate_limits.policy')"
                        :options="policyOptions"
                        :error="form.errors.policy"
                    />
                    <FormInput
                        v-model="form.limiter_key"
                        :label="t('pages.admin.rate_limits.exact_limiter_key')"
                        :error="form.errors.limiter_key"
                    />
                    <FormInput v-model="form.reason" :label="t('pages.admin.rate_limits.reset_reason')" :error="form.errors.reason" />
                    <FormButton
                        type="submit"
                        tone="danger"
                        class="w-full xl:w-auto"
                        :loading="form.processing"
                        :disabled="!form.policy || !form.limiter_key.trim() || !form.reason.trim()"
                    >
                        {{ t('pages.admin.rate_limits.reset_counter') }}
                    </FormButton>
                </AtlasForm>
            </SurfaceCard>

            <DataTable
                :title="t('pages.admin.rate_limits.policies')"
                :rows="policies"
                :columns="columns"
                row-key="publicId"
                :table="table"
            />
        </PageStack>

        <DialogPanel
            v-model:open="instructionsOpen"
            :title="t('pages.admin.rate_limits.reset_one_counter')"
            :icon="IconInfoCircle"
            :close-label="t('pages.admin.rate_limits.close_instructions')"
        >
            <div class="space-y-3">
                <p>
                    {{ t('pages.admin.rate_limits.instructions_intro') }}
                </p>
                <ol class="list-decimal space-y-2 pl-5">
                    <li>
                        {{ t('pages.admin.rate_limits.instructions_step_policy') }}
                        <span class="font-mono">auth.login</span>.
                    </li>
                    <li>
                        {{ t('pages.admin.rate_limits.instructions_step_key') }}
                        <span class="font-mono">auth.login|user:name@example.test|ip:127.0.0.1</span>.
                    </li>
                    <li>
                        {{ t('pages.admin.rate_limits.instructions_step_reason') }}
                    </li>
                </ol>
                <p>{{ t('pages.admin.rate_limits.instructions_footer') }}</p>
            </div>
        </DialogPanel>
    </AdminLayout>
</template>
