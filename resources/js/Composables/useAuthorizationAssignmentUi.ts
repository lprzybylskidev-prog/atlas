export interface AuthorizationAssignmentState {
    role_names: string[];
    direct_permission_names: string[];
}

export function roleGrantedPermissions(assignment: AuthorizationAssignmentState, rolePermissionMap: Record<string, string[]>): string[] {
    return Array.from(new Set(assignment.role_names.flatMap((role) => rolePermissionMap[role] ?? []))).sort();
}

export function effectivePermissions(assignment: AuthorizationAssignmentState, rolePermissionMap: Record<string, string[]>): string[] {
    return Array.from(new Set([...roleGrantedPermissions(assignment, rolePermissionMap), ...assignment.direct_permission_names])).sort();
}

export function authorizationListLabel(values: string[], labels: Map<string, string>, emptyLabel: string): string {
    return values.length === 0 ? emptyLabel : values.map((value) => labels.get(value) ?? value).join(', ');
}

export function selectedCountLabel(selected: number, total: number, label: (replacements: Record<string, number>) => string): string {
    return label({ selected, total });
}
