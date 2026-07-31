<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormSelect from '../../Components/Form/FormSelect.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { AtlasTeam } from '../../Types/inertia';

const props = defineProps<{
    teams: AtlasTeam[];
}>();

const selectedTeam = ref<string | number>(props.teams[0]?.publicId ?? '');
const submitting = ref(false);
const { t } = useTranslator();

function submit(): void {
    if (typeof selectedTeam.value !== 'string' || selectedTeam.value === '') {
        return;
    }

    submitting.value = true;
    router.post(
        '/team/select',
        { team_public_id: selectedTeam.value },
        {
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('team.select.head_title')" />
    <AuthLayout :title="t('team.select.heading')" :subtitle="t('team.select.description')">
        <AtlasForm class="space-y-5" :processing="submitting" @submit="submit">
            <FormSelect
                v-model="selectedTeam"
                :label="t('team.select.field')"
                :options="teams.map((team) => ({ value: team.publicId, label: team.name }))"
            />
            <FormButton type="submit" class="h-11 w-full" :loading="submitting">
                {{ t('team.select.continue') }}
            </FormButton>
        </AtlasForm>
    </AuthLayout>
</template>
