<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconBell,
    IconCheck,
    IconClock,
    IconKey,
    IconMail,
    IconPalette,
    IconQrcode,
    IconShieldLock,
    IconUpload,
    IconUserCircle,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormCheckbox from '../../Components/Form/FormCheckbox.vue';
import FormColorPicker from '../../Components/Form/FormColorPicker.vue';
import FormImageCropper from '../../Components/Form/FormImageCropper.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormActions from '../../Components/FormActions.vue';
import OperationalTile from '../../Components/OperationalTile.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import Tooltip from '../../Components/Tooltip.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import { beginFullscreenTransitionLoading } from '../../Services/fullscreenTransitionLoading';
import { DEFAULT_AVATAR_COLOR, readableAvatarTextColor } from '../../Utils/avatar';
import { formatDateTime as formatSharedDateTime } from '../../Utils/formatters';

interface NotificationEmail {
    publicId: string;
    email: string;
    primary: boolean;
    verified: boolean;
    verifiedAt: string | null;
    pendingVerification: boolean;
    enabledTypes: string[];
}

interface NotificationType {
    type: string;
    labelKey: string;
    descriptionKey: string;
    bodyPreviewKey: string;
    bodyPreviewParams: Record<string, string | number>;
}

const props = defineProps<{
    profile: {
        name: string;
        email: string;
        password: {
            changedAt: string | null;
            expiresAt: string | null;
            expiresAfterDays: number;
        };
        session: {
            inactivityTimeoutMinutes: number;
        };
        timeTracking: {
            breakDailyLimitMinutes: number | null;
        };
        mfa: {
            enabled: boolean;
            pendingConfirmation: boolean;
            confirmedAt: string | null;
        };
        avatar: {
            color: string | null;
            imageUrl: string | null;
        };
        notificationEmails: NotificationEmail[];
        notificationTypes: NotificationType[];
    };
}>();

const { locale, t } = useTranslator();
const selectedEmailPublicId = ref(props.profile.notificationEmails[0]?.publicId ?? '');
const selectedEmail = computed(
    () => props.profile.notificationEmails.find((email) => email.publicId === selectedEmailPublicId.value) ?? null,
);
const enabledNotificationTypes = ref<string[]>(selectedEmail.value?.enabledTypes ?? []);
const qrCodeSvg = ref('');
const recoveryCodes = ref<string[]>([]);
const mfaArtifactsVisible = ref(false);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const avatarForm = useForm<{
    avatar_color: string;
    avatar_image: File | null;
    remove_avatar_image: boolean;
}>({
    avatar_color: props.profile.avatar.color ?? DEFAULT_AVATAR_COLOR,
    avatar_image: null,
    remove_avatar_image: false,
});
const emailForm = useForm({
    email: '',
});
const preferencesForm = useForm({
    enabled_types: enabledNotificationTypes.value,
});
const mfaConfirmationForm = useForm({
    code: '',
});

const selectedEmailOptions = computed<FormSelectOption[]>(() =>
    props.profile.notificationEmails.map((email) => ({
        value: email.publicId,
        label: `${email.email}${email.primary ? ` (${t('pages.user_panel.notifications.primary')})` : ''}`,
    })),
);
const passwordExpiryValue = computed(() =>
    props.profile.password.expiresAt === null
        ? t('pages.user_panel.password.expiry_unknown')
        : formatSharedDateTime(props.profile.password.expiresAt, locale.value),
);
const inactivityTimeoutValue = computed(() =>
    t('pages.user_panel.session.inactivity_timeout_value', { minutes: props.profile.session.inactivityTimeoutMinutes }),
);
const breakDailyLimitValue = computed(() =>
    props.profile.timeTracking.breakDailyLimitMinutes === null
        ? ''
        : t('pages.user_panel.time_tracking.break_daily_limit_value', { minutes: props.profile.timeTracking.breakDailyLimitMinutes }),
);
const hasNotificationTypes = computed(() => props.profile.notificationTypes.length > 0);
const mfaStatus = computed(() => {
    if (props.profile.mfa.enabled) {
        return t('pages.user_panel.mfa.enabled');
    }

    return props.profile.mfa.pendingConfirmation ? t('pages.user_panel.mfa.pending') : t('pages.user_panel.mfa.disabled');
});
const avatarColorPreview = computed(() => avatarForm.avatar_color || DEFAULT_AVATAR_COLOR);
const qrCodeDataUrl = computed(() => (qrCodeSvg.value === '' ? '' : `data:image/svg+xml;base64,${window.btoa(qrCodeSvg.value)}`));
const avatarTextColor = computed(() => readableAvatarTextColor(avatarColorPreview.value));

watch(
    () => props.profile.notificationEmails,
    () => {
        selectedEmailPublicId.value = props.profile.notificationEmails[0]?.publicId ?? '';
    },
);

watch(selectedEmail, (email) => {
    enabledNotificationTypes.value = email?.enabledTypes ?? [];
    preferencesForm.enabled_types = [...enabledNotificationTypes.value];
});

watch(enabledNotificationTypes, (types) => {
    preferencesForm.enabled_types = [...types];
});

function submitPassword(): void {
    passwordForm.put('/user/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function submitAvatar(): void {
    const finishLoading = beginFullscreenTransitionLoading();

    avatarForm.post('/user/avatar', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            avatarForm.avatar_image = null;
            avatarForm.remove_avatar_image = false;
            router.visit(window.location.pathname + window.location.search, {
                only: ['auth', 'profile', 'flash'],
                preserveScroll: true,
                preserveState: false,
                onFinish: finishLoading,
            });
        },
        onError: () => {
            finishLoading();
        },
        onCancel: () => {
            finishLoading();
        },
    });
}

function removeAvatarImage(): void {
    avatarForm.avatar_image = null;
    avatarForm.remove_avatar_image = true;
    submitAvatar();
}

function addNotificationEmail(): void {
    emailForm.post('/user/notification-emails', {
        preserveScroll: true,
        onSuccess: () => emailForm.reset(),
    });
}

function saveNotificationPreferences(): void {
    if (selectedEmail.value === null) {
        return;
    }

    preferencesForm.patch(`/user/notification-emails/${selectedEmail.value.publicId}`, {
        preserveScroll: true,
    });
}

function notificationBodyPreview(type: NotificationType): string {
    let preview = t(type.bodyPreviewKey);

    Object.entries(type.bodyPreviewParams).forEach(([name, value]) => {
        preview = preview.replaceAll(`:${name}`, String(value)).replaceAll(`{${name}}`, String(value));
    });

    return preview;
}

function enableMfa(): void {
    router.post(
        '/user/two-factor-authentication',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                loadMfaArtifacts();
            },
        },
    );
}

function confirmMfa(): void {
    mfaConfirmationForm.post('/user/confirmed-two-factor-authentication', {
        preserveScroll: true,
        onSuccess: () => {
            mfaConfirmationForm.reset();
            mfaArtifactsVisible.value = false;
        },
    });
}

function disableMfa(): void {
    router.delete('/user/two-factor-authentication', {
        preserveScroll: true,
        onSuccess: () => {
            qrCodeSvg.value = '';
            recoveryCodes.value = [];
            mfaArtifactsVisible.value = false;
        },
    });
}

async function toggleMfaArtifacts(): Promise<void> {
    if (mfaArtifactsVisible.value) {
        mfaArtifactsVisible.value = false;

        return;
    }

    if (qrCodeSvg.value === '' && recoveryCodes.value.length === 0) {
        await loadMfaArtifacts();
    }

    mfaArtifactsVisible.value = true;
}

async function loadMfaArtifacts(): Promise<void> {
    const [qrResponse, codesResponse] = await Promise.all([fetch('/user/two-factor-qr-code'), fetch('/user/two-factor-recovery-codes')]);

    if (qrResponse.ok && qrResponse.headers.get('content-type')?.includes('application/json')) {
        const qr = await qrResponse.json();
        qrCodeSvg.value = typeof qr.svg === 'string' ? qr.svg : '';
    }

    if (codesResponse.ok && codesResponse.headers.get('content-type')?.includes('application/json')) {
        const codes = await codesResponse.json();
        recoveryCodes.value = Array.isArray(codes) ? codes.filter((code): code is string => typeof code === 'string') : [];
    }
}
</script>

<template>
    <Head :title="t('pages.user_panel.profile_head_title')" />
    <AppLayout :title="t('pages.user_panel.profile_title')" :title-icon="IconUserCircle" mode="user">
        <PageStack>
            <SurfaceCard :title="t('pages.user_panel.profile_title')" :icon="IconUserCircle" tone="zinc">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <OperationalTile :label="t('pages.user_panel.name')" :value="profile.name" :icon="IconUserCircle" />
                    <OperationalTile :label="t('pages.user_panel.email')" :value="profile.email" :icon="IconMail" />
                    <OperationalTile :label="t('pages.user_panel.password.expires_at')" :value="passwordExpiryValue" :icon="IconClock" />
                    <OperationalTile
                        :label="t('pages.user_panel.session.inactivity_timeout')"
                        :value="inactivityTimeoutValue"
                        :icon="IconClock"
                    />
                    <OperationalTile
                        v-if="profile.timeTracking.breakDailyLimitMinutes !== null"
                        :label="t('pages.user_panel.time_tracking.break_daily_limit')"
                        :value="breakDailyLimitValue"
                        :icon="IconClock"
                    />
                    <OperationalTile :label="t('pages.user_panel.mfa.status')" :value="mfaStatus" :icon="IconShieldLock" />
                </div>
            </SurfaceCard>

            <div class="grid gap-4 xl:grid-cols-2">
                <SurfaceCard :title="t('pages.user_panel.password.title')" :icon="IconKey" tone="zinc">
                    <AtlasForm class="grid gap-4" :processing="passwordForm.processing" @submit="submitPassword">
                        <FormInput
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            :label="t('pages.user_panel.password.current')"
                            :error="passwordForm.errors.current_password"
                        />
                        <FormInput
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            :label="t('pages.user_panel.password.new')"
                            :error="passwordForm.errors.password"
                        />
                        <FormInput
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :label="t('pages.user_panel.password.confirm')"
                            :error="passwordForm.errors.password_confirmation"
                        />
                        <FormActions class="justify-end">
                            <FormButton
                                v-if="!profile.mfa.enabled && !profile.mfa.pendingConfirmation"
                                type="button"
                                tone="neutral"
                                :icon="IconShieldLock"
                                @click="enableMfa"
                            >
                                {{ t('pages.user_panel.mfa.enable') }}
                            </FormButton>
                            <FormButton type="submit" :loading="passwordForm.processing" :icon="IconKey">
                                {{ t('pages.user_panel.password.save') }}
                            </FormButton>
                        </FormActions>
                    </AtlasForm>
                    <div class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <FormActions class="justify-end">
                            <FormButton
                                v-if="profile.mfa.enabled || profile.mfa.pendingConfirmation"
                                tone="neutral"
                                :icon="IconQrcode"
                                @click="toggleMfaArtifacts"
                            >
                                {{ mfaArtifactsVisible ? t('pages.user_panel.mfa.hide_codes') : t('pages.user_panel.mfa.show_codes') }}
                            </FormButton>
                            <FormButton
                                v-if="profile.mfa.enabled || profile.mfa.pendingConfirmation"
                                tone="danger"
                                :icon="IconShieldLock"
                                @click="disableMfa"
                            >
                                {{ t('pages.user_panel.mfa.disable') }}
                            </FormButton>
                        </FormActions>
                        <AtlasForm
                            v-if="profile.mfa.pendingConfirmation"
                            class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                            :processing="mfaConfirmationForm.processing"
                            @submit="confirmMfa"
                        >
                            <FormInput
                                v-model="mfaConfirmationForm.code"
                                inputmode="numeric"
                                :label="t('pages.user_panel.mfa.code')"
                                :error="mfaConfirmationForm.errors.code"
                            />
                            <FormButton type="submit" :loading="mfaConfirmationForm.processing" :icon="IconCheck">
                                {{ t('pages.user_panel.mfa.confirm') }}
                            </FormButton>
                        </AtlasForm>
                        <div
                            v-if="mfaArtifactsVisible && (qrCodeSvg || recoveryCodes.length > 0)"
                            class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                        >
                            <div v-if="qrCodeDataUrl" class="max-w-48 bg-white p-2">
                                <img :src="qrCodeDataUrl" :alt="t('pages.user_panel.mfa.qr_alt')" class="h-auto w-full" />
                            </div>
                            <div v-if="recoveryCodes.length > 0" class="grid gap-2 font-mono text-xs text-zinc-700 dark:text-zinc-200">
                                <span v-for="code in recoveryCodes" :key="code">{{ code }}</span>
                            </div>
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard :title="t('pages.user_panel.avatar.title')" :icon="IconPalette" tone="zinc">
                    <AtlasForm class="grid gap-4" :processing="avatarForm.processing" @submit="submitAvatar">
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full text-lg font-semibold"
                                :style="{ backgroundColor: avatarColorPreview, color: avatarTextColor }"
                            >
                                <img
                                    v-if="profile.avatar.imageUrl"
                                    :src="profile.avatar.imageUrl"
                                    :alt="t('pages.user_panel.avatar.current')"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ profile.name.slice(0, 1) }}</span>
                            </div>
                            <div v-if="profile.avatar.imageUrl" class="flex min-w-0 flex-1 justify-end">
                                <FormButton type="button" tone="neutral" :loading="avatarForm.processing" @click="removeAvatarImage">
                                    {{ t('pages.user_panel.avatar.remove_image') }}
                                </FormButton>
                            </div>
                        </div>
                        <FormColorPicker
                            v-if="!profile.avatar.imageUrl"
                            v-model="avatarForm.avatar_color"
                            :label="t('pages.user_panel.avatar.color_picker')"
                            :red-label="t('pages.user_panel.avatar.red')"
                            :green-label="t('pages.user_panel.avatar.green')"
                            :blue-label="t('pages.user_panel.avatar.blue')"
                            :error="avatarForm.errors.avatar_color"
                        />
                        <FormImageCropper
                            v-model="avatarForm.avatar_image"
                            :label="t('pages.user_panel.avatar.upload')"
                            :choose-label="t('pages.user_panel.avatar.choose_image')"
                            :crop-label="t('pages.user_panel.avatar.crop_image')"
                            :crop-action-label="t('pages.user_panel.avatar.crop')"
                            :reset-label="t('pages.user_panel.avatar.reset_crop')"
                            :zoom-in-label="t('pages.user_panel.avatar.zoom_in')"
                            :zoom-out-label="t('pages.user_panel.avatar.zoom_out')"
                            stencil="circle"
                            :aspect-ratio="1"
                            :output-width="512"
                            :output-height="512"
                            output-mime="image/webp"
                            output-suffix="avatar"
                            :error="avatarForm.errors.avatar_image"
                        />
                        <FormActions class="justify-end">
                            <FormButton type="submit" :loading="avatarForm.processing" :icon="IconUpload">
                                {{ t('pages.user_panel.avatar.save') }}
                            </FormButton>
                        </FormActions>
                    </AtlasForm>
                </SurfaceCard>
            </div>

            <SurfaceCard v-if="hasNotificationTypes" :title="t('pages.user_panel.notifications.title')" :icon="IconBell" tone="zinc">
                <div class="grid gap-5 xl:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
                    <div class="space-y-4">
                        <AtlasForm class="grid gap-3" :processing="emailForm.processing" @submit="addNotificationEmail">
                            <FormInput
                                v-model="emailForm.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                :label="t('pages.user_panel.notifications.add_email')"
                                :error="emailForm.errors.email"
                            />
                            <FormActions class="justify-end">
                                <FormButton type="submit" :loading="emailForm.processing" :icon="IconMail">
                                    {{ t('pages.user_panel.notifications.add') }}
                                </FormButton>
                            </FormActions>
                        </AtlasForm>
                        <FormSelect
                            v-model="selectedEmailPublicId"
                            :options="selectedEmailOptions"
                            :label="t('pages.user_panel.notifications.selected_email')"
                        />
                        <div v-if="selectedEmail" class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ selectedEmail.email }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{
                                    selectedEmail.verified
                                        ? t('pages.user_panel.notifications.verified')
                                        : t('pages.user_panel.notifications.pending_verification')
                                }}
                            </p>
                        </div>
                    </div>
                    <AtlasForm
                        v-if="selectedEmail"
                        class="space-y-3"
                        :processing="preferencesForm.processing"
                        @submit="saveNotificationPreferences"
                    >
                        <div
                            v-for="type in profile.notificationTypes"
                            :key="type.type"
                            class="border-b border-zinc-200 py-3 last:border-b-0 dark:border-zinc-800"
                        >
                            <Tooltip :text="notificationBodyPreview(type)" full-width placement="top" align="start" wide>
                                <FormCheckbox v-model="enabledNotificationTypes" :value="type.type" align="start">
                                    <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ t(type.labelKey) }}</span>
                                    <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ t(type.descriptionKey) }}</span>
                                </FormCheckbox>
                            </Tooltip>
                        </div>
                        <FormActions class="justify-end">
                            <FormButton type="submit" :loading="preferencesForm.processing" :icon="IconBell">
                                {{ t('pages.user_panel.notifications.save') }}
                            </FormButton>
                        </FormActions>
                    </AtlasForm>
                </div>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
