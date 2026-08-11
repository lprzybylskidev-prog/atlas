import type { Component } from 'vue';

import type { AtlasAction } from './actions';

export type CrudView = 'index' | 'create' | 'show' | 'edit';

export interface CrudContract {
    resourceKey: string;
    view: CrudView;
    title: string;
    headTitle: string;
    icon: Component;
    objectLabel?: string;
    status?: string;
    auditHref?: string;
    actions: AtlasAction<undefined>[];
}

export interface FormSaveScope {
    key: string;
    label: string;
    transaction: 'single' | 'independent';
}

export interface FormContract {
    scope: FormSaveScope;
    cancelHref: string;
    dirty: boolean;
    processing: boolean;
    submitLabel: string;
    processingLabel: string;
}
