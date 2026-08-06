import type { FormSelectOption } from '../Components/Form/FormSelect.vue';
import { formatStatus } from './formatters';

export interface ExistingFilterOption {
    value: string;
    label: string;
}

export function optionsWithAll(
    values: string[],
    allLabel: string,
    formatter: (value: string) => string = formatStatus,
): FormSelectOption[] {
    return [
        { value: 'all', label: allLabel },
        ...values.map((value) => ({
            value,
            label: formatter(value),
        })),
    ];
}

export function existingOptionsWithAll(
    values: ExistingFilterOption[],
    allLabel: string,
    formatter?: (option: ExistingFilterOption) => string,
): FormSelectOption[] {
    return [
        { value: 'all', label: allLabel },
        ...values.map((option) => ({
            value: option.value,
            label: formatter === undefined ? option.label : formatter(option),
        })),
    ];
}

export function yesNoOptionsWithAll(allLabel: string, yesLabel: string, noLabel: string): FormSelectOption[] {
    return [
        { value: 'all', label: allLabel },
        { value: 'yes', label: yesLabel },
        { value: 'no', label: noLabel },
    ];
}
