import { IconCalendarTime, IconFileImport, IconListDetails, IconRotateClockwise } from '@tabler/icons-vue';

import type { TranslationKey } from '../../../Localization/catalog';
import type { ShellSubnavigationItem } from '../../../Types/navigation';

type ManagedProcessSection = 'runs' | 'imports' | 'definitions' | 'schedules';
type Translate = (key: TranslationKey, params?: Record<string, string | number>) => string;

export function managedProcessSubnavigation(active: ManagedProcessSection, t: Translate): ShellSubnavigationItem[] {
    return [
        {
            key: 'runs',
            label: t('pages.admin.managed_processes.nav.runs'),
            href: '/admin/managed-processes',
            icon: IconRotateClockwise,
            active: active === 'runs',
        },
        {
            key: 'imports',
            label: t('pages.admin.managed_processes.nav.imports'),
            href: '/admin/managed-processes/imports',
            icon: IconFileImport,
            active: active === 'imports',
        },
        {
            key: 'definitions',
            label: t('pages.admin.managed_processes.nav.definitions'),
            href: '/admin/managed-processes/definitions',
            icon: IconListDetails,
            active: active === 'definitions',
        },
        {
            key: 'schedules',
            label: t('pages.admin.managed_processes.nav.schedules'),
            href: '/admin/managed-processes/schedules',
            icon: IconCalendarTime,
            active: active === 'schedules',
        },
    ];
}
