import type { Component } from 'vue';

export type ComposableHostViewType = 'dashboard' | 'overview' | 'manager-workspace' | 'operational-status' | 'module-landing';

export type ComposableHostViewKey = 'app.dashboard' | 'admin.system-status';

export type ComposableViewElementKey = string;

export type ComposableViewElementArea = 'main' | 'aside' | 'full';

export type ComposableViewAvailabilityReason = 'available' | 'permission-denied' | 'module-inactive' | 'active-team-required';

export interface ComposableViewElementRequirements {
    permissions: readonly string[];
    modules: readonly string[];
    activeTeam: 'none' | 'optional' | 'required';
}

export interface ComposableViewRealtimeContract {
    supported: boolean;
    channel: string | null;
}

export interface ComposableViewDataProviderResult<TData> {
    data: TData | null;
    empty: boolean;
}

export type ComposableViewDataProvider<TData = unknown> = () => Promise<ComposableViewDataProviderResult<TData>>;

export interface ComposableViewElementDefinition<TData = unknown> {
    key: ComposableViewElementKey;
    hostTypes: readonly ComposableHostViewType[];
    hostKeys: readonly ComposableHostViewKey[];
    titleKey: string;
    fallbackTitle: string;
    descriptionKey: string | null;
    fallbackDescription: string | null;
    requirements: ComposableViewElementRequirements;
    component: Component;
    dataProvider: ComposableViewDataProvider<TData>;
    cacheTtlSeconds: number | null;
    realtime: ComposableViewRealtimeContract;
    optional: boolean;
}

export interface ComposableViewAcceptedElement {
    elementKey: ComposableViewElementKey;
    area: ComposableViewElementArea;
    order: number;
    dimensions: {
        minHeightClass: string;
        spanClass: string;
    };
    structural: boolean;
}

export interface ComposableHostViewDefinition {
    key: ComposableHostViewKey;
    type: ComposableHostViewType;
    titleKey: string;
    fallbackTitle: string;
    acceptedElements: readonly ComposableViewAcceptedElement[];
}

export interface ComposableViewAvailability {
    elementKey: ComposableViewElementKey;
    reason: ComposableViewAvailabilityReason;
}

export interface ResolvedComposableViewElement {
    definition: ComposableViewElementDefinition;
    placement: ComposableViewAcceptedElement;
    availability: ComposableViewAvailability;
}

export interface ResolvedComposableHostView {
    host: ComposableHostViewDefinition;
    elements: readonly ResolvedComposableViewElement[];
    missingStructuralElementKeys: readonly ComposableViewElementKey[];
}
