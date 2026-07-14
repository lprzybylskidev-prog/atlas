import type { ComposableHostViewDefinition, ComposableHostViewLayout, ComposableViewElementArea } from '../Types/composable-view';

export interface ComposableViewHostLayoutArea {
    area: ComposableViewElementArea;
    wrapperClass: string;
    listClass: string;
}

export interface ComposableViewHostLayoutDefinition {
    key: ComposableHostViewLayout;
    supportedHostTypes: readonly ComposableHostViewDefinition['type'][];
    containerClass: string;
    areas: readonly ComposableViewHostLayoutArea[];
}

export const COMPOSABLE_VIEW_HOST_LAYOUTS: readonly ComposableViewHostLayoutDefinition[] = [
    {
        key: 'dashboard-sidebar',
        supportedHostTypes: ['dashboard', 'module-landing'],
        containerClass: 'grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]',
        areas: [
            {
                area: 'full',
                wrapperClass: 'xl:col-span-2',
                listClass: 'grid gap-4',
            },
            {
                area: 'main',
                wrapperClass: 'min-w-0',
                listClass: 'grid gap-4 xl:grid-cols-4',
            },
            {
                area: 'aside',
                wrapperClass: 'min-w-0',
                listClass: 'space-y-4',
            },
        ],
    },
    {
        key: 'overview-grid',
        supportedHostTypes: ['overview', 'module-landing'],
        containerClass: 'grid gap-4',
        areas: [
            {
                area: 'full',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4',
            },
            {
                area: 'main',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4 md:grid-cols-2 xl:grid-cols-3',
            },
            {
                area: 'aside',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4 md:grid-cols-2',
            },
        ],
    },
    {
        key: 'manager-workspace',
        supportedHostTypes: ['manager-workspace'],
        containerClass: 'grid gap-4 2xl:grid-cols-[minmax(0,1fr)_28rem]',
        areas: [
            {
                area: 'full',
                wrapperClass: '2xl:col-span-2',
                listClass: 'grid gap-4',
            },
            {
                area: 'main',
                wrapperClass: 'min-w-0',
                listClass: 'grid gap-4 xl:grid-cols-2',
            },
            {
                area: 'aside',
                wrapperClass: 'min-w-0',
                listClass: 'space-y-4',
            },
        ],
    },
    {
        key: 'operational-status',
        supportedHostTypes: ['operational-status'],
        containerClass: 'grid gap-4',
        areas: [
            {
                area: 'full',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4',
            },
            {
                area: 'main',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4',
            },
            {
                area: 'aside',
                wrapperClass: 'grid gap-4',
                listClass: 'grid gap-4 xl:grid-cols-2',
            },
        ],
    },
];

export function getComposableViewHostLayout(host: ComposableHostViewDefinition): ComposableViewHostLayoutDefinition {
    const layout = COMPOSABLE_VIEW_HOST_LAYOUTS.find((candidate) => candidate.key === host.layout);

    if (layout === undefined) {
        throw new Error(`Composable host layout is not registered: ${host.layout}`);
    }

    if (!layout.supportedHostTypes.includes(host.type)) {
        throw new Error(`Composable host layout [${host.layout}] does not support host type [${host.type}].`);
    }

    return layout;
}
