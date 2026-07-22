import { IconCalendarTime, IconFileImport, IconListDetails, IconRotateClockwise } from '@tabler/icons-vue';

import type { ShellSubnavigationItem } from '../../../Types/navigation';

type ManagedProcessSection = 'runs' | 'imports' | 'definitions' | 'schedules';

export function managedProcessSubnavigation(active: ManagedProcessSection): ShellSubnavigationItem[] {
    return [
        { key: 'runs', label: 'Runs', href: '/admin/managed-processes', icon: IconRotateClockwise, active: active === 'runs' },
        {
            key: 'imports',
            label: 'Imports',
            href: '/admin/managed-processes/imports',
            icon: IconFileImport,
            active: active === 'imports',
        },
        {
            key: 'definitions',
            label: 'Definitions',
            href: '/admin/managed-processes/definitions',
            icon: IconListDetails,
            active: active === 'definitions',
        },
        {
            key: 'schedules',
            label: 'Schedules',
            href: '/admin/managed-processes/schedules',
            icon: IconCalendarTime,
            active: active === 'schedules',
        },
    ];
}
