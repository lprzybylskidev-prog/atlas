<script setup lang="ts">
import { IconShieldLock } from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasForm from '../Form/AtlasForm.vue';
import FormInput from '../Form/FormInput.vue';
import FormActions from '../FormActions.vue';
import SearchableCheckboxList from '../SearchableCheckboxList.vue';
import SurfaceCard from '../SurfaceCard.vue';
import { useTranslator } from '../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../Types/user-team-access';

const name = defineModel<string>('name', { required: true });
const permissions = defineModel<string[]>('permissions', { required: true });

const props = withDefaults(
    defineProps<{
        permissionOptions: AuthorizationAssignmentOption[];
        errors?: Partial<Record<'name' | 'display_name' | 'permissions', string>>;
        processing?: boolean;
        submitLabel: string;
        processingLabel: string;
        backHref: string;
        dirty?: boolean;
    }>(),
    {
        errors: () => ({}),
        processing: false,
        dirty: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslator();
const displayName = defineModel<string>('displayName', { required: true });
const selectedCountLabel = computed(() =>
    t('pages.admin.roles.form.selected_permissions', {
        selected: permissions.value.length,
        total: props.permissionOptions.length,
    }),
);
</script>

<template>
    <AtlasForm class="space-y-5" :processing="processing" @submit="emit('submit')">
        <SurfaceCard :title="t('pages.admin.roles.form.identity_title')" :icon="IconShieldLock" tone="teal">
            <FormInput
                v-model="name"
                :label="t('pages.admin.roles.form.technical_name')"
                :placeholder="t('pages.admin.roles.form.technical_name_placeholder')"
                :error="errors.name"
            />
            <FormInput
                v-model="displayName"
                class="mt-4"
                :label="t('pages.admin.roles.form.display_name')"
                :placeholder="t('pages.admin.roles.form.display_name_placeholder')"
                :error="errors.display_name"
            />
        </SurfaceCard>

        <SurfaceCard :title="t('pages.admin.roles.form.permissions_title')" :icon="IconShieldLock" tone="zinc">
            <SearchableCheckboxList
                v-model="permissions"
                :options="permissionOptions"
                :label="t('pages.admin.roles.form.permissions')"
                :search-label="t('pages.admin.roles.form.permission_search')"
                :search-placeholder="t('pages.admin.roles.form.permission_search_placeholder')"
                :selected-label="selectedCountLabel"
                :empty-text="t('pages.admin.roles.form.no_permissions')"
                :error="errors.permissions"
                max-height="max-h-96"
            />
        </SurfaceCard>

        <FormActions
            :submit-label="submitLabel"
            :processing-label="processingLabel"
            :processing="processing"
            :dirty="dirty"
            :cancel-href="backHref"
            :scope-label="t('pages.admin.roles.form.identity_title')"
        />
    </AtlasForm>
</template>
