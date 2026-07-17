<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPlus, IconTrash, IconUserPlus } from '@tabler/icons-vue';

import AdminActionLink from '../../../Components/AdminActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import AdminFormActions from '../../../Components/AdminFormActions.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

interface PackagePreview {
    publicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    label: string;
    initialRoles: string[];
    directPermissions: string[];
    templatePermissions: string[];
}

interface CopySource {
    publicId: string;
    name: string;
    email: string;
    assignmentsByTeam: Record<string, { roles: string[]; directPermissions: string[] }>;
}

interface TeamAssignmentForm {
    team_public_id: string;
    source: 'manual' | 'package' | 'copy';
    onboarding_package: string;
    copy_authorization_from_user: string;
    role_names: string[];
    direct_permission_names: string[];
}

const props = defineProps<{
    packages: PackagePreview[];
    copySources: CopySource[];
    teamOptions: FormSelectOption[];
    roleOptions: string[];
    permissionOptions: string[];
    rolePermissionMap: Record<string, string[]>;
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: '',
    email: '',
    team_assignments: [] as TeamAssignmentForm[],
});

const sourceOptions: FormSelectOption[] = [
    { value: 'manual', label: 'Manual' },
    { value: 'package', label: 'Preset' },
    { value: 'copy', label: 'Copy from user' },
];
const canAddTeamAssignment = computed(() => form.team_assignments.length < props.teamOptions.length);
const canAdd = computed(() => canAddTeamAssignment.value);
function packageOptionsForAssignment(assignment: TeamAssignmentForm): FormSelectOption[] {
    return [
        { value: '', label: assignment.team_public_id === '' ? 'Select team first' : 'Select preset' },
        ...props.packages
            .filter((pkg) => pkg.teamPublicId === assignment.team_public_id)
            .map((pkg) => ({ value: pkg.name, label: pkg.label })),
    ];
}

function teamOptionsForAssignment(assignment: TeamAssignmentForm): FormSelectOption[] {
    const selectedTeamIds = new Set(
        form.team_assignments
            .filter((candidate) => candidate !== assignment)
            .map((candidate) => candidate.team_public_id)
            .filter((teamPublicId) => teamPublicId !== ''),
    );

    return [
        { value: '', label: props.teamOptions.length === 0 ? 'No teams available' : 'Select team' },
        ...props.teamOptions.filter((team) => !selectedTeamIds.has(String(team.value))),
    ];
}

function copySourceOptionsForAssignment(assignment: TeamAssignmentForm): FormSelectOption[] {
    const eligibleSources = props.copySources.filter((user) => user.assignmentsByTeam[assignment.team_public_id] !== undefined);

    return [
        { value: '', label: assignment.team_public_id === '' ? 'Select team first' : 'Select user' },
        ...eligibleSources.map((user) => ({ value: user.publicId, label: `${user.name} · ${user.email}` })),
    ];
}

function addTeamAssignment(): void {
    if (!canAddTeamAssignment.value) {
        return;
    }

    form.team_assignments.push({
        team_public_id: '',
        source: 'manual',
        onboarding_package: '',
        copy_authorization_from_user: '',
        role_names: [],
        direct_permission_names: [],
    });
}
const add = addTeamAssignment;

function removeTeamAssignment(index: number): void {
    form.team_assignments.splice(index, 1);
}

function resetAssignmentDetails(assignment: TeamAssignmentForm): void {
    assignment.onboarding_package = '';
    assignment.copy_authorization_from_user = '';
    assignment.role_names = [];
    assignment.direct_permission_names = [];
}

function changeAssignmentTeam(assignment: TeamAssignmentForm): void {
    resetAssignmentDetails(assignment);
}

function changeAssignmentSource(assignment: TeamAssignmentForm): void {
    resetAssignmentDetails(assignment);
}

function selectedPackage(assignment: TeamAssignmentForm): PackagePreview | null {
    return props.packages.find((pkg) => pkg.name === assignment.onboarding_package) ?? null;
}

function copiedAssignment(assignment: TeamAssignmentForm): { roles: string[]; directPermissions: string[] } {
    const source = props.copySources.find((user) => user.publicId === assignment.copy_authorization_from_user);

    return source?.assignmentsByTeam[assignment.team_public_id] ?? { roles: [], directPermissions: [] };
}

function resolvedRoles(assignment: TeamAssignmentForm): string[] {
    if (assignment.source === 'package') {
        return selectedPackage(assignment)?.initialRoles ?? [];
    }

    if (assignment.source === 'copy') {
        return copiedAssignment(assignment).roles;
    }

    return assignment.role_names;
}

function resolvedDirectPermissions(assignment: TeamAssignmentForm): string[] {
    if (assignment.source === 'package') {
        return selectedPackage(assignment)?.directPermissions ?? [];
    }

    if (assignment.source === 'copy') {
        return copiedAssignment(assignment).directPermissions;
    }

    return assignment.direct_permission_names;
}

function roleGrantedPermissions(assignment: TeamAssignmentForm): string[] {
    return Array.from(new Set(resolvedRoles(assignment).flatMap((role) => props.rolePermissionMap[role] ?? []))).sort();
}

function effectivePermissions(assignment: TeamAssignmentForm): string[] {
    return Array.from(new Set([...roleGrantedPermissions(assignment), ...resolvedDirectPermissions(assignment)])).sort();
}

function submit(): void {
    form.post('/admin/users', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.users.create.head_title')" />
    <AdminLayout :title="t('pages.admin.users.create.title')" :title-icon="IconUserPlus">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
                    <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Team assignments</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            A user must belong to at least one team. Roles and permissions are assigned in that team context.
                        </p>
                    </div>
                    <FormButton type="button" tone="neutral" :icon="IconPlus" :disabled="!canAdd" @click="add">Add team</FormButton>
                </div>

                <p v-if="form.errors.team_assignments" class="mt-3 text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.team_assignments }}
                </p>

                <div class="mt-5 space-y-4">
                    <div
                        v-if="form.team_assignments.length === 0"
                        class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                    >
                        Add at least one team assignment.
                    </div>

                    <div
                        v-for="(assignment, index) in form.team_assignments"
                        :key="index"
                        class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
                    >
                        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_16rem_auto]">
                            <FormSelect
                                v-model="assignment.team_public_id"
                                label="Team"
                                :options="teamOptionsForAssignment(assignment)"
                                :error="form.errors[`team_assignments.${index}.team_public_id`]"
                                @update:model-value="changeAssignmentTeam(assignment)"
                            />
                            <FormSelect
                                v-model="assignment.source"
                                label="Assignment source"
                                :options="sourceOptions"
                                :error="form.errors[`team_assignments.${index}.source`]"
                                @update:model-value="changeAssignmentSource(assignment)"
                            />
                            <FormButton
                                type="button"
                                tone="danger"
                                class="mt-0 xl:mt-6"
                                :icon="IconTrash"
                                @click="removeTeamAssignment(index)"
                            >
                                Remove
                            </FormButton>
                        </div>

                        <FormSelect
                            v-if="assignment.team_public_id !== '' && assignment.source === 'package'"
                            v-model="assignment.onboarding_package"
                            label="Preset"
                            :options="packageOptionsForAssignment(assignment)"
                            :error="form.errors[`team_assignments.${index}.onboarding_package`]"
                        />

                        <FormSelect
                            v-if="assignment.team_public_id !== '' && assignment.source === 'copy'"
                            v-model="assignment.copy_authorization_from_user"
                            label="Copy roles and permissions from"
                            :options="copySourceOptionsForAssignment(assignment)"
                            :error="form.errors[`team_assignments.${index}.copy_authorization_from_user`]"
                        />

                        <div v-if="assignment.team_public_id !== '' && assignment.source === 'manual'" class="grid gap-4 xl:grid-cols-2">
                            <section>
                                <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Roles</p>
                                <div
                                    class="mt-2 grid max-h-56 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                                >
                                    <FormCheckbox
                                        v-for="role in roleOptions"
                                        :key="role"
                                        v-model="assignment.role_names"
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
                                        v-model="assignment.direct_permission_names"
                                        :value="permission"
                                        align="start"
                                    >
                                        <span class="break-all font-mono text-xs">{{ permission }}</span>
                                    </FormCheckbox>
                                </div>
                            </section>
                        </div>

                        <section
                            v-if="assignment.team_public_id !== ''"
                            class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50"
                        >
                            <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Effective preview</h3>
                            <div class="mt-3 grid gap-4 xl:grid-cols-3">
                                <div>
                                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Roles</p>
                                    <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                                        {{ resolvedRoles(assignment).join(', ') || 'None' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Direct permissions</p>
                                    <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                                        {{ resolvedDirectPermissions(assignment).join(', ') || 'None' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Effective permissions</p>
                                    <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                                        {{ effectivePermissions(assignment).join(', ') || 'None' }}
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </section>

            <AdminFormActions>
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create user' }}
                </FormButton>
                <AdminActionLink href="/admin/users" :icon="IconArrowLeft"> Back to users </AdminActionLink>
            </AdminFormActions>
        </AtlasForm>
    </AdminLayout>
</template>
