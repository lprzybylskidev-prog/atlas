import type {
    ComposableHostViewDefinition,
    ComposableHostViewKey,
    ComposableViewAvailability,
    ComposableViewElementDefinition,
    ComposableViewElementKey,
    ResolvedComposableHostView,
    ResolvedComposableViewElement,
} from '../Types/composable-view';
import SystemStatusCard from '../Components/ComposableView/Elements/SystemStatusCard.vue';

export const COMPOSABLE_HOST_VIEWS: readonly ComposableHostViewDefinition[] = [
    {
        key: 'app.dashboard',
        type: 'dashboard',
        layout: 'dashboard-sidebar',
        titleKey: 'views.dashboard.title',
        fallbackTitle: 'Pulpit',
        acceptedElements: [],
    },
    {
        key: 'admin.system-status',
        type: 'operational-status',
        layout: 'operational-status',
        titleKey: 'views.admin.system_status.title',
        fallbackTitle: 'Dashboard',
        acceptedElements: [
            {
                elementKey: 'admin.system-status.identity',
                area: 'main',
                order: 20,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.release',
                area: 'main',
                order: 10,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.search',
                area: 'main',
                order: 20,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: false,
            },
            {
                elementKey: 'admin.system-status.scheduler',
                area: 'main',
                order: 40,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.module-activation',
                area: 'main',
                order: 50,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.readiness',
                area: 'main',
                order: 30,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: 'lg:col-span-2',
                },
                structural: true,
            },
        ],
    },
];

export const SYSTEM_STATUS_ELEMENTS: readonly ComposableViewElementDefinition[] = [
    {
        key: 'admin.system-status.release',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.release.title',
        fallbackTitle: 'Release',
        descriptionKey: 'views.admin.system_status.release.description',
        fallbackDescription: 'Application version, release identifier, and last deployment metadata.',
        requirements: {
            permissions: ['admin.system-status.release'],
            modules: ['health'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/release', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Release status could not be loaded.');
            }

            return (await response.json()) as { data: unknown; empty: boolean };
        },
        cacheTtlSeconds: 300,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'admin.system-status.identity',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.identity.title',
        fallbackTitle: 'Identity module',
        descriptionKey: 'views.admin.system_status.identity.description',
        fallbackDescription: 'Core authentication and account access.',
        requirements: {
            permissions: ['admin.system-status'],
            modules: ['identity'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => ({
            data: {
                label: 'Identity',
                value: 'Available',
                description: 'The deployed Identity module is available for the active team context.',
            },
            empty: false,
        }),
        cacheTtlSeconds: 30,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'admin.system-status.readiness',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.readiness.title',
        fallbackTitle: 'Readiness',
        descriptionKey: 'views.admin.system_status.readiness.description',
        fallbackDescription: 'Blocking and degraded operational dependencies.',
        requirements: {
            permissions: ['admin.system-status.readiness'],
            modules: ['health'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/readiness', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Readiness diagnostics could not be loaded.');
            }

            return (await response.json()) as { data: unknown; empty: boolean };
        },
        cacheTtlSeconds: 30,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'admin.system-status.scheduler',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.scheduler.title',
        fallbackTitle: 'Scheduler heartbeat',
        descriptionKey: 'views.admin.system_status.scheduler.description',
        fallbackDescription: 'Freshness of the application scheduler process.',
        requirements: {
            permissions: ['admin.system-status.scheduler'],
            modules: ['authorization'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/scheduler', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Scheduler heartbeat status could not be loaded.');
            }

            return (await response.json()) as { data: unknown; empty: boolean };
        },
        cacheTtlSeconds: 30,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'admin.system-status.module-activation',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.module_activation.title',
        fallbackTitle: 'Module activation schedules',
        descriptionKey: 'views.admin.system_status.module_activation.description',
        fallbackDescription: 'Failed scheduled activation changes requiring operator review.',
        requirements: {
            permissions: ['admin.system-status.module-activation'],
            modules: ['authorization'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/module-activation', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Module activation schedule diagnostics could not be loaded.');
            }

            return (await response.json()) as { data: unknown; empty: boolean };
        },
        cacheTtlSeconds: 30,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'admin.system-status.search',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.search.title',
        fallbackTitle: 'Search module',
        descriptionKey: 'views.admin.system_status.search.description',
        fallbackDescription: 'Optional search projection status.',
        requirements: {
            permissions: ['admin.system-status'],
            modules: ['search'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => ({
            data: {
                label: 'Search',
                value: 'Available',
                description: 'The optional Search module is deployed and available.',
            },
            empty: false,
        }),
        cacheTtlSeconds: 30,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: true,
    },
];

export function resolveComposableHostView(
    hostKey: ComposableHostViewKey,
    elementDefinitions: readonly ComposableViewElementDefinition[],
    availability: readonly ComposableViewAvailability[] = [],
): ResolvedComposableHostView {
    const host = COMPOSABLE_HOST_VIEWS.find((definition) => definition.key === hostKey);

    if (host === undefined) {
        throw new Error(`Composable host view is not registered: ${hostKey}`);
    }

    return resolveComposableHostViewDefinition(host, elementDefinitions, availability);
}

export function resolveComposableHostViewDefinition(
    host: ComposableHostViewDefinition,
    elementDefinitions: readonly ComposableViewElementDefinition[],
    availability: readonly ComposableViewAvailability[] = [],
): ResolvedComposableHostView {
    const definitionsByKey = new Map<ComposableViewElementKey, ComposableViewElementDefinition>(
        elementDefinitions.map((definition) => [definition.key, definition]),
    );
    const availabilityByKey = new Map<ComposableViewElementKey, ComposableViewAvailability>(
        availability.map((entry) => [entry.elementKey, entry]),
    );
    const missingStructuralElementKeys: ComposableViewElementKey[] = [];
    const elements: ResolvedComposableViewElement[] = [];

    for (const placement of [...host.acceptedElements].sort((left, right) => left.order - right.order)) {
        const definition = definitionsByKey.get(placement.elementKey);

        if (definition === undefined) {
            if (placement.structural) {
                missingStructuralElementKeys.push(placement.elementKey);
            }

            continue;
        }

        const elementAvailability = availabilityByKey.get(definition.key) ?? {
            elementKey: definition.key,
            reason: 'available',
        };

        if (elementAvailability.reason !== 'available' && definition.optional) {
            continue;
        }

        elements.push({
            definition,
            placement,
            availability: elementAvailability,
        });
    }

    return {
        host,
        elements,
        missingStructuralElementKeys,
    };
}
