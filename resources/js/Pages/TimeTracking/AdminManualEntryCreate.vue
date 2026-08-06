<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconBriefcase, IconClockHour4, IconDatabase, IconFilePencil, IconPlayerPause } from '@tabler/icons-vue';
import { computed, watch } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import FormActions from '../../Components/FormActions.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { ShellSubnavigationItem } from '../../Types/navigation';

interface TeamOption {
    publicId: string;
    name: string;
    trackedUsers: number;
}

interface UserOption {
    publicId: string;
    name: string;
    email: string;
}

interface OtherWorkCategoryOption {
    key: string;
    labelPl: string;
    labelEn: string;
    teamPublicId: string;
}

const props = defineProps<{
    teamOptions: TeamOption[];
    userOptionsByTeam: Record<string, UserOption[]>;
    otherWorkCategoryOptionsByTeam: Record<string, OtherWorkCategoryOption[]>;
    defaultTeamPublicId: string | null;
}>();

const { locale, t } = useTranslator();
const form = useForm({
    entry_kind: 'work_session',
    team_public_id: props.defaultTeamPublicId ?? '',
    user_public_id: '',
    category_key: '',
    final_started_at: '',
    final_ended_at: '',
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
        active: false,
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
        active: true,
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
const usersForTeam = computed<UserOption[]>(() => props.userOptionsByTeam[form.team_public_id] ?? []);
const userOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_manual_entry.user_placeholder') },
    ...usersForTeam.value.map((user) => ({
        value: user.publicId,
        label: user.email === '' ? user.name : `${user.name} (${user.email})`,
    })),
]);
const entryKindOptions = computed<FormSelectOption[]>(() => [
    { value: 'work_session', label: t('pages.time_tracking.admin_manual_entry.entry_kinds.work_session') },
    { value: 'break', label: t('pages.time_tracking.admin_manual_entry.entry_kinds.break') },
    { value: 'other_work', label: t('pages.time_tracking.admin_manual_entry.entry_kinds.other_work') },
]);
const otherWorkCategoriesForTeam = computed<OtherWorkCategoryOption[]>(
    () => props.otherWorkCategoryOptionsByTeam[form.team_public_id] ?? [],
);
const otherWorkCategoryOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_manual_entry.category_placeholder') },
    ...otherWorkCategoriesForTeam.value.map((category) => ({
        value: category.key,
        label: locale.value === 'pl' ? category.labelPl : category.labelEn,
    })),
]);

watch(
    () => form.team_public_id,
    () => {
        if (form.user_public_id !== '' && !usersForTeam.value.some((user) => user.publicId === form.user_public_id)) {
            form.user_public_id = '';
        }

        if (form.category_key !== '' && !otherWorkCategoriesForTeam.value.some((category) => category.key === form.category_key)) {
            form.category_key = '';
        }
    },
);

watch(
    () => form.entry_kind,
    () => {
        if (form.entry_kind !== 'other_work') {
            form.category_key = '';
        }
    },
);

function submit(): void {
    form.post('/admin/work-time/corrections/manual-entry', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.time_tracking.admin_manual_entry.head_title')" />
    <AppLayout
        mode="admin"
        :title="t('pages.time_tracking.admin_manual_entry.title')"
        :title-icon="IconFilePencil"
        :subnavigation="subnavigation"
        :subnavigation-label="t('navigation.group.work_time')"
    >
        <PageStack>
            <SurfaceCard :title="t('pages.time_tracking.admin_manual_entry.form_title')" :icon="IconFilePencil" tone="teal">
                <AtlasForm :processing="form.processing" @submit="submit">
                    <div class="grid gap-3 md:grid-cols-2">
                        <FormSelect
                            v-model="form.entry_kind"
                            class="md:col-span-2"
                            :label="t('pages.time_tracking.admin_manual_entry.entry_kind')"
                            :options="entryKindOptions"
                            :error="form.errors.entry_kind"
                        />
                        <FormSelect
                            v-model="form.team_public_id"
                            :label="t('pages.time_tracking.admin_operations.filters.team')"
                            :options="teamOptions"
                            :error="form.errors.team_public_id"
                        />
                        <FormSelect
                            v-model="form.user_public_id"
                            :label="t('pages.time_tracking.admin_operations.manual.user')"
                            :options="userOptions"
                            :error="form.errors.user_public_id"
                        />
                        <FormSelect
                            v-if="form.entry_kind === 'other_work'"
                            v-model="form.category_key"
                            class="md:col-span-2"
                            :label="t('pages.time_tracking.admin_operations.table.category')"
                            :options="otherWorkCategoryOptions"
                            :error="form.errors.category_key"
                        />
                        <FormDateTimeInput
                            v-model="form.final_started_at"
                            :label="t('pages.time_tracking.admin_operations.manual.started_at')"
                            :error="form.errors.final_started_at"
                        />
                        <FormDateTimeInput
                            v-model="form.final_ended_at"
                            :label="t('pages.time_tracking.admin_operations.manual.ended_at')"
                            :error="form.errors.final_ended_at"
                        />
                        <FormTextarea
                            v-model="form.reason"
                            class="md:col-span-2"
                            :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                            :error="form.errors.reason"
                        />
                    </div>
                    <FormActions class="mt-5 justify-end">
                        <ActionLink href="/admin/work-time/corrections">
                            {{ t('modal.cancel') }}
                        </ActionLink>
                        <FormButton type="submit" :icon="IconFilePencil" :loading="form.processing">
                            {{ t('pages.time_tracking.admin_operations.actions.manual_entry') }}
                        </FormButton>
                    </FormActions>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
