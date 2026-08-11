import { h, type VNodeChild } from 'vue';

import type { TranslationKey } from '../Localization/catalog';
import type { DataTableColumn } from '../Types/data-table';
import {
    formatDate,
    formatEmpty,
    formatFileSize,
    formatMoney,
    formatNumber,
    formatPercent,
    formatTimestamp,
    formatTime,
} from '../Utils/formatters';
import { statusBadgeToneForToken } from '../Utils/statusBadge';
import SeverityBadge from '../Components/SeverityBadge.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { statusDefinition, statusTranslationKey } from './statusCatalog';

type Translator = (key: TranslationKey, params?: Record<string, string | number>) => string;
type ColumnFormat = DataTableColumn<Record<string, unknown>>['format'];

export function createDataTableFormatting(t: Translator, locale?: string) {
    function localizedStatus(value: string): string {
        const key = statusTranslationKey(value);

        return key === undefined ? `[status:${value}]` : t(key);
    }

    function statusBadge(value: string): VNodeChild {
        const definition = statusDefinition(value);

        return h(StatusBadge, {
            label: localizedStatus(value),
            tone: definition?.tone ?? statusBadgeToneForToken(value),
            icon: definition?.icon,
        });
    }

    function formattedText(value: unknown, format: ColumnFormat): string {
        if (format === 'list' && Array.isArray(value)) return value.join(', ');
        if (format === 'count' && Array.isArray(value)) return String(value.length);
        if (format === 'date' && (typeof value === 'string' || value instanceof Date)) return formatDate(value, locale);
        if (format === 'time' && (typeof value === 'string' || value instanceof Date)) return formatTime(value, locale);
        if (format === 'datetime' && (typeof value === 'string' || value instanceof Date)) return formatTimestamp(value, locale);
        if (format === 'money' && value !== null && typeof value === 'object' && 'amountMinor' in value && 'currency' in value) {
            return formatMoney(value as { amountMinor: number; currency: string }, locale);
        }
        if (format === 'file-size' && typeof value === 'number') return formatFileSize(value, locale);
        if (format === 'number' && typeof value === 'number') return formatNumber(value, locale);
        if (format === 'percent' && typeof value === 'number') return formatPercent(value, locale);
        if (format === 'activation-status' && typeof value === 'boolean') return localizedStatus(value ? 'active' : 'inactive');
        if ((format === 'severity' || format === 'status' || format === 'status-badge') && typeof value === 'string') {
            return localizedStatus(value);
        }

        return formatEmpty(value);
    }

    function formatCell(value: unknown, format: ColumnFormat): VNodeChild {
        if (format === 'activation-status' && typeof value === 'boolean') return statusBadge(value ? 'active' : 'inactive');
        if (format === 'boolean') {
            return h(StatusBadge, {
                value: value === true,
                trueLabel: t('datatable.boolean.yes'),
                falseLabel: t('datatable.boolean.no'),
            });
        }
        if (format === 'status-badge' && typeof value === 'string') return statusBadge(value);
        if (format === 'severity' && typeof value === 'string') {
            return h(SeverityBadge, { value, label: localizedStatus(value) });
        }

        return formattedText(value, format);
    }

    return { formatCell, formattedText };
}
