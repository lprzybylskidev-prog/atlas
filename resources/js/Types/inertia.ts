export interface AtlasUser {
    name: string;
    email: string;
    avatar: {
        color: string | null;
        imageUrl: string | null;
    };
}

export interface AtlasTeam {
    publicId: string;
    name: string;
}

export interface AtlasNotificationSummary {
    publicId: string;
    type: string;
    severity: string;
    title: string;
    body: string | null;
    deepLinkUrl: string | null;
    teamPublicId: string | null;
    read: boolean;
    createdAt: string;
    readAt: string | null;
}

export interface AtlasImpersonationState {
    active: boolean;
    sessionId: string | null;
    actorPublicId: string | null;
    userPublicId: string | null;
    userName: string | null;
    teamPublicId: string | null;
    teamName: string | null;
    reason: string | null;
    startedAt: string | null;
}

export interface AtlasPageProps {
    [key: string]: unknown;
    app: {
        name: string;
        release: {
            version: string;
            id: string;
        };
    };
    auth: {
        user: AtlasUser | null;
        availableAdminRoutes: string[];
        availableApplicationRoutes: string[];
        teams: {
            active: AtlasTeam | null;
            available: AtlasTeam[];
        };
        impersonation: AtlasImpersonationState;
    };
    locale: string;
    supportedLocales: string[];
    translations: Record<string, string>;
    preferences: {
        theme: 'light' | 'dark';
    };
    navigation: {
        breadcrumbs: {
            label: string;
            url: string | null;
        }[];
    };
    notifications: {
        unreadCount: number;
        latest: AtlasNotificationSummary[];
    };
    timeTracking: {
        activity: {
            enabled: boolean;
            endpoint: string;
            thresholdSeconds: number;
            warningSeconds: number;
        };
    };
    flash: {
        messages?: {
            type: 'success' | 'info' | 'warning' | 'error';
            key?: string;
            message?: string;
            description?: string | null;
            descriptionKey?: string;
            timeoutMs?: number | null;
            critical?: boolean;
        }[];
    };
}
