export interface FrontendSourceFile {
    path: string;
    contents: string;
}

export interface FrontendSourceViolation {
    path: string;
    rule: string;
}

const legacyReferences = [
    ['Admin', 'Managers'].join('/'),
    ['', 'admin', 'managers'].join('/'),
    ['admin', 'managers', ''].join('.'),
    'navigation.managers',
    'breadcrumbs.managers',
    '/time-tracking/manager-report',
    'time-tracking.reports.manager',
    'RecordActions.vue',
    '<RecordActions',
    'UserTeamAccessWorkflow',
    'TeamMemberAccessWorkflow',
    'usePrivacyRetentionSubnavigation',
] as const;

function violation(path: string, rule: string): FrontendSourceViolation {
    return { path, rule };
}

export function findFrontendSourceViolations(files: FrontendSourceFile[]): FrontendSourceViolation[] {
    const violations: FrontendSourceViolation[] = [];

    for (const file of files) {
        const isVuePage = file.path.includes('/Pages/') && file.path.endsWith('.vue');

        for (const legacyReference of legacyReferences) {
            if (file.contents.includes(legacyReference)) {
                violations.push(violation(file.path, `legacy-reference:${legacyReference}`));
            }
        }

        if (/\bwindow\.(?:alert|confirm)\s*\(/u.test(file.contents)) {
            violations.push(violation(file.path, 'native-browser-dialog'));
        }

        if (/const\s+\w*[Ss]tatus\w*\s*:\s*Record<string,\s*string>/u.test(file.contents)) {
            violations.push(violation(file.path, 'local-status-map'));
        }

        if (/const\s+\w*(?:translations|dictionary|fallbackLabels)\w*\s*:\s*Record<string,\s*string>/iu.test(file.contents)) {
            violations.push(violation(file.path, 'local-translation-dictionary'));
        }

        if (!isVuePage) {
            continue;
        }

        if (!file.contents.includes('<Head')) {
            violations.push(violation(file.path, 'missing-browser-title'));
        }

        const usesAcceptedLayout =
            file.contents.includes('AppLayout') ||
            file.contents.includes('AuthLayout') ||
            file.contents.includes('ManagedProcessArea') ||
            file.path.endsWith('/Pages/Error.vue');

        if (!usesAcceptedLayout) {
            violations.push(violation(file.path, 'missing-accepted-layout'));
        }

        if (/<(?:input|select|textarea|table)\b/u.test(file.contents)) {
            violations.push(violation(file.path, 'page-owned-native-control-or-table'));
        }

        if (file.contents.includes('NavigationGroupDefinition') || file.contents.includes('ShellSubnavigation')) {
            violations.push(violation(file.path, 'page-owned-navigation'));
        }

        const technicalColumns = file.contents.matchAll(
            /\{\s*key:\s*['"](?:id|publicId|public_id|internalId|internal_id|eventType)['"][^\n}]*/gu,
        );

        for (const technicalColumn of technicalColumns) {
            const definition = technicalColumn[0];
            const isAdminOnly = file.path.includes('/Pages/Admin/');
            const isExplicitlyUnavailable = /(?:access:\s*['"]forbidden['"]|hidden:\s*true)/u.test(definition);

            if (!isAdminOnly && !isExplicitlyUnavailable) {
                violations.push(violation(file.path, 'unsafe-technical-column'));
            }
        }
    }

    return violations.sort((left, right) => `${left.path}:${left.rule}`.localeCompare(`${right.path}:${right.rule}`));
}
