export interface AtlasUser {
    name: string;
    email: string;
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
        teams: {
            active: AtlasTeam | null;
            available: AtlasTeam[];
        };
    };
    locale: string;
    supportedLocales: string[];
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
    flash: {
        success: string | null;
        error: string | null;
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
