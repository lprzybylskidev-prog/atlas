export interface AtlasUser {
    name: string;
    email: string;
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
