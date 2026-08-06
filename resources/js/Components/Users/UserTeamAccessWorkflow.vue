<script setup lang="ts">
import { IconDeviceFloppy, IconPlus, IconTrash, IconUsersGroup } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import AuthorizationAssignmentPreview from '../Authorization/AuthorizationAssignmentPreview.vue';
import FormButton from '../Form/FormButton.vue';
import FormInput from '../Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../Form/FormSelect.vue';
import SearchableCheckboxList from '../SearchableCheckboxList.vue';
import SurfaceCard from '../SurfaceCard.vue';
import UiState from '../UiState.vue';
import {
    authorizationListLabel,
    effectivePermissions as resolveEffectivePermissions,
    selectedCountLabel,
} from '../../Composables/useAuthorizationAssignmentUi';
import { useTranslator } from '../../Localization/translator';
import type {
    AuthorizationAssignmentOption,
    TeamPolicyDefaults,
    UserTeamAccessAssignment,
    UserTeamAccessCopySource,
    UserTeamAccessPackage,
    UserTeamAccessRemovePayload,
    UserTeamAccessSavePayload,
} from '../../Types/user-team-access';

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        assignments: UserTeamAccessAssignment[];
        teamOptions: FormSelectOption[];
        packages: UserTeamAccessPackage[];
        copySources: UserTeamAccessCopySource[];
        roleOptions: AuthorizationAssignmentOption[];
        permissionOptions: AuthorizationAssignmentOption[];
        rolePermissionMap: Record<string, string[]>;
        sessionDefaults: {
            inactivityTimeoutMinutes: number;
            sessionMaxLifetimeMinutes: number;
        };
        teamPolicyDefaults: Record<string, TeamPolicyDefaults>;
        processing?: boolean;
        rootError?: string;
        errors?: Record<string, string>;
        errorPrefix?: string;
    }>(),
    {
        processing: false,
        rootError: undefined,
        errors: () => ({}),
        errorPrefix: 'team_assignments',
    },
);

const emit = defineEmits<{
    addTeam: [teamPublicId: string];
    remove: [payload: UserTeamAccessRemovePayload];
    save: [payload: UserTeamAccessSavePayload];
}>();

const { t } = useTranslator();
const pendingTeamPublicId = ref('');

const sourceOptions = computed<FormSelectOption[]>(() => [
    { value: 'manual', label: t('pages.admin.users.assignment.source.manual') },
    { value: 'package', label: t('pages.admin.users.assignment.source.package') },
    { value: 'copy', label: t('pages.admin.users.assignment.source.copy') },
]);
const assignedTeamPublicIds = computed(
    () => new Set(props.assignments.map((assignment) => assignment.team_public_id).filter((teamPublicId) => teamPublicId !== '')),
);
const roleLabelByValue = computed(() => new Map(props.roleOptions.map((option) => [option.value, option.label])));
const permissionLabelByValue = computed(() => new Map(props.permissionOptions.map((option) => [option.value, option.label])));
const availableTeamOptions = computed<FormSelectOption[]>(() =>
    props.teamOptions.filter((team) => !assignedTeamPublicIds.value.has(String(team.value))),
);
const canAddTeam = computed(
    () => pendingTeamPublicId.value !== '' && availableTeamOptions.value.some((team) => String(team.value) === pendingTeamPublicId.value),
);

function addTeam(): void {
    if (!canAddTeam.value || props.processing) {
        return;
    }

    emit('addTeam', pendingTeamPublicId.value);
    pendingTeamPublicId.value = '';
}

function teamPlaceholder(): string {
    if (props.teamOptions.length === 0 || availableTeamOptions.value.length === 0) {
        return t('pages.admin.users.team_access.no_assignable_teams');
    }

    return t('pages.admin.users.assignment.select_team');
}

function teamLabel(assignment: UserTeamAccessAssignment): string {
    return (
        assignment.teamName ??
        props.teamOptions.find((team) => String(team.value) === assignment.team_public_id)?.label ??
        assignment.team_public_id
    );
}

function resetAssignmentDetails(assignment: UserTeamAccessAssignment): void {
    assignment.onboarding_package = '';
    assignment.copy_authorization_from_user = '';

    if (assignment.source !== 'manual') {
        assignment.role_names = [];
        assignment.direct_permission_names = [];
    }
}

function packageOptionsForAssignment(assignment: UserTeamAccessAssignment): FormSelectOption[] {
    return props.packages
        .filter((pkg) => pkg.teamPublicId === assignment.team_public_id)
        .map((pkg) => ({ value: pkg.name, label: pkg.label }));
}

function copySourceOptionsForAssignment(assignment: UserTeamAccessAssignment): FormSelectOption[] {
    return props.copySources
        .filter((user) => user.assignmentsByTeam[assignment.team_public_id] !== undefined)
        .map((user) => ({ value: user.publicId, label: `${user.name} · ${user.email}` }));
}

function applyPackage(assignment: UserTeamAccessAssignment): void {
    const selected = props.packages.find(
        (pkg) => pkg.teamPublicId === assignment.team_public_id && pkg.name === assignment.onboarding_package,
    );

    assignment.role_names = selected === undefined ? [] : [...selected.initialRoles];
    assignment.direct_permission_names = selected === undefined ? [] : [...selected.directPermissions];
}

function applyCopySource(assignment: UserTeamAccessAssignment): void {
    const selected = props.copySources.find((source) => source.publicId === assignment.copy_authorization_from_user);
    const copied = selected?.assignmentsByTeam[assignment.team_public_id];

    assignment.role_names = copied === undefined ? [] : [...copied.roles];
    assignment.direct_permission_names = copied === undefined ? [] : [...copied.directPermissions];
}

function resolvedRoles(assignment: UserTeamAccessAssignment): string[] {
    return assignment.role_names;
}

function resolvedDirectPermissions(assignment: UserTeamAccessAssignment): string[] {
    return assignment.direct_permission_names;
}

function effectivePermissions(assignment: UserTeamAccessAssignment): string[] {
    return resolveEffectivePermissions(assignment, props.rolePermissionMap);
}

function listLabel(values: string[], labels: Map<string, string>): string {
    return authorizationListLabel(values, labels, t('pages.admin.users.assignment.none'));
}

function selectedRolesLabel(assignment: UserTeamAccessAssignment): string {
    return selectedCountLabel(assignment.role_names.length, props.roleOptions.length, (replacements) =>
        t('pages.admin.users.assignment.selected_roles', replacements),
    );
}

function selectedPermissionsLabel(assignment: UserTeamAccessAssignment): string {
    return selectedCountLabel(assignment.direct_permission_names.length, props.permissionOptions.length, (replacements) =>
        t('pages.admin.users.assignment.selected_permissions', replacements),
    );
}

function fieldError(index: number, field: string): string | undefined {
    return props.errors[`${props.errorPrefix}.${index}.${field}`];
}

function policyDefaults(assignment: UserTeamAccessAssignment): TeamPolicyDefaults {
    return (
        props.teamPolicyDefaults[assignment.team_public_id] ?? {
            inactivityTimeoutMinutes: props.sessionDefaults.inactivityTimeoutMinutes,
            sessionMaxLifetimeMinutes: props.sessionDefaults.sessionMaxLifetimeMinutes,
            breakDailyLimitMinutes: 30,
            breakMaximumSingleMinutes: 240,
        }
    );
}
</script>

<template>
    <SurfaceCard
        :title="t('pages.admin.users.team_access.title')"
        :subtitle="mode === 'create' ? t('pages.admin.users.assignment.subtitle') : undefined"
        :icon="IconUsersGroup"
        tone="sky"
    >
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <FormSelect
                v-model="pendingTeamPublicId"
                :label="t('pages.admin.users.team_access.add_team')"
                :options="availableTeamOptions"
                :placeholder="teamPlaceholder()"
            />
            <FormButton
                type="button"
                class="mt-0 md:mt-6"
                :icon="IconPlus"
                :loading="processing && pendingTeamPublicId !== ''"
                :disabled="!canAddTeam || processing"
                @click="addTeam"
            >
                {{ t('pages.admin.users.team_access.add_team') }}
            </FormButton>
        </div>

        <p v-if="rootError" class="mt-4 text-xs text-rose-600 dark:text-rose-300">
            {{ rootError }}
        </p>

        <div class="mt-5 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            <UiState
                v-if="assignments.length === 0"
                variant="empty"
                :title="t('pages.admin.users.team_access.empty_title')"
                :description="t('pages.admin.users.team_access.empty_description')"
                size="compact"
            />

            <div v-for="(assignment, index) in assignments" :key="assignment.team_public_id || index" class="space-y-4 p-4">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_16rem_auto]">
                    <div>
                        <p class="font-medium text-zinc-950 dark:text-zinc-50">
                            {{ teamLabel(assignment) }}
                        </p>
                        <p class="mt-1 break-all font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ assignment.team_public_id }}
                        </p>
                        <p v-if="fieldError(index, 'team_public_id')" class="mt-2 text-xs text-rose-600 dark:text-rose-300">
                            {{ fieldError(index, 'team_public_id') }}
                        </p>
                    </div>

                    <FormSelect
                        v-model="assignment.source"
                        :label="t('pages.admin.users.assignment.source')"
                        :options="sourceOptions"
                        :error="fieldError(index, 'source')"
                        @update:model-value="resetAssignmentDetails(assignment)"
                    />

                    <FormButton
                        v-if="mode === 'create'"
                        type="button"
                        tone="danger"
                        class="mt-0 xl:mt-6"
                        :icon="IconTrash"
                        @click="emit('remove', { assignment, index })"
                    >
                        {{ t('pages.admin.users.assignment.remove') }}
                    </FormButton>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/40">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ t('pages.admin.users.assignment.policy_limits_title') }}
                    </p>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <FormInput
                            v-model="assignment.inactivity_timeout_minutes"
                            type="number"
                            inputmode="numeric"
                            step="1"
                            min="1"
                            suffix="min"
                            :label="t('pages.admin.users.fields.inactivity_timeout_minutes')"
                            :placeholder="
                                t('pages.admin.users.session_limits.default_minutes', {
                                    minutes: policyDefaults(assignment).inactivityTimeoutMinutes,
                                })
                            "
                            :error="fieldError(index, 'inactivity_timeout_minutes')"
                        />
                        <FormInput
                            v-model="assignment.session_max_lifetime_minutes"
                            type="number"
                            inputmode="numeric"
                            step="1"
                            min="1"
                            suffix="min"
                            :label="t('pages.admin.users.fields.session_max_lifetime_minutes')"
                            :placeholder="
                                t('pages.admin.users.session_limits.default_minutes', {
                                    minutes: policyDefaults(assignment).sessionMaxLifetimeMinutes,
                                })
                            "
                            :error="fieldError(index, 'session_max_lifetime_minutes')"
                        />
                        <FormInput
                            v-model="assignment.break_daily_limit_minutes"
                            type="number"
                            inputmode="numeric"
                            step="1"
                            min="0"
                            suffix="min"
                            :label="t('pages.admin.users.assignment.break_daily_limit_minutes')"
                            :placeholder="
                                t('pages.admin.users.session_limits.default_minutes', {
                                    minutes: policyDefaults(assignment).breakDailyLimitMinutes,
                                })
                            "
                            :error="fieldError(index, 'break_daily_limit_minutes')"
                        />
                        <FormInput
                            v-model="assignment.break_maximum_single_minutes"
                            type="number"
                            inputmode="numeric"
                            step="1"
                            min="1"
                            suffix="min"
                            :label="t('pages.admin.users.assignment.break_maximum_single_minutes')"
                            :placeholder="
                                t('pages.admin.users.session_limits.default_minutes', {
                                    minutes: policyDefaults(assignment).breakMaximumSingleMinutes,
                                })
                            "
                            :error="fieldError(index, 'break_maximum_single_minutes')"
                        />
                    </div>
                </div>

                <FormSelect
                    v-if="assignment.source === 'package'"
                    v-model="assignment.onboarding_package"
                    :label="t('pages.admin.users.assignment.package')"
                    :options="packageOptionsForAssignment(assignment)"
                    :placeholder="t('pages.admin.users.assignment.select_package')"
                    :error="fieldError(index, 'onboarding_package')"
                    @update:model-value="applyPackage(assignment)"
                />

                <FormSelect
                    v-if="assignment.source === 'copy'"
                    v-model="assignment.copy_authorization_from_user"
                    :label="t('pages.admin.users.assignment.copy_from')"
                    :options="copySourceOptionsForAssignment(assignment)"
                    :placeholder="t('pages.admin.users.assignment.select_user')"
                    :error="fieldError(index, 'copy_authorization_from_user')"
                    @update:model-value="applyCopySource(assignment)"
                />

                <div v-if="assignment.source === 'manual'" class="grid gap-4 xl:grid-cols-2">
                    <SearchableCheckboxList
                        v-model="assignment.role_names"
                        :label="t('pages.admin.users.assignment.roles')"
                        :search-label="t('pages.admin.users.assignment.role_search')"
                        :search-placeholder="t('pages.admin.users.assignment.role_search_placeholder')"
                        :selected-label="selectedRolesLabel(assignment)"
                        :options="roleOptions"
                        :empty-text="t('pages.admin.users.assignment.no_roles')"
                        :error="fieldError(index, 'role_names')"
                    />
                    <SearchableCheckboxList
                        v-model="assignment.direct_permission_names"
                        :label="t('pages.admin.users.assignment.direct_permissions')"
                        :search-label="t('pages.admin.users.assignment.permission_search')"
                        :search-placeholder="t('pages.admin.users.assignment.permission_search_placeholder')"
                        :selected-label="selectedPermissionsLabel(assignment)"
                        :options="permissionOptions"
                        :empty-text="t('pages.admin.users.assignment.no_permissions')"
                        :error="fieldError(index, 'direct_permission_names')"
                    />
                </div>

                <AuthorizationAssignmentPreview
                    :title="t('pages.admin.users.assignment.preview')"
                    :roles-label="t('pages.admin.users.assignment.roles')"
                    :direct-permissions-label="t('pages.admin.users.assignment.direct_permissions')"
                    :effective-permissions-label="t('pages.admin.users.assignment.effective_permissions')"
                    :roles="listLabel(resolvedRoles(assignment), roleLabelByValue)"
                    :direct-permissions="listLabel(resolvedDirectPermissions(assignment), permissionLabelByValue)"
                    :effective-permissions="listLabel(effectivePermissions(assignment), permissionLabelByValue)"
                />

                <div v-if="mode === 'edit'" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <FormInput
                        v-model="assignment.reason"
                        :label="t('pages.admin.users.team_access.authorization_reason')"
                        :placeholder="t('pages.admin.users.team_access.authorization_reason_placeholder')"
                    />
                    <FormButton type="button" class="mt-0 xl:mt-6" :icon="IconDeviceFloppy" @click="emit('save', { assignment, index })">
                        {{ t('pages.admin.users.team_access.save_assignments') }}
                    </FormButton>
                </div>

                <div v-if="mode === 'edit'" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <FormInput
                        v-model="assignment.removal_reason"
                        :label="t('pages.admin.users.team_access.removal_reason')"
                        :placeholder="t('pages.admin.users.team_access.removal_reason_placeholder')"
                    />
                    <FormButton
                        type="button"
                        tone="danger"
                        class="mt-0 xl:mt-6"
                        :icon="IconTrash"
                        :disabled="!(assignment.removal_reason ?? '').trim()"
                        @click="emit('remove', { assignment, index })"
                    >
                        {{ t('pages.admin.users.team_access.remove_access') }}
                    </FormButton>
                </div>
            </div>
        </div>
    </SurfaceCard>
</template>
