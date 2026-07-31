import { computed } from 'vue';

import { useTranslator } from '../Localization/translator';
import type { FormSelectOption } from '../Components/Form/FormSelect.vue';

const accountSensitivityValues = ['normal', 'sensitive', 'technical', 'service', 'integration'] as const;

type AccountSensitivity = (typeof accountSensitivityValues)[number];

function isAccountSensitivity(value: string): value is AccountSensitivity {
    return accountSensitivityValues.includes(value as AccountSensitivity);
}

export function useAccountSensitivityOptions() {
    const { t } = useTranslator();

    const options = computed<FormSelectOption[]>(() =>
        accountSensitivityValues.map((value) => ({
            value,
            label: t(`pages.admin.users.sensitivity.${value}`),
        })),
    );

    function label(value: string): string {
        return isAccountSensitivity(value) ? t(`pages.admin.users.sensitivity.${value}`) : value;
    }

    return {
        options,
        label,
    };
}
