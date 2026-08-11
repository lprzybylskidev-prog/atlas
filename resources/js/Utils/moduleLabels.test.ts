import { describe, expect, it } from 'vitest';

import { moduleLabel } from './moduleLabels';

const translations: Record<string, string> = {
    'pages.admin.dashboard.module.privacy': 'Prywatność i retencja',
    'pages.admin.dashboard.module.unknown': 'Nieznany moduł ({module})',
};

const t = (key: string, params: Record<string, string | number> = {}): string => {
    let message = translations[key] ?? key;

    Object.entries(params).forEach(([name, value]) => {
        message = message.replaceAll(`{${name}}`, String(value));
    });

    return message;
};

describe('module labels', () => {
    it('uses translated module names for user-facing labels', () => {
        expect(moduleLabel('privacy', t)).toBe('Prywatność i retencja');
    });

    it('marks unknown technical module keys explicitly instead of inventing a label', () => {
        expect(moduleLabel('managed_processes_extension', t)).toBe('Nieznany moduł (managed_processes_extension)');
    });
});
