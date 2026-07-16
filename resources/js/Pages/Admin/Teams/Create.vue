<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPlus, IconTrash, IconUsersGroup } from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';

const props = defineProps<{
    userOptions: FormSelectOption[];
    roleOptions: string[];
    permissionOptions: string[];
}>();
const { t } = useTranslator('en');
const form = useForm({
    name: '',
    user_assignments: [] as {
        user_public_id: string;
        role_names: string[];
        direct_permission_names: string[];
    }[],
});

type UserAssignmentForm = (typeof form.user_assignments)[number];

const canAddUserAssignment = computed(() => form.user_assignments.length < props.userOptions.length);
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
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <section class="max-w-2xl rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Initial members</h2>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Assign users, roles, and direct permissions while creating the team.
                        </p>
                    </div>
                    <FormButton type="button" tone="neutral" :icon="IconPlus" :disabled="!canAdd" @click="add">Add user</FormButton>
                </div>

                <div class="mt-5 space-y-4">
                    <div
                        v-if="form.user_assignments.length === 0"
                        class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
                    >
                        No initial members.
                    </div>

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
                    </div>
                </div>
            </section>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save team' }}
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
    </AdminLayout>
</template>
