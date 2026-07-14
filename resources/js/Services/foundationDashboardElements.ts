import type { ComposableViewElementDefinition } from '../Types/composable-view';
import DashboardActiveTeamElement from '../Components/ComposableView/Elements/DashboardActiveTeamElement.vue';
import DashboardContractElement from '../Components/ComposableView/Elements/DashboardContractElement.vue';
import DashboardIntroductionElement from '../Components/ComposableView/Elements/DashboardIntroductionElement.vue';
import DashboardMetricsElement from '../Components/ComposableView/Elements/DashboardMetricsElement.vue';
import DashboardNextStepsElement from '../Components/ComposableView/Elements/DashboardNextStepsElement.vue';

const foundationRequirements = {
    permissions: [] as const,
    modules: [] as const,
    activeTeam: 'optional' as const,
};

export const foundationDashboardElements: readonly ComposableViewElementDefinition[] = [
    {
        key: 'foundation.dashboard.introduction',
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: 'views.dashboard.elements.introduction.title',
        fallbackTitle: 'Pierwszy shell aplikacji',
        descriptionKey: null,
        fallbackDescription: null,
        requirements: foundationRequirements,
        component: DashboardIntroductionElement,
        dataProvider: async () => ({
            empty: false,
            data: {
                eyebrow: 'Atlas Operations',
                title: 'Pierwszy shell aplikacji',
                body: 'Ten ekran jest makietą operacyjną pod ocenę layoutu: sidebar, topbar, aktywny zespół, motywy i zachowanie mobilne.',
                status: 'Ready to review',
            },
        }),
        cacheTtlSeconds: 60,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'foundation.dashboard.metrics',
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: 'views.dashboard.elements.metrics.title',
        fallbackTitle: 'Skrót operacyjny',
        descriptionKey: null,
        fallbackDescription: null,
        requirements: foundationRequirements,
        component: DashboardMetricsElement,
        dataProvider: async () => ({
            empty: false,
            data: [
                { label: 'Kolejka operacji', value: '12', helper: 'Zadania oczekujące na wykonanie', icon: 'progress' },
                { label: 'Powiadomienia', value: '4', helper: 'Wymagają przeczytania', icon: 'inbox' },
                { label: 'Ostatnie zdarzenia', value: '8', helper: 'Bezpieczeństwo i sesje', icon: 'clock' },
                { label: 'Alerty', value: '1', helper: 'Wymaga weryfikacji admina', icon: 'alert' },
            ],
        }),
        cacheTtlSeconds: 60,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'foundation.dashboard.composable-view-contract',
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: 'views.dashboard.elements.composable_view_contract.title',
        fallbackTitle: 'Widok modułowy',
        descriptionKey: null,
        fallbackDescription: null,
        requirements: foundationRequirements,
        component: DashboardContractElement,
        dataProvider: async () => ({
            empty: false,
            data: [
                {
                    title: 'Element niezależny',
                    body: 'Każdy panel ma własne loading, empty, error i permission-denied state.',
                },
                {
                    title: 'Stały układ',
                    body: 'Brak drag and drop, personalizacji i ukrywania elementów w obecnym zakresie.',
                },
                {
                    title: 'Gotowe pod bramki',
                    body: 'Prawdziwe uprawnienia i module gates podłączymy w późniejszych fazach.',
                },
            ],
        }),
        cacheTtlSeconds: 60,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'foundation.dashboard.active-team',
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: 'views.dashboard.elements.active_team.title',
        fallbackTitle: 'Aktywny zespół',
        descriptionKey: null,
        fallbackDescription: null,
        requirements: foundationRequirements,
        component: DashboardActiveTeamElement,
        dataProvider: async () => ({
            empty: false,
            data: null,
        }),
        cacheTtlSeconds: 60,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: false,
    },
    {
        key: 'foundation.dashboard.next-steps',
        hostTypes: ['dashboard'],
        hostKeys: ['app.dashboard'],
        titleKey: 'views.dashboard.elements.next_steps.title',
        fallbackTitle: 'Następne kroki UI',
        descriptionKey: null,
        fallbackDescription: null,
        requirements: foundationRequirements,
        component: DashboardNextStepsElement,
        dataProvider: async () => ({
            empty: false,
            data: ['Centralne breadcrumbs z nazwanych tras.', 'Pełny composable-view contract.', 'Shared forms, dialogs, toasts i table wrapper.'],
        }),
        cacheTtlSeconds: 60,
        realtime: {
            supported: false,
            channel: null,
        },
        optional: true,
    },
];
