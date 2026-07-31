import { describe, expect, it } from 'vitest';

import { moduleLabel } from './moduleLabels';

const translations: Record<string, string> = {
    'pages.admin.dashboard.module.privacy': 'Prywatność i retencja',
};

const t = (key: string): string => translations[key] ?? key;

describe('module labels', () => {
    it('uses translated module names for user-facing labels', () => {
        expect(moduleLabel('privacy', t)).toBe('Prywatność i retencja');
    });

    it('humanizes unknown technical module keys instead of leaking raw underscores', () => {
        expect(moduleLabel('managed_processes_extension', t)).toBe('Managed Processes Extension');
    });
});
