export const supportedLocales = ['pl', 'en'] as const;

export type SupportedLocale = (typeof supportedLocales)[number];

export const defaultLocale: SupportedLocale = 'pl';

export const translations = {
    pl: {
        'actions.change_language': 'Zmień język',
        'actions.close_navigation': 'Zamknij nawigację',
        'actions.collapse_sidebar': 'Zwiń panel boczny',
        'actions.expand_sidebar': 'Rozwiń panel boczny',
        'actions.logout': 'Wyloguj',
        'actions.open_navigation': 'Otwórz nawigację',
        'actions.switch_dark_theme': 'Włącz ciemny motyw',
        'actions.switch_light_theme': 'Włącz jasny motyw',
        'app.locale': 'Język',
        'app.version': 'Wersja',
        'auth.login.email': 'Email',
        'auth.login.head_title': 'Logowanie',
        'auth.login.password': 'Hasło',
        'auth.login.remember': 'Zapamiętaj mnie',
        'auth.login.submit': 'Zaloguj',
        'auth.login.submitting': 'Logowanie...',
        'auth.login.subtitle': 'Uzyskaj dostęp do swojego obszaru pracy, aktywnego zespołu oraz narzędzi operacyjnych Atlas.',
        'auth.login.title': 'Zaloguj się',
        'auth.shell.body':
            'Bezpieczne centrum pracy dla zespołów obsługujących sprawy, działania terenowe, komunikację i nadzór nad procesami windykacyjnymi.',
        'auth.shell.heading': 'Operacyjny pulpit windykacji',
        'navigation.admin': 'Admin',
        'navigation.aria.breadcrumb': 'Breadcrumb',
        'navigation.aria.main': 'Główna nawigacja',
        'navigation.aria.mobile': 'Mobilna nawigacja',
        'navigation.dashboard': 'Dashboard',
        'pages.dashboard.head_title': 'Dashboard',
        'pages.dashboard.section': 'Aplikacja',
        'pages.dashboard.title': 'Dashboard operacyjny',
        'team.active_short': 'Team',
        'team.active': 'Aktywny zespół',
        'team.current': 'Atlas Operations',
        'user.default_name': 'Atlas User',
        'user.menu': 'Menu użytkownika',
        'user.profile': 'Profil użytkownika',
    },
    en: {
        'actions.change_language': 'Change language',
        'actions.close_navigation': 'Close navigation',
        'actions.collapse_sidebar': 'Collapse sidebar',
        'actions.expand_sidebar': 'Expand sidebar',
        'actions.logout': 'Log out',
        'actions.open_navigation': 'Open navigation',
        'actions.switch_dark_theme': 'Enable dark theme',
        'actions.switch_light_theme': 'Enable light theme',
        'app.locale': 'Language',
        'app.version': 'Version',
        'auth.login.email': 'Email',
        'auth.login.head_title': 'Login',
        'auth.login.password': 'Password',
        'auth.login.remember': 'Remember me',
        'auth.login.submit': 'Log in',
        'auth.login.submitting': 'Logging in...',
        'auth.login.subtitle': 'Access your workspace, active team, and Atlas operational tools.',
        'auth.login.title': 'Log in',
        'auth.shell.body':
            'A secure work center for teams handling cases, field activities, communication, and debt collection process oversight.',
        'auth.shell.heading': 'Debt collection operations desk',
        'navigation.admin': 'Admin',
        'navigation.aria.breadcrumb': 'Breadcrumb',
        'navigation.aria.main': 'Main navigation',
        'navigation.aria.mobile': 'Mobile navigation',
        'navigation.dashboard': 'Dashboard',
        'pages.dashboard.head_title': 'Dashboard',
        'pages.dashboard.section': 'App',
        'pages.dashboard.title': 'Operations dashboard',
        'team.active_short': 'Team',
        'team.active': 'Active team',
        'team.current': 'Atlas Operations',
        'user.default_name': 'Atlas User',
        'user.menu': 'User menu',
        'user.profile': 'User profile',
    },
} as const;

export type TranslationKey = keyof (typeof translations)[typeof defaultLocale];

export function normalizeLocale(locale: string | undefined): SupportedLocale {
    return supportedLocales.includes(locale as SupportedLocale) ? (locale as SupportedLocale) : defaultLocale;
}
