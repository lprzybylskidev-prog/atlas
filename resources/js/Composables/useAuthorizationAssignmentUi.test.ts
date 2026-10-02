import { describe, expect, it } from 'vitest';

import { effectivePermissions, roleGrantedPermissions, roleGrantsByPermission } from './useAuthorizationAssignmentUi';

describe('authorization assignment state', () => {
    const rolePermissionMap = {
        operator: ['workspace.items.view', 'workspace.items.update'],
        reviewer: ['workspace.items.view', 'workspace.items.approve'],
    };

    it('keeps role-derived, direct, and effective permissions distinct', () => {
        const assignment = {
            role_names: ['operator', 'reviewer'],
            direct_permission_names: ['workspace.items.view', 'workspace.items.export'],
        };

        expect(roleGrantedPermissions(assignment, rolePermissionMap)).toEqual([
            'workspace.items.approve',
            'workspace.items.update',
            'workspace.items.view',
        ]);
        expect(roleGrantsByPermission(assignment, rolePermissionMap)).toEqual({
            'workspace.items.approve': ['reviewer'],
            'workspace.items.update': ['operator'],
            'workspace.items.view': ['operator', 'reviewer'],
        });
        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual([
            'workspace.items.approve',
            'workspace.items.export',
            'workspace.items.update',
            'workspace.items.view',
        ]);
        expect(assignment.direct_permission_names).toEqual(['workspace.items.view', 'workspace.items.export']);
    });

    it('recomputes effective permissions when selected roles change without creating direct grants', () => {
        const assignment = { role_names: ['operator'], direct_permission_names: ['workspace.items.view'] };

        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual(['workspace.items.update', 'workspace.items.view']);
        assignment.role_names = [];
        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual(['workspace.items.view']);
        expect(assignment.direct_permission_names).toEqual(['workspace.items.view']);
    });
});
