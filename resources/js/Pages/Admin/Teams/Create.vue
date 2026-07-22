<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPlus, IconPuzzle, IconTrash, IconUsersGroup } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import CheckboxList from '../../../Components/CheckboxList.vue';
import FormActions from '../../../Components/FormActions.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import UiState from '../../../Components/UiState.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

const props = defineProps<{
    userOptions: FormSelectOption[];
    roleOptions: string[];
    permissionOptions: string[];
    moduleOptions: {
        moduleKey: string;
        category: string;
        supportsTeamActivation: boolean;
        readOnly: boolean;
    }[];
}>();
const { t } = useTranslator('en');
const form = useForm({
    name: '',
    user_assignments: [] as {
        user_public_id: string;
        role_names: string[];
        direct_permission_names: string[];
    }[],
    module_overrides: [] as {
        module_key: string;
        enabled: boolean;
        reason: string;
    }[],
});

type UserAssignmentForm = (typeof form.user_assignments)[number];

const canAddUserAssignment = computed(() => form.user_assignments.length < props.userOptions.length);
const assignableModules = computed(() => props.moduleOptions.filter((module) => module.supportsTeamActivation && !module.readOnly));
const canAddModuleOverride = computed(() => form.module_overrides.length < assignableModules.value.length);
const canAdd = computed(() => canAddUserAssignment.value);

function userOptionsForAssignment(assignment: UserAssignmentForm): FormSelectOption[] {
    const selectedUserIds = new Set(
        form.user_assignments
            .filter((candidate) => candidate !== assignment)
            .map((candidate) => candidate.user_public_id)
            .filter((userPublicId) => userPublicId !== ''),
    );

    return [
        { value: '', label: props.userOptions.length === 0 ? 'No users available' : 'Select user' },
        ...props.userOptions.filter((user) => !selectedUserIds.has(String(user.value))),
    ];
}

function addUserAssignment(): void {
    if (!canAddUserAssignment.value) {
        return;
    }

    form.user_assignments.push({
        user_public_id: '',
        role_names: [],
        direct_permission_names: [],
    });
}
const add = addUserAssignment;

function removeUserAssignment(index: number): void {
    form.user_assignments.splice(index, 1);
}

function moduleOptionsForOverride(override: (typeof form.module_overrides)[number]): FormSelectOption[] {
    const selectedModuleKeys = new Set(
        form.module_overrides
            .filter((candidate) => candidate !== override)
            .map((candidate) => candidate.module_key)
            .filter((moduleKey) => moduleKey !== ''),
    );

    return [
        { value: '', label: assignableModules.value.length === 0 ? 'No team-activatable modules' : 'Select module' },
        ...assignableModules.value
            .filter((module) => !selectedModuleKeys.has(module.moduleKey))
            .map((module) => ({ value: module.moduleKey, label: `${module.moduleKey} · ${module.category}` })),
    ];
}

function addModuleOverride(): void {
    if (!canAddModuleOverride.value) {
        return;
    }

    form.module_overrides.push({
        module_key: '',
        enabled: true,
        reason: '',
    });
}

function removeModuleOverride(index: number): void {
    form.module_overrides.splice(index, 1);
}

function changeAssignedUser(assignment: UserAssignmentForm): void {
    assignment.role_names = [];
    assignment.direct_permission_names = [];
}

function submit(): void {
    form.post('/admin/teams', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.create.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.create.title')" :title-icon="IconUsersGroup">
        <PageStack>
            <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
                <SurfaceCard title="Team identity" :icon="IconUsersGroup">
                    <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
                </SurfaceCard>

                <SurfaceCard
                    title="Initial members"
                    :icon="IconUsersGroup"
                    subtitle="Assign users, roles, and direct permissions while creating the team."
                >
                    <template #actions>
                        <FormButton type="button" tone="neutral" :icon="IconPlus" :disabled="!canAdd" @click="add">Add user</FormButton>
                    </template>

                    <div class="space-y-4">
                        <UiState v-if="form.user_assignments.length === 0" variant="empty" title="No initial members." size="compact" />

                        <div
                            v-for="(assignment, index) in form.user_assignments"
                            :key="index"
                            class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                        >
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                                <FormSelect
                                    v-model="assignment.user_public_id"
                                    label="User"
                                    :options="userOptionsForAssignment(assignment)"
                                    :error="form.errors[`user_assignments.${index}.user_public_id`]"
                                    @update:model-value="changeAssignedUser(assignment)"
                                />
                                <FormButton
                                    type="button"
                                    tone="danger"
                                    class="mt-0 md:mt-6"
                                    :icon="IconTrash"
                                    @click="removeUserAssignment(index)"
                                >
                                    Remove
                                </FormButton>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                <CheckboxList v-model="assignment.role_names" label="Roles" :options="roleOptions" />
                                <CheckboxList
                                    v-model="assignment.direct_permission_names"
                                    label="Direct permissions"
                                    :options="permissionOptions"
                                />
                            </div>
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard title="Module overrides" :icon="IconPuzzle" subtitle="Set team-specific module state while creating the team.">
                    <template #actions>
                        <FormButton
                            type="button"
                            tone="neutral"
                            :icon="IconPuzzle"
                            :disabled="!canAddModuleOverride"
                            @click="addModuleOverride"
                        >
                            Add module
                        </FormButton>
                    </template>

                    <div class="space-y-4">
                        <UiState
                            v-if="form.module_overrides.length === 0"
                            variant="empty"
                            title="No team module overrides."
                            size="compact"
                        />

                        <div
                            v-for="(override, index) in form.module_overrides"
                            :key="index"
                            class="grid gap-3 rounded-lg border border-zinc-200 p-4 xl:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] dark:border-zinc-800"
                        >
                            <FormSelect
                                v-model="override.module_key"
                                label="Module"
                                :options="moduleOptionsForOverride(override)"
                                :error="form.errors[`module_overrides.${index}.module_key`]"
                            />
                            <FormCheckbox v-model="override.enabled" class="mt-0 xl:mt-6">Enabled</FormCheckbox>
                            <FormInput v-model="override.reason" label="Reason" :error="form.errors[`module_overrides.${index}.reason`]" />
                            <FormButton
                                type="button"
                                tone="danger"
                                class="mt-0 xl:mt-6"
                                :icon="IconTrash"
                                @click="removeModuleOverride(index)"
                            >
                                Remove
                            </FormButton>
                        </div>
                    </div>
                </SurfaceCard>

                <FormActions class="mt-5">
                    <FormButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save team' }}
                    </FormButton>
                    <ActionLink href="/admin/teams" :icon="IconArrowLeft"> Back to teams </ActionLink>
                </FormActions>
            </AtlasForm>
        </PageStack>
    </AdminLayout>
</template>
