import { describe, expect, it } from 'vitest';

import { effectivePermissions, roleGrantedPermissions, roleGrantsByPermission } from './useAuthorizationAssignmentUi';

describe('authorization assignment state', () => {
    const rolePermissionMap = {
        collector: ['cases.view', 'cases.update'],
        reviewer: ['cases.view', 'cases.approve'],
    };

    it('keeps role-derived, direct, and effective permissions distinct', () => {
        const assignment = {
            role_names: ['collector', 'reviewer'],
            direct_permission_names: ['cases.view', 'cases.export'],
        };

        expect(roleGrantedPermissions(assignment, rolePermissionMap)).toEqual(['cases.approve', 'cases.update', 'cases.view']);
        expect(roleGrantsByPermission(assignment, rolePermissionMap)).toEqual({
            'cases.approve': ['reviewer'],
            'cases.update': ['collector'],
            'cases.view': ['collector', 'reviewer'],
        });
        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual([
            'cases.approve',
            'cases.export',
            'cases.update',
            'cases.view',
        ]);
        expect(assignment.direct_permission_names).toEqual(['cases.view', 'cases.export']);
    });

    it('recomputes effective permissions when selected roles change without creating direct grants', () => {
        const assignment = { role_names: ['collector'], direct_permission_names: ['cases.view'] };

        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual(['cases.update', 'cases.view']);
        assignment.role_names = [];
        expect(effectivePermissions(assignment, rolePermissionMap)).toEqual(['cases.view']);
        expect(assignment.direct_permission_names).toEqual(['cases.view']);
    });
});
