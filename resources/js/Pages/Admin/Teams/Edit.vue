<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPuzzle, IconTrash, IconUsersGroup } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import AdminRecordActions from '../../../Components/AdminRecordActions.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

interface TeamFormData {
    publicId: string;
    name: string;
    isActive: boolean;
}

interface MemberRow {
    userPublicId: string;
    userName: string;
    userEmail: string;
    validFrom: string | null;
    validTo: string | null;
    roleNames: string[];
    directPermissionNames: string[];
}

interface ModuleStateRow {
    moduleKey: string;
    category: string;
    teamEnabled: boolean;
    effectiveEnabled: boolean;
    source: string;
    version: number | null;
    supportsTeamActivation: boolean;
    readOnly: boolean;
}

const props = defineProps<{
    team: TeamFormData;
    memberships: MemberRow[];
    assignableUsers: FormSelectOption[];
    roleOptions: string[];
    permissionOptions: string[];
    moduleStates: ModuleStateRow[];
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: props.team.name,
});
const addMemberForm = useForm({
    user_public_id: '',
});
const canAddMember = computed(() => props.assignableUsers.length > 0 && addMemberForm.user_public_id !== '');
const memberAuthorizationForms = reactive<Record<string, { role_names: string[]; direct_permission_names: string[]; reason: string }>>(
    Object.fromEntries(
        props.memberships.map((membership) => [
            membership.userPublicId,
            {
                role_names: [...membership.roleNames],
                direct_permission_names: [...membership.directPermissionNames],
                reason: '',
            },
        ]),
    ),
);
const removalReasons = reactive<Record<string, string>>({});
const moduleForms = reactive<Record<string, { enabled: boolean; reason: string; version: number | null; clearReason: string }>>(
    Object.fromEntries(
        props.moduleStates.map((module) => [
            module.moduleKey,
            {
                enabled: module.teamEnabled,
                reason: '',
                version: module.version,
                clearReason: '',
            },
        ]),
    ),
);
const recordActions = [
    { key: 'activate', label: 'Activate', method: 'post' as const, href: `/admin/teams/${props.team.publicId}/activate` },
    { key: 'deactivate', label: 'Deactivate', method: 'post' as const, href: `/admin/teams/${props.team.publicId}/deactivate` },
    { key: 'delete', label: 'Delete', method: 'delete' as const, href: `/admin/teams/${props.team.publicId}` },
];

function submit(): void {
    form.patch(`/admin/teams/${props.team.publicId}`, { preserveScroll: true });
}

function addMember(): void {
    if (addMemberForm.user_public_id === '') {
        return;
    }

    router.post(
        `/admin/users/${addMemberForm.user_public_id}/teams`,
        { team_public_id: props.team.publicId },
        {
            preserveScroll: true,
            onSuccess: () => addMemberForm.reset('user_public_id'),
        },
    );
}

function memberAuthorizationForm(userPublicId: string): { role_names: string[]; direct_permission_names: string[]; reason: string } {
    memberAuthorizationForms[userPublicId] ??= {
        role_names: [],
        direct_permission_names: [],
        reason: '',
    };

    return memberAuthorizationForms[userPublicId];
}

function updateMemberAuthorization(userPublicId: string): void {
    const values = memberAuthorizationForm(userPublicId);

    router.patch(
        `/admin/users/${userPublicId}/teams/${props.team.publicId}/authorization`,
        {
            role_names: values.role_names,
            direct_permission_names: values.direct_permission_names,
            reason: values.reason,
        },
        { preserveScroll: true },
    );
}

function removeMember(userPublicId: string): void {
    router.delete(`/admin/users/${userPublicId}/teams/${props.team.publicId}`, {
        data: {
            reason: removalReasons[userPublicId] ?? '',
        },
        preserveScroll: true,
    });
}

function moduleForm(module: ModuleStateRow): { enabled: boolean; reason: string; version: number | null; clearReason: string } {
    moduleForms[module.moduleKey] ??= {
        enabled: module.teamEnabled,
        reason: '',
        version: module.version,
        clearReason: '',
    };

    return moduleForms[module.moduleKey];
}

function updateModule(module: ModuleStateRow): void {
    const values = moduleForm(module);

    router.patch(
        `/admin/modules/${module.moduleKey}/teams/${props.team.publicId}`,
        {
            enabled: values.enabled,
            reason: values.reason,
            version: values.version,
        },
        { preserveScroll: true },
    );
}

function clearModule(module: ModuleStateRow): void {
    const values = moduleForm(module);

    router.delete(`/admin/modules/${module.moduleKey}/teams/${props.team.publicId}`, {
        data: {
            reason: values.clearReason,
        },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.teams.edit.title')" :title-icon="IconUsersGroup">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Actions</h2>
                <AdminRecordActions class="mt-3" :actions="recordActions" />
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="space-y-5">
                    <AtlasForm
                        class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                        :processing="form.processing"
                        @submit="submit"
                    >
                        <FormInput v-model="form.name" label="Name" :error="form.errors.name" />

                        <div class="mt-5 flex flex-wrap items-center gap-2">
                            <FormButton type="submit" :loading="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save changes' }}
                            </FormButton>
                            <Link
                                href="/admin/teams"
                                class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                            >
                                <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                Back to teams
                            </Link>
                        </div>
                    </AtlasForm>

                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Members</h2>

                        <AtlasForm
                            class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]"
                            :processing="addMemberForm.processing"
                            @submit="addMember"
                        >
                            <FormSelect
                                v-model="addMemberForm.user_public_id"
                                label="Add user"
                                :options="[{ value: '', label: 'Select user' }, ...assignableUsers]"
                                placeholder="Select user"
                            />
                            <FormButton type="submit" class="mt-0 md:mt-6" :disabled="!canAddMember">Add member</FormButton>
                        </AtlasForm>

                        <div
                            class="mt-5 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                        >
                            <div v-if="memberships.length === 0" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">
                                No active members.
                            </div>

                            <div v-for="membership in memberships" :key="membership.userPublicId" class="space-y-4 p-4">
                                <div>
                                    <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ membership.userName }}</p>
                                    <p class="mt-1 break-all text-xs text-zinc-500 dark:text-zinc-400">{{ membership.userEmail }}</p>
                                </div>

                                <div class="grid gap-4 xl:grid-cols-2">
                                    <section>
                                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Roles</p>
                                        <div
                                            class="mt-2 grid max-h-56 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                                        >
                                            <FormCheckbox
                                                v-for="role in roleOptions"
                                                :key="role"
                                                v-model="memberAuthorizationForm(membership.userPublicId).role_names"
                                                :value="role"
                                                align="start"
                                            >
                                                <span class="break-all font-mono text-xs">{{ role }}</span>
                                            </FormCheckbox>
                                        </div>
                                    </section>

                                    <section>
                                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Direct permissions</p>
                                        <div
                                            class="mt-2 grid max-h-56 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                                        >
                                            <FormCheckbox
                                                v-for="permission in permissionOptions"
                                                :key="permission"
                                                v-model="memberAuthorizationForm(membership.userPublicId).direct_permission_names"
                                                :value="permission"
                                                align="start"
                                            >
                                                <span class="break-all font-mono text-xs">{{ permission }}</span>
                                            </FormCheckbox>
                                        </div>
                                    </section>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto_auto]">
                                    <FormInput
                                        v-model="memberAuthorizationForm(membership.userPublicId).reason"
                                        label="Authorization change reason"
                                        placeholder="Optional but recommended"
                                    />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        @click="updateMemberAuthorization(membership.userPublicId)"
                                    >
                                        Save assignments
                                    </FormButton>
                                    <FormButton
                                        type="button"
                                        tone="danger"
                                        class="mt-0 xl:mt-6"
                                        :icon="IconTrash"
                                        :disabled="!(removalReasons[membership.userPublicId] ?? '').trim()"
                                        @click="removeMember(membership.userPublicId)"
                                    >
                                        Remove access
                                    </FormButton>
                                </div>

                                <FormInput
                                    v-model="removalReasons[membership.userPublicId]"
                                    label="Removal reason"
                                    placeholder="Required before removal"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Modules</h2>
                        <div
                            class="mt-5 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                        >
                            <div v-for="module in moduleStates" :key="module.moduleKey" class="space-y-4 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ module.moduleKey }}</p>
                                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ module.category }} · {{ module.source }}
                                        </p>
                                    </div>
                                    <StatusBadge :value="module.effectiveEnabled" />
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[auto_minmax(0,1fr)_auto]">
                                    <FormCheckbox
                                        v-model="moduleForm(module).enabled"
                                        :disabled="module.readOnly || !module.supportsTeamActivation"
                                    >
                                        Enabled override
                                    </FormCheckbox>
                                    <FormInput v-model="moduleForm(module).reason" label="Override reason" />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        :icon="IconPuzzle"
                                        :disabled="module.readOnly || !module.supportsTeamActivation || !moduleForm(module).reason.trim()"
                                        @click="updateModule(module)"
                                    >
                                        Save module
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                                    <FormInput v-model="moduleForm(module).clearReason" label="Clear override reason" />
                                    <FormButton
                                        type="button"
                                        tone="danger"
                                        class="mt-0 xl:mt-6"
                                        :disabled="module.source !== 'team' || !moduleForm(module).clearReason.trim()"
                                        @click="clearModule(module)"
                                    >
                                        Inherit global
                                    </FormButton>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Team status</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Active</dt>
                            <dd><StatusBadge :value="team.isActive" /></dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Public ID</dt>
                            <dd class="mt-1 break-all font-mono text-xs text-zinc-700 dark:text-zinc-200">{{ team.publicId }}</dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
