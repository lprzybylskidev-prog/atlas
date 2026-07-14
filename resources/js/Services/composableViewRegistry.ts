import type {
    ComposableHostViewDefinition,
    ComposableHostViewKey,
    ComposableViewAvailability,
    ComposableViewElementDefinition,
    ComposableViewElementKey,
    ResolvedComposableHostView,
    ResolvedComposableViewElement,
} from '../Types/composable-view';

export const COMPOSABLE_HOST_VIEWS: readonly ComposableHostViewDefinition[] = [
    {
        key: 'app.dashboard',
        type: 'dashboard',
        layout: 'dashboard-sidebar',
        titleKey: 'views.dashboard.title',
        fallbackTitle: 'Dashboard operacyjny',
        acceptedElements: [],
    },
    {
        key: 'admin.system-status',
        type: 'operational-status',
        layout: 'operational-status',
        titleKey: 'views.admin.system_status.title',
        fallbackTitle: 'System status',
        acceptedElements: [],
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
