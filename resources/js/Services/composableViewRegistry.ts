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
                elementKey: 'admin.system-status.failed-jobs',
                area: 'main',
                order: 20,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.module-activation',
                area: 'main',
                order: 30,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.readiness',
                area: 'full',
                order: 100,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
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
        key: 'admin.system-status.failed-jobs',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.failed_jobs.title',
        fallbackTitle: 'Failed jobs',
        descriptionKey: 'views.admin.system_status.failed_jobs.description',
        fallbackDescription: 'Queue failures waiting for operator review.',
        requirements: {
            permissions: ['admin.system-status.failed-jobs'],
            modules: ['health'],
            activeTeam: 'required',
        },
        component: SystemStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/failed-jobs', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed job diagnostics could not be loaded.');
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
