import type { FunctionalComponent } from 'vue';

export type ShellMode = 'app' | 'user' | 'manager' | 'admin';

export interface NavigationNode {
    key: string;
    label: string;
    icon: FunctionalComponent;
    active?: boolean;
    href?: string;
    external?: boolean;
    children?: NavigationNode[];
    visible?: boolean;
}

export interface ShellSubnavigationItem {
    key: string;
    label: string;
    href: string;
    icon?: FunctionalComponent;
    active?: boolean;
    visible?: boolean;
}
