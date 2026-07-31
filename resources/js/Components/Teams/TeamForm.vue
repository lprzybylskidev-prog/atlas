<script setup lang="ts">
import { IconArrowLeft, IconDeviceFloppy, IconUsersGroup } from '@tabler/icons-vue';

import ActionLink from '../ActionLink.vue';
import AtlasForm from '../Form/AtlasForm.vue';
import FormButton from '../Form/FormButton.vue';
import FormInput from '../Form/FormInput.vue';
import FormActions from '../FormActions.vue';
import SurfaceCard from '../SurfaceCard.vue';
import { useTranslator } from '../../Localization/translator';

const name = defineModel<string>('name', { required: true });
const displayName = defineModel<string>('displayName', { required: true });

withDefaults(
    defineProps<{
        errors?: Partial<Record<'name' | 'display_name', string>>;
        processing?: boolean;
        submitLabel: string;
        processingLabel: string;
        backHref: string;
    }>(),
    {
        errors: () => ({}),
        processing: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslator();
</script>

<template>
    <AtlasForm class="space-y-5" :processing="processing" @submit="emit('submit')">
        <SurfaceCard :title="t('pages.admin.teams.form.identity_title')" :icon="IconUsersGroup" tone="teal">
            <div class="grid gap-4 lg:grid-cols-2">
                <FormInput
                    v-model="name"
                    :label="t('pages.admin.teams.form.technical_name')"
                    :placeholder="t('pages.admin.teams.form.technical_name_placeholder')"
                    :error="errors.name"
                />
                <FormInput
                    v-model="displayName"
                    :label="t('pages.admin.teams.form.display_name')"
                    :placeholder="t('pages.admin.teams.form.display_name_placeholder')"
                    :error="errors.display_name"
                />
            </div>
        </SurfaceCard>

        <slot />

        <FormActions>
            <FormButton type="submit" :icon="IconDeviceFloppy" :loading="processing">
                {{ processing ? processingLabel : submitLabel }}
            </FormButton>
            <ActionLink :href="backHref" :icon="IconArrowLeft">
                {{ t('pages.admin.teams.actions.back_to_teams') }}
            </ActionLink>
        </FormActions>
    </AtlasForm>
</template>
