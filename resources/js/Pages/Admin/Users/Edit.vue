<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUserEdit } from '@tabler/icons-vue';

import AdminRecordActions from '../../../Components/AdminRecordActions.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';

interface UserFormData {
    publicId: string;
    name: string;
    email: string;
    isActive: boolean;
    emailVerified: boolean;
    firstPasswordSet: boolean;
    loginLocked: boolean;
    mfaEnabled: boolean;
}

const props = defineProps<{
    user: UserFormData;
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: props.user.name,
    email: props.user.email,
});
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
];

function submit(): void {
    form.patch(`/admin/users/${props.user.publicId}`, { preserveScroll: true });
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
                <AtlasForm
                    class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                    :processing="form.processing"
                    @submit="submit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput v-model="form.name" label="Name" :error="form.errors.name" />
                        <FormInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <FormButton type="submit" :loading="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save changes' }}
                        </FormButton>
                        <Link
                            href="/admin/users"
                            class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                        >
                            <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                            Back to users
                        </Link>
                    </div>
                </AtlasForm>

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
