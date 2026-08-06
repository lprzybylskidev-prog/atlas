<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconBriefcase, IconClockHour4, IconDatabase, IconFilePencil, IconPlayerPause } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import FormActions from '../../Components/FormActions.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormCheckbox from '../../Components/Form/FormCheckbox.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { ShellSubnavigationItem } from '../../Types/navigation';

interface TeamOption {
    publicId: string;
    name: string;
    trackedUsers: number;
}

const props = defineProps<{
    surface?: 'admin' | 'manager';
    teamOptions: TeamOption[];
    defaultTeamPublicId: string | null;
}>();

const { t } = useTranslator();
const surface = computed(() => props.surface ?? 'admin');
const isManagerSurface = computed(() => surface.value === 'manager');
const form = useForm({
    team_public_id: props.defaultTeamPublicId ?? '',
    category_key: '',
    label_pl: '',
    label_en: '',
    description_pl: '',
    description_en: '',
    requires_comment: false,
    auto_approval_enabled: false,
    reason: '',
});
const subnavigation = computed<ShellSubnavigationItem[]>(() => [
    {
        key: 'daily',
        label: t('navigation.work_time_daily'),
        href: '/admin/work-time/summary',
        icon: IconClockHour4,
        active: false,
    },
    {
        key: 'other_work',
        label: t('navigation.work_time_other_work'),
        href: '/admin/work-time/other-work',
        icon: IconBriefcase,
        active: true,
    },
    {
        key: 'breaks',
        label: t('navigation.work_time_breaks'),
        href: '/admin/work-time/breaks',
        icon: IconPlayerPause,
        active: false,
    },
    {
        key: 'corrections',
        label: t('navigation.work_time_corrections'),
        href: '/admin/work-time/corrections',
        icon: IconFilePencil,
        active: false,
    },
    {
        key: 'work_sessions',
        label: t('navigation.work_time_sessions'),
        href: '/admin/work-time/work-sessions',
        icon: IconDatabase,
        active: false,
    },
]);
const teamOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.team_placeholder') },
    ...props.teamOptions.map((team) => ({
        value: team.publicId,
        label: t('pages.time_tracking.admin_operations.filters.team_option', { team: team.name, count: team.trackedUsers }),
    })),
]);

function submit(): void {
    form.post(`${basePath()}/other-work/categories`, { preserveScroll: true });
}

function basePath(): string {
    return isManagerSurface.value ? '/manager/work-time' : '/admin/work-time';
}
</script>

<template>
    <Head :title="t('pages.time_tracking.admin_categories.create.head_title')" />
    <AppLayout
        :mode="surface"
        :title="t('pages.time_tracking.admin_categories.create.title')"
        :title-icon="IconBriefcase"
        :subnavigation="isManagerSurface ? [] : subnavigation"
        :subnavigation-label="t('navigation.group.work_time')"
    >
        <PageStack>
            <SurfaceCard :title="t('pages.time_tracking.admin_categories.form.title')" :icon="IconBriefcase" tone="teal">
                <AtlasForm :processing="form.processing" @submit="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <FormSelect
                            v-model="form.team_public_id"
                            :label="t('pages.time_tracking.admin_operations.filters.team')"
                            :options="teamOptions"
                            :error="form.errors.team_public_id"
                        />
                        <FormInput
                            v-model="form.category_key"
                            :label="t('pages.time_tracking.admin_operations.category.key')"
                            :error="form.errors.category_key"
                        />
                        <FormInput
                            v-model="form.label_pl"
                            :label="t('pages.time_tracking.admin_operations.category.label_pl')"
                            :error="form.errors.label_pl"
                        />
                        <FormInput
                            v-model="form.label_en"
                            :label="t('pages.time_tracking.admin_operations.category.label_en')"
                            :error="form.errors.label_en"
                        />
                        <FormTextarea
                            v-model="form.description_pl"
                            :label="t('pages.time_tracking.admin_operations.category.description_pl')"
                            :error="form.errors.description_pl"
                        />
                        <FormTextarea
                            v-model="form.description_en"
                            :label="t('pages.time_tracking.admin_operations.category.description_en')"
                            :error="form.errors.description_en"
                        />
                        <FormTextarea
                            v-model="form.reason"
                            :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                            :error="form.errors.reason"
                        />
                        <div class="grid content-start gap-3">
                            <FormCheckbox
                                v-model="form.requires_comment"
                                :label="t('pages.time_tracking.admin_operations.category.requires_comment')"
                            />
                            <FormCheckbox
                                v-model="form.auto_approval_enabled"
                                :label="t('pages.time_tracking.admin_operations.category.auto_approval')"
                            />
                        </div>
                    </div>
                    <FormActions class="mt-5 justify-end">
                        <ActionLink :href="`${basePath()}/other-work/categories`">
                            {{ t('modal.cancel') }}
                        </ActionLink>
                        <FormButton type="submit" :icon="IconBriefcase" :loading="form.processing">
                            {{ t('pages.time_tracking.admin_operations.category.save') }}
                        </FormButton>
                    </FormActions>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
