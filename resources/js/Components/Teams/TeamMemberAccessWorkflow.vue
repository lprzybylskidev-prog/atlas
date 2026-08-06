<script setup lang="ts">
import { IconDeviceFloppy, IconPlus, IconTrash, IconUserPlus, IconUsersGroup } from '@tabler/icons-vue';
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
import type { AuthorizationAssignmentOption, TeamPolicyDefaults } from '../../Types/user-team-access';

export interface TeamMemberAccessAssignment {
    user_public_id: string;
    userName?: string;
    userEmail?: string;
    role_names: string[];
    direct_permission_names: string[];
    inactivity_timeout_minutes: string;
    session_max_lifetime_minutes: string;
    break_daily_limit_minutes: string;
    break_maximum_single_minutes: string;
    reason: string;
    removal_reason: string;
}

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        assignments: TeamMemberAccessAssignment[];
        userOptions: FormSelectOption[];
        roleOptions: AuthorizationAssignmentOption[];
        permissionOptions: AuthorizationAssignmentOption[];
        rolePermissionMap: Record<string, string[]>;
        sessionDefaults: {
            inactivityTimeoutMinutes: number;
            sessionMaxLifetimeMinutes: number;
        };
        policyDefaults: TeamPolicyDefaults;
        processing?: boolean;
        rootError?: string;
        errors?: Record<string, string>;
        errorPrefix?: string;
    }>(),
    {
        processing: false,
        rootError: undefined,
        errors: () => ({}),
        errorPrefix: 'user_assignments',
    },
);

const emit = defineEmits<{
    addUser: [userPublicId: string];
    remove: [payload: { assignment: TeamMemberAccessAssignment; index: number }];
    save: [payload: { assignment: TeamMemberAccessAssignment; index: number }];
}>();

const { t } = useTranslator();
const pendingUserPublicId = ref('');

const assignedUserPublicIds = computed(
    () => new Set(props.assignments.map((assignment) => assignment.user_public_id).filter((userPublicId) => userPublicId !== '')),
);
const availableUserOptions = computed<FormSelectOption[]>(() =>
    props.userOptions.filter((user) => !assignedUserPublicIds.value.has(String(user.value))),
);
const canAddUser = computed(
    () => pendingUserPublicId.value !== '' && availableUserOptions.value.some((user) => String(user.value) === pendingUserPublicId.value),
);
const roleLabelByValue = computed(() => new Map(props.roleOptions.map((option) => [option.value, option.label])));
const permissionLabelByValue = computed(() => new Map(props.permissionOptions.map((option) => [option.value, option.label])));

function addUser(): void {
    if (!canAddUser.value || props.processing) {
        return;
    }

    emit('addUser', pendingUserPublicId.value);
    pendingUserPublicId.value = '';
}

function userPlaceholder(): string {
    if (props.userOptions.length === 0 || availableUserOptions.value.length === 0) {
        return t('pages.admin.teams.members.no_assignable_users');
    }

    return t('pages.admin.teams.members.select_user');
}

function userLabel(assignment: TeamMemberAccessAssignment): string {
    if (assignment.userName !== undefined && assignment.userEmail !== undefined) {
        return `${assignment.userName} · ${assignment.userEmail}`;
    }

    return props.userOptions.find((user) => String(user.value) === assignment.user_public_id)?.label ?? assignment.user_public_id;
}

function effectivePermissions(assignment: TeamMemberAccessAssignment): string[] {
    return resolveEffectivePermissions(assignment, props.rolePermissionMap);
}

function listLabel(values: string[], labels: Map<string, string>): string {
    return authorizationListLabel(values, labels, t('pages.admin.teams.members.none'));
}

function selectedRolesLabel(assignment: TeamMemberAccessAssignment): string {
    return selectedCountLabel(assignment.role_names.length, props.roleOptions.length, (replacements) =>
        t('pages.admin.teams.members.selected_roles', replacements),
    );
}

function selectedPermissionsLabel(assignment: TeamMemberAccessAssignment): string {
    return selectedCountLabel(assignment.direct_permission_names.length, props.permissionOptions.length, (replacements) =>
        t('pages.admin.teams.members.selected_permissions', replacements),
    );
}

function fieldError(index: number, field: string): string | undefined {
    return props.errors[`${props.errorPrefix}.${index}.${field}`];
}
</script>

<template>
    <SurfaceCard :title="t('pages.admin.teams.members.title')" :icon="IconUsersGroup" tone="sky">
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
            <FormSelect
                v-model="pendingUserPublicId"
                :label="t('pages.admin.teams.members.add_user')"
                :options="availableUserOptions"
                :placeholder="userPlaceholder()"
            />
            <FormButton
                type="button"
                class="mt-0 md:mt-6"
                :icon="IconUserPlus"
                :loading="processing && pendingUserPublicId !== ''"
                :disabled="!canAddUser || processing"
                @click="addUser"
            >
                {{ t('pages.admin.teams.members.add_user') }}
            </FormButton>
        </div>

        <p v-if="rootError" class="mt-4 text-xs text-rose-600 dark:text-rose-300">
            {{ rootError }}
        </p>

        <div class="mt-5 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            <UiState
                v-if="assignments.length === 0"
                variant="empty"
                :title="t('pages.admin.teams.members.empty_title')"
                :description="t('pages.admin.teams.members.empty_description')"
                size="compact"
            />

            <div v-for="(assignment, index) in assignments" :key="assignment.user_public_id || index" class="space-y-4 p-4">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <p class="font-medium text-zinc-950 dark:text-zinc-50">
                            {{ userLabel(assignment) }}
                        </p>
                        <p class="mt-1 break-all font-mono text-xs text-zinc-500 dark:text-zinc-400">
                            {{ assignment.user_public_id }}
                        </p>
                        <p v-if="fieldError(index, 'user_public_id')" class="mt-2 text-xs text-rose-600 dark:text-rose-300">
                            {{ fieldError(index, 'user_public_id') }}
                        </p>
                    </div>

                    <FormButton
                        v-if="mode === 'create'"
                        type="button"
                        tone="danger"
                        class="mt-0 xl:mt-6"
                        :icon="IconTrash"
                        @click="emit('remove', { assignment, index })"
                    >
                        {{ t('pages.admin.teams.members.remove_draft') }}
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
                                    minutes: policyDefaults.inactivityTimeoutMinutes,
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
                                    minutes: policyDefaults.sessionMaxLifetimeMinutes,
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
                                    minutes: policyDefaults.breakDailyLimitMinutes,
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
                                    minutes: policyDefaults.breakMaximumSingleMinutes,
                                })
                            "
                            :error="fieldError(index, 'break_maximum_single_minutes')"
                        />
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <SearchableCheckboxList
                        v-model="assignment.role_names"
                        :options="roleOptions"
                        :label="t('pages.admin.teams.members.roles')"
                        :search-label="t('pages.admin.teams.members.role_search')"
                        :search-placeholder="t('pages.admin.teams.members.role_search_placeholder')"
                        :selected-label="selectedRolesLabel(assignment)"
                        :empty-text="t('pages.admin.teams.members.no_roles')"
                        :error="fieldError(index, 'role_names')"
                    />
                    <SearchableCheckboxList
                        v-model="assignment.direct_permission_names"
                        :options="permissionOptions"
                        :label="t('pages.admin.teams.members.permissions')"
                        :search-label="t('pages.admin.teams.members.permission_search')"
                        :search-placeholder="t('pages.admin.teams.members.permission_search_placeholder')"
                        :selected-label="selectedPermissionsLabel(assignment)"
                        :empty-text="t('pages.admin.teams.members.no_permissions')"
                        :error="fieldError(index, 'direct_permission_names')"
                    />
                </div>

                <AuthorizationAssignmentPreview
                    :title="t('pages.admin.teams.members.preview')"
                    :roles-label="t('pages.admin.teams.members.roles')"
                    :direct-permissions-label="t('pages.admin.teams.members.permissions')"
                    :effective-permissions-label="t('pages.admin.teams.members.effective_permissions')"
                    :roles="listLabel(assignment.role_names, roleLabelByValue)"
                    :direct-permissions="listLabel(assignment.direct_permission_names, permissionLabelByValue)"
                    :effective-permissions="listLabel(effectivePermissions(assignment), permissionLabelByValue)"
                />

                <div v-if="mode === 'edit'" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <FormInput
                        v-model="assignment.reason"
                        :label="t('pages.admin.teams.members.change_reason')"
                        :placeholder="t('pages.admin.teams.members.change_reason_placeholder')"
                    />
                    <FormButton
                        type="button"
                        class="mt-0 xl:mt-6"
                        :icon="IconDeviceFloppy"
                        :loading="processing"
                        @click="emit('save', { assignment, index })"
                    >
                        {{ t('pages.admin.teams.members.save_authorization') }}
                    </FormButton>
                </div>

                <div v-if="mode === 'edit'" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                    <FormInput
                        v-model="assignment.removal_reason"
                        :label="t('pages.admin.teams.members.removal_reason')"
                        :placeholder="t('pages.admin.teams.members.removal_reason_placeholder')"
                    />
                    <FormButton
                        type="button"
                        tone="danger"
                        class="mt-0 xl:mt-6"
                        :icon="IconTrash"
                        :disabled="(assignment.removal_reason ?? '').trim() === ''"
                        @click="emit('remove', { assignment, index })"
                    >
                        {{ t('pages.admin.teams.members.remove_access') }}
                    </FormButton>
                </div>
            </div>
        </div>

        <p v-if="mode === 'create' && assignments.length > 0" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
            <IconPlus aria-hidden="true" class="mr-1 inline h-4 w-4" :stroke-width="1.8" />
            {{ t('pages.admin.teams.members.create_note') }}
        </p>
    </SurfaceCard>
</template>
