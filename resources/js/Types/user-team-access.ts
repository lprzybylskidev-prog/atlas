import type { FormSelectOption } from '../Components/Form/FormSelect.vue';

export type AssignmentSource = 'manual' | 'package' | 'copy';

export interface AuthorizationAssignmentOption {
    value: string;
    label: string;
    description?: string;
}

export interface UserTeamAccessPackage {
    publicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    label: string;
    initialRoles: string[];
    directPermissions: string[];
    templatePermissions: string[];
}

export interface UserTeamAccessCopySource {
    publicId: string;
    name: string;
    email: string;
    assignmentsByTeam: Record<string, { roles: string[]; directPermissions: string[] }>;
}

export interface UserTeamAccessAssignment {
    team_public_id: string;
    source: AssignmentSource;
    onboarding_package: string;
    copy_authorization_from_user: string;
    role_names: string[];
    direct_permission_names: string[];
    inactivity_timeout_minutes: string;
    session_max_lifetime_minutes: string;
    break_daily_limit_minutes: string;
    break_maximum_single_minutes: string;
    teamName?: string;
    reason: string;
    removal_reason: string;
}

export interface TeamPolicyDefaults {
    inactivityTimeoutMinutes: number;
    sessionMaxLifetimeMinutes: number;
    breakDailyLimitMinutes: number;
    breakMaximumSingleMinutes: number;
}

export interface UserTeamAccessSavePayload {
    assignment: UserTeamAccessAssignment;
    index: number;
}

export interface UserTeamAccessRemovePayload {
    assignment: UserTeamAccessAssignment;
    index: number;
}

export type TeamSelectOption = FormSelectOption;
