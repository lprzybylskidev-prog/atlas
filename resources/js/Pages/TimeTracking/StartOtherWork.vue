<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconBriefcase, IconPlayerPlay } from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

interface OtherWorkCategory {
    value: string;
    label: string;
    description: string | null;
    requiresComment: boolean;
    autoApprovalEnabled: boolean;
}

const props = defineProps<{
    categories: OtherWorkCategory[];
}>();

const { t } = useTranslator();
const form = useForm({
    category_key: '',
    description: '',
});

const categoryOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.other_work_start.category_none') },
    ...props.categories.map((category) => ({
        value: category.value,
        label: category.label,
        description: category.description ?? undefined,
        meta: [
            category.autoApprovalEnabled
                ? t('pages.time_tracking.other_work_start.auto_approved')
                : t('pages.time_tracking.other_work_start.manager_review'),
            ...(category.requiresComment ? [t('pages.time_tracking.other_work_start.comment_required')] : []),
        ],
    })),
]);

function submit(): void {
    form.post('/user/work-time/other-work/start', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.time_tracking.other_work_start.head_title')" />
    <AuthLayout
        :title="t('pages.time_tracking.other_work_start.title')"
        :subtitle="t('pages.time_tracking.other_work_start.notice_body')"
        :frame-content="false"
        content-size="xl"
    >
        <PageStack>
            <SurfaceCard class="max-w-2xl" :title="t('pages.time_tracking.other_work_start.form_title')" :icon="IconBriefcase" tone="sky">
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                    <FormSelect
                        v-model="form.category_key"
                        :label="t('pages.time_tracking.other_work_start.category')"
                        :options="categoryOptions"
                        :error="form.errors.category_key"
                    />

                    <FormTextarea
                        id="other-work-start-description"
                        v-model="form.description"
                        :label="t('pages.time_tracking.other_work_start.description')"
                        :error="form.errors.description"
                        :rows="6"
                    />

                    <FormButton type="submit" class="w-full sm:w-auto" :loading="form.processing" :icon="IconPlayerPlay">
                        {{
                            form.processing
                                ? t('pages.time_tracking.other_work_start.submitting')
                                : t('pages.time_tracking.other_work_start.submit')
                        }}
                    </FormButton>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AuthLayout>
</template>
