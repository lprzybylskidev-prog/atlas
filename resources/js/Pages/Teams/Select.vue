<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { IconBuildingCommunity } from '@tabler/icons-vue';
import { ref } from 'vue';

import AppLayout from '../../Layouts/AppLayout.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormSelect from '../../Components/Form/FormSelect.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
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
    <AppLayout :title="t('team.select.title')" :title-icon="IconBuildingCommunity" :show-locale-switcher="false">
        <PageStack>
            <SurfaceCard :title="t('team.select.heading')" :icon="IconBuildingCommunity" :subtitle="t('team.select.description')">
                <AtlasForm class="space-y-4" :processing="submitting" @submit="submit">
                    <FormSelect
                        v-model="selectedTeam"
                        :label="t('team.select.field')"
                        :options="teams.map((team) => ({ value: team.publicId, label: team.name }))"
                    />
                    <FormButton type="submit" :loading="submitting">{{ t('team.select.continue') }}</FormButton>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
