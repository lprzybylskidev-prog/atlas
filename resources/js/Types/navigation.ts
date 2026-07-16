import type { FunctionalComponent } from 'vue';

export interface NavigationNode {
    key: string;
    label: string;
    icon: FunctionalComponent;
    active?: boolean;
    href?: string;
    children?: NavigationNode[];
    visible?: boolean;
}
