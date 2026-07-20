<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconTrash, IconUserEdit, IconUsersGroup } from '@tabler/icons-vue';
import { reactive } from 'vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import AdminFormActions from '../../../Components/AdminFormActions.vue';
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

interface UserFormData {
    publicId: string;
    name: string;
    email: string;
    isActive: boolean;
    emailVerified: boolean;
    firstPasswordSet: boolean;
    loginLocked: boolean;
    mfaEnabled: boolean;
    accountSensitivity: string;
    canImpersonate: boolean;
    impersonationRequiresSensitiveOverride: boolean;
}

interface TeamMembership {
    teamPublicId: string;
    teamName: string;
    teamActive: boolean;
    validFrom: string | null;
    validTo: string | null;
    roleNames: string[];
    directPermissionNames: string[];
}

const props = defineProps<{
    user: UserFormData;
    teamMemberships: TeamMembership[];
    assignableTeams: FormSelectOption[];
    roleOptions: string[];
    permissionOptions: string[];
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    account_sensitivity: props.user.accountSensitivity,
});
const teamForm = useForm({
    team_public_id: '',
});
const removalReasons = reactive<Record<string, string>>({});
const authorizationForms = reactive<Record<string, { role_names: string[]; direct_permission_names: string[]; reason: string }>>(
    Object.fromEntries(
        props.teamMemberships.map((membership) => [
            membership.teamPublicId,
            {
                role_names: [...membership.roleNames],
                direct_permission_names: [...membership.directPermissionNames],
                reason: '',
            },
        ]),
    ),
);
const recordActions = [
    { key: 'activate', label: 'Activate', method: 'post' as const, href: `/admin/users/${props.user.publicId}/activate` },
    { key: 'deactivate', label: 'Deactivate', method: 'post' as const, href: `/admin/users/${props.user.publicId}/deactivate` },
    { key: 'verify', label: 'Verify email', method: 'post' as const, href: `/admin/users/${props.user.publicId}/verify-email` },
    {
        key: 'require-email-verification',
        label: 'Require re-verification',
        method: 'post' as const,
        href: `/admin/users/${props.user.publicId}/require-email-verification`,
        tone: 'warning' as const,
    },
    {
        key: 'first-password',
        label: 'Send link',
        method: 'post' as const,
        href: `/admin/users/${props.user.publicId}/resend-first-password`,
    },
    { key: 'unlock', label: 'Unlock', method: 'post' as const, href: `/admin/users/${props.user.publicId}/unlock` },
    { key: 'reset-mfa', label: 'Reset MFA', method: 'post' as const, href: `/admin/users/${props.user.publicId}/reset-mfa` },
    { key: 'impersonate', label: 'Impersonate', method: 'get' as const, href: `/admin/users/${props.user.publicId}/impersonate` },
].filter((action) => action.key !== 'impersonate' || props.user.canImpersonate);
const sensitivityOptions = [
    { value: 'normal', label: 'Normal human account' },
    { value: 'sensitive', label: 'Sensitive human account' },
    { value: 'technical', label: 'Technical account' },
    { value: 'service', label: 'Service account' },
    { value: 'integration', label: 'Integration account' },
];

function submit(): void {
    form.patch(`/admin/users/${props.user.publicId}`, { preserveScroll: true });
}

function addTeamAccess(): void {
    teamForm.post(`/admin/users/${props.user.publicId}/teams`, {
        preserveScroll: true,
        onSuccess: () => teamForm.reset('team_public_id'),
    });
}

function removeTeamAccess(teamPublicId: string): void {
    router.delete(`/admin/users/${props.user.publicId}/teams/${teamPublicId}`, {
        data: {
            reason: removalReasons[teamPublicId] ?? '',
        },
        preserveScroll: true,
    });
}

function authorizationForm(teamPublicId: string): { role_names: string[]; direct_permission_names: string[]; reason: string } {
    authorizationForms[teamPublicId] ??= {
        role_names: [],
        direct_permission_names: [],
        reason: '',
    };

    return authorizationForms[teamPublicId];
}

function updateTeamAuthorization(teamPublicId: string): void {
    const values = authorizationForm(teamPublicId);

    router.patch(
        `/admin/users/${props.user.publicId}/teams/${teamPublicId}/authorization`,
        {
            role_names: values.role_names,
            direct_permission_names: values.direct_permission_names,
            reason: values.reason,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('pages.admin.users.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.users.edit.title')" :title-icon="IconUserEdit">
        <section class="space-y-5">
            <section class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Actions</h2>
                <AdminRecordActions class="mt-3" :actions="recordActions" />
            </section>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="space-y-5">
                    <AtlasForm
                        class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                        :processing="form.processing"
                        @submit="submit"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
                            <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                            <FormSelect
                                v-model="form.account_sensitivity"
                                label="Account sensitivity"
                                :options="sensitivityOptions"
                                :error="form.errors.account_sensitivity"
                            />
                        </div>

                        <AdminFormActions class="mt-5">
                            <FormButton type="submit" :loading="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save changes' }}
                            </FormButton>
                            <AdminActionLink href="/admin/users" :icon="IconArrowLeft"> Back to users </AdminActionLink>
                        </AdminFormActions>
                    </AtlasForm>

                    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex items-center gap-2">
                            <IconUsersGroup aria-hidden="true" class="h-5 w-5 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                            <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Team access</h2>
                        </div>

                        <AtlasForm
                            class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]"
                            :processing="teamForm.processing"
                            @submit="addTeamAccess"
                        >
                            <FormSelect
                                v-model="teamForm.team_public_id"
                                label="Add team"
                                :options="assignableTeams"
                                placeholder="Select team"
                                :error="teamForm.errors.team_public_id"
                            />
                            <FormButton
                                type="submit"
                                class="mt-0 md:mt-6"
                                :loading="teamForm.processing"
                                :disabled="assignableTeams.length === 0 || teamForm.team_public_id === ''"
                            >
                                Add access
                            </FormButton>
                        </AtlasForm>

                        <div
                            class="mt-5 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800"
                        >
                            <div v-if="teamMemberships.length === 0" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">
                                No active team access.
                            </div>
                            <div v-for="membership in teamMemberships" :key="membership.teamPublicId" class="space-y-4 p-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-zinc-950 dark:text-zinc-50">{{ membership.teamName }}</p>
                                        <StatusBadge :value="membership.teamActive" true-label="Active team" false-label="Inactive team" />
                                    </div>
                                    <p class="mt-1 break-all font-mono text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ membership.teamPublicId }}
                                    </p>
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
                                                v-model="authorizationForm(membership.teamPublicId).role_names"
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
                                                v-model="authorizationForm(membership.teamPublicId).direct_permission_names"
                                                :value="permission"
                                                align="start"
                                            >
                                                <span class="break-all font-mono text-xs">{{ permission }}</span>
                                            </FormCheckbox>
                                        </div>
                                    </section>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                                    <FormInput
                                        v-model="authorizationForm(membership.teamPublicId).reason"
                                        label="Authorization change reason"
                                        placeholder="Optional but recommended"
                                    />
                                    <FormButton
                                        type="button"
                                        class="mt-0 xl:mt-6"
                                        @click="updateTeamAuthorization(membership.teamPublicId)"
                                    >
                                        Save assignments
                                    </FormButton>
                                </div>

                                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_auto]">
                                    <FormInput
                                        v-model="removalReasons[membership.teamPublicId]"
                                        label="Removal reason"
                                        placeholder="Required before removal"
                                    />
                                    <FormButton
                                        type="button"
                                        tone="danger"
                                        class="mt-0 xl:mt-6"
                                        :icon="IconTrash"
                                        :disabled="!(removalReasons[membership.teamPublicId] ?? '').trim()"
                                        @click="removeTeamAccess(membership.teamPublicId)"
                                    >
                                        Remove access
                                    </FormButton>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Account status</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Active</dt>
                            <dd><StatusBadge :value="user.isActive" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Email verified</dt>
                            <dd><StatusBadge :value="user.emailVerified" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Password set</dt>
                            <dd><StatusBadge :value="user.firstPasswordSet" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Locked</dt>
                            <dd><StatusBadge :value="user.loginLocked" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">MFA</dt>
                            <dd><StatusBadge :value="user.mfaEnabled" /></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
