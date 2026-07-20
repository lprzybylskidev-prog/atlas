import type {
    ComposableHostViewDefinition,
    ComposableHostViewKey,
    ComposableViewAvailability,
    ComposableViewElementDefinition,
    ComposableViewElementKey,
    ResolvedComposableHostView,
    ResolvedComposableViewElement,
} from '../Types/composable-view';
import ModulesStatusCard from '../Components/ComposableView/Elements/ModulesStatusCard.vue';
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
                area: 'full',
                order: 10,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.readiness',
                area: 'full',
                order: 20,
                dimensions: {
                    minHeightClass: 'min-h-44',
                    spanClass: '',
                },
                structural: true,
            },
            {
                elementKey: 'admin.system-status.modules',
                area: 'full',
                order: 30,
                dimensions: {
                    minHeightClass: 'min-h-80',
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
        key: 'admin.system-status.modules',
        hostTypes: ['operational-status'],
        hostKeys: ['admin.system-status'],
        titleKey: 'views.admin.system_status.modules.title',
        fallbackTitle: 'Modules',
        descriptionKey: 'views.admin.system_status.modules.description',
        fallbackDescription: 'Deployed modules, activation state, dependencies, and module-owned issues.',
        requirements: {
            permissions: ['admin.system-status.modules'],
            modules: ['authorization'],
            activeTeam: 'required',
        },
        component: ModulesStatusCard,
        dataProvider: async () => {
            const response = await fetch('/admin/system-status/modules', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Module diagnostics could not be loaded.');
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
