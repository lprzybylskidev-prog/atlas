<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormCheckbox from '../../Components/Form/FormCheckbox.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import NoticeBanner from '../../Components/NoticeBanner.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';

const form = useForm({
    email: '',
    password: '',
    remember: false,
    terminate_existing_session: false,
});

const { t } = useTranslator();
const sessionConflictError = computed(() => (form.errors as Record<string, string | undefined>).session_conflict);
const hasSessionConflict = computed(() => Boolean(sessionConflictError.value));

const submit = (): void => {
    form.terminate_existing_session = false;
    form.post('/login', {
        preserveScroll: true,
    });
};

const continueHere = (): void => {
    form.terminate_existing_session = true;
    form.post('/login', {
        preserveScroll: true,
    });
};

const cancelConflict = (): void => {
    form.terminate_existing_session = false;
    form.reset('password');
    form.clearErrors();
};
</script>

<template>
    <Head :title="t('auth.login.head_title')" />
    <AuthLayout :title="t('auth.login.title')" :subtitle="t('auth.login.subtitle')">
        <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
            <FormInput
                id="email"
                v-model="form.email"
                :label="t('auth.login.email')"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
            />

            <FormInput
                id="password"
                v-model="form.password"
                :label="t('auth.login.password')"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
            />

            <FormCheckbox v-model="form.remember" :label="t('auth.login.remember')" />

            <NoticeBanner v-if="hasSessionConflict" :title="t('auth.login.session_conflict.title')" tone="warning" role="alert">
                {{ t('auth.login.session_conflict.description') }}
                <template #actions>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <FormButton type="button" tone="neutral" class="h-10 w-full" :disabled="form.processing" @click="cancelConflict">
                            {{ t('auth.login.session_conflict.cancel') }}
                        </FormButton>
                        <FormButton type="button" tone="danger" class="h-10 w-full" :loading="form.processing" @click="continueHere">
                            {{ t('auth.login.session_conflict.continue') }}
                        </FormButton>
                    </div>
                </template>
            </NoticeBanner>

            <FormButton type="submit" class="h-11 w-full" :loading="form.processing">
                {{ form.processing ? t('auth.login.submitting') : t('auth.login.submit') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
