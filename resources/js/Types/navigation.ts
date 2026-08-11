import type { Component } from 'vue';

export type ShellMode = 'app' | 'user' | 'manager' | 'admin';

export interface NavigationNode {
    key: string;
    label: string;
    icon: Component;
    active?: boolean;
    href?: string;
    external?: boolean;
    children?: NavigationNode[];
    visible?: boolean;
}

export interface NavigationGroup {
    key: string;
    label: string;
    icon: Component;
    items: NavigationNode[];
}

export interface ShellSubnavigationItem {
    key: string;
    label: string;
    href: string;
    icon?: Component;
    active?: boolean;
    visible?: boolean;
}

export type ShellSubnavigationKey = 'audit' | 'managed-processes' | 'privacy-retention' | 'work-time';

export interface ShellModeLink {
    key: ShellMode;
    label: string;
    href: string;
    icon: Component;
    active: boolean;
}
