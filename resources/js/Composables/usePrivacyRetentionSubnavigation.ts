import { IconHistory, IconScale, IconShieldCheck } from '@tabler/icons-vue';
import { computed, type ComputedRef } from 'vue';

import type { ShellSubnavigationItem } from '../Types/navigation';

export function usePrivacyRetentionSubnavigation(currentPath: string, t: (key: string) => string): ComputedRef<ShellSubnavigationItem[]> {
    return computed<ShellSubnavigationItem[]>(() => [
        {
            key: 'privacy.coverage',
            label: t('pages.admin.privacy_retention.nav.coverage'),
            href: '/admin/privacy-retention',
            icon: IconShieldCheck,
            active: currentPath === '/admin/privacy-retention',
        },
        {
            key: 'privacy.legal_holds',
            label: t('pages.admin.privacy_retention.nav.legal_holds'),
            href: '/admin/privacy-retention/legal-holds',
            icon: IconScale,
            active: currentPath.startsWith('/admin/privacy-retention/legal-holds'),
        },
        {
            key: 'privacy.operations',
            label: t('pages.admin.privacy_retention.nav.operations'),
            href: '/admin/privacy-retention/operations',
            icon: IconHistory,
            active: currentPath.startsWith('/admin/privacy-retention/operations'),
        },
    ]);
}
