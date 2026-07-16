<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { IconUserPlus } from '@tabler/icons-vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect from '../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface PackagePreview {
    name: string;
    label: string;
    initialRoles: string[];
    directPermissions: string[];
    templatePermissions: string[];
}

const props = defineProps<{
    packages: PackagePreview[];
    copySources: { publicId: string; name: string; email: string; roles: string[]; directPermissions: string[] }[];
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: '',
    email: '',
    authorization_mode: 'package',
    onboarding_package: '',
    copy_authorization_from_user: '',
});

const selectedPackage = computed(() => props.packages.find((pkg) => pkg.name === form.onboarding_package) ?? null);
const selectedCopySource = computed(
    () => props.copySources.find((source) => source.publicId === form.copy_authorization_from_user) ?? null,
);
const authorizationModeOptions = [
    { value: 'package', label: 'Onboarding package' },
    { value: 'copy', label: 'Copy from user' },
];
const packageOptions = computed(() => [
    { value: '', label: 'No package' },
    ...props.packages.map((pkg) => ({ value: pkg.name, label: pkg.label })),
]);
const copySourceOptions = computed(() => [
    { value: '', label: 'Select user' },
    ...props.copySources.map((user) => ({ value: user.publicId, label: `${user.name} · ${user.email}` })),
]);

function submit(): void {
    form.post('/admin/users', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.users.create.head_title')" />
    <AdminLayout :title="t('pages.admin.users.create.title')" :title-icon="IconUserPlus">
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <AtlasForm class="space-y-4" :processing="form.processing" @submit="submit">
                <div class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
                        <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    </div>

                    <FormSelect
                        v-model="form.authorization_mode"
                        class="mt-4"
                        label="Authorization source"
                        :options="authorizationModeOptions"
                    />

                    <FormSelect
                        v-if="form.authorization_mode === 'package'"
                        v-model="form.onboarding_package"
                        class="mt-4"
                        label="Onboarding package"
                        :options="packageOptions"
                    />

                    <FormSelect
                        v-else
                        v-model="form.copy_authorization_from_user"
                        class="mt-4"
                        label="Copy roles and permissions from"
                        :options="copySourceOptions"
                    />

                    <FormButton type="submit" class="mt-5" :loading="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create user' }}
                    </FormButton>
                </div>
            </AtlasForm>

            <aside class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
                <h2 class="text-sm font-semibold uppercase text-zinc-500 dark:text-zinc-400">Package preview</h2>
                <div v-if="form.authorization_mode === 'package' && selectedPackage" class="mt-4 space-y-4">
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ selectedPackage.label }}</p>
                        <p class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ selectedPackage.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Initial roles</p>
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">{{ selectedPackage.initialRoles.join(', ') || 'None' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Direct permissions</p>
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                            {{ selectedPackage.directPermissions.join(', ') || 'None' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Template permissions</p>
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">{{ selectedPackage.templatePermissions.length }}</p>
                    </div>
                </div>
                <div v-else-if="form.authorization_mode === 'copy' && selectedCopySource" class="mt-4 space-y-4">
                    <div>
                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ selectedCopySource.name }}</p>
                        <p class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ selectedCopySource.email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Copied roles</p>
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">{{ selectedCopySource.roles.join(', ') || 'None' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">Copied direct permissions</p>
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-200">
                            {{ selectedCopySource.directPermissions.join(', ') || 'None' }}
                        </p>
                    </div>
                </div>
                <p v-else-if="form.authorization_mode === 'copy'" class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                    Select a user to preview copied roles and direct permissions in the active team.
                </p>
                <p v-else class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Select a package to preview its exact assignments.</p>
            </aside>
        </section>
    </AdminLayout>
</template>
