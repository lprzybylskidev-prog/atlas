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
    };
    locale: string;
    flash: {
        success: string | null;
        error: string | null;
    };
}
