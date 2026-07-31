<script setup lang="ts">
import { IconArrowLeft, IconDeviceFloppy, IconPackage, IconUsersGroup } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionLink from '../ActionLink.vue';
import AtlasForm from '../Form/AtlasForm.vue';
import FormButton from '../Form/FormButton.vue';
import FormInput from '../Form/FormInput.vue';
import FormSelect from '../Form/FormSelect.vue';
import FormActions from '../FormActions.vue';
import SearchableCheckboxList from '../SearchableCheckboxList.vue';
import SurfaceCard from '../SurfaceCard.vue';
import { useTranslator } from '../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../Types/user-team-access';

interface TeamOption {
    value: string;
    label: string;
}

const teamPublicId = defineModel<string>('teamPublicId', { default: '' });
const name = defineModel<string>('name', { required: true });
const label = defineModel<string>('label', { required: true });
const initialRoles = defineModel<string[]>('initialRoles', { required: true });
const directPermissions = defineModel<string[]>('directPermissions', { required: true });

const props = withDefaults(
    defineProps<{
        mode: 'create' | 'edit';
        teamOptions?: TeamOption[];
        teamName?: string;
        roleOptions: AuthorizationAssignmentOption[];
        permissionOptions: AuthorizationAssignmentOption[];
        rolePermissionMap: Record<string, string[]>;
        errors?: Partial<Record<'team_public_id' | 'name' | 'label' | 'initial_roles' | 'direct_permissions', string>>;
        processing?: boolean;
        submitLabel: string;
        processingLabel: string;
        backHref: string;
    }>(),
    {
        teamOptions: () => [],
        teamName: '',
        errors: () => ({}),
        processing: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslator();
const selectedRolesLabel = computed(() =>
    t('pages.admin.packages.form.selected_roles', {
        selected: initialRoles.value.length,
        total: props.roleOptions.length,
    }),
);
const selectedPermissionsLabel = computed(() =>
    t('pages.admin.packages.form.selected_permissions', {
        selected: directPermissions.value.length,
        total: props.permissionOptions.length,
    }),
);

const roleLabelByValue = computed(() => new Map(props.roleOptions.map((option) => [option.value, option.label])));
const permissionLabelByValue = computed(() => new Map(props.permissionOptions.map((option) => [option.value, option.label])));
const effectivePermissions = computed(() =>
    Array.from(new Set([...initialRoles.value.flatMap((role) => props.rolePermissionMap[role] ?? []), ...directPermissions.value])).sort(),
);

function listLabel(values: string[], labels: Map<string, string>): string {
    return values.length === 0 ? t('pages.admin.packages.form.none') : values.map((value) => labels.get(value) ?? value).join(', ');
}
</script>

<template>
    <AtlasForm class="space-y-5" :processing="processing" @submit="emit('submit')">
        <SurfaceCard :title="t('pages.admin.packages.form.identity_title')" :icon="IconPackage" tone="teal">
            <div class="grid gap-4 lg:grid-cols-2">
                <FormSelect
                    v-if="mode === 'create'"
                    v-model="teamPublicId"
                    :label="t('pages.admin.packages.form.team')"
                    :placeholder="t('pages.admin.packages.form.team_placeholder')"
                    :options="teamOptions"
                    :error="errors.team_public_id"
                />
                <div v-else class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ t('pages.admin.packages.form.team') }}</span>
                    <p
                        class="min-h-10 rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                        {{ teamName }}
                    </p>
                </div>
                <FormInput
                    v-model="name"
                    :label="t('pages.admin.packages.form.name')"
                    :placeholder="t('pages.admin.packages.form.name_placeholder')"
                    :error="errors.name"
                    :disabled="mode === 'edit'"
                />
                <FormInput
                    v-model="label"
                    class="lg:col-span-2"
                    :label="t('pages.admin.packages.form.label')"
                    :placeholder="t('pages.admin.packages.form.label_placeholder')"
                    :error="errors.label"
                />
            </div>
        </SurfaceCard>

        <SurfaceCard :title="t('pages.admin.packages.form.assignments_title')" :icon="IconUsersGroup" tone="sky">
            <div class="grid gap-4 xl:grid-cols-2">
                <SearchableCheckboxList
                    v-model="initialRoles"
                    :options="roleOptions"
                    :label="t('pages.admin.packages.form.roles')"
                    :search-label="t('pages.admin.packages.form.role_search')"
                    :search-placeholder="t('pages.admin.packages.form.role_search_placeholder')"
                    :selected-label="selectedRolesLabel"
                    :empty-text="t('pages.admin.packages.form.no_roles')"
                    :error="errors.initial_roles"
                />
                <SearchableCheckboxList
                    v-model="directPermissions"
                    :options="permissionOptions"
                    :label="t('pages.admin.packages.form.permissions')"
                    :search-label="t('pages.admin.packages.form.permission_search')"
                    :search-placeholder="t('pages.admin.packages.form.permission_search_placeholder')"
                    :selected-label="selectedPermissionsLabel"
                    :empty-text="t('pages.admin.packages.form.no_permissions')"
                    :error="errors.direct_permissions"
                />
            </div>

            <section class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                <h3 class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">
                    {{ t('pages.admin.packages.form.preview') }}
                </h3>
                <div class="mt-3 grid gap-4 xl:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.packages.form.roles') }}
                        </p>
                        <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                            {{ listLabel(initialRoles, roleLabelByValue) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.packages.form.permissions') }}
                        </p>
                        <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                            {{ listLabel(directPermissions, permissionLabelByValue) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.packages.form.effective_permissions') }}
                        </p>
                        <p class="mt-1 break-words text-sm text-zinc-700 dark:text-zinc-200">
                            {{ listLabel(effectivePermissions, permissionLabelByValue) }}
                        </p>
                    </div>
                </div>
            </section>
        </SurfaceCard>

        <FormActions>
            <FormButton type="submit" :icon="IconDeviceFloppy" :loading="processing">
                {{ processing ? processingLabel : submitLabel }}
            </FormButton>
            <ActionLink :href="backHref" :icon="IconArrowLeft">
                {{ t('pages.admin.packages.actions.back_to_packages') }}
            </ActionLink>
        </FormActions>
    </AtlasForm>
</template>
