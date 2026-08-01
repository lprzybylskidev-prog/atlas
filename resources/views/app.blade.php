<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0f766e">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Atlas') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico">

        @fonts
        @routes(nonce: Vite::cspNonce())
        <script nonce="{{ Vite::cspNonce() }}">
            (() => {
                const cookieTheme = document.cookie
                    .split('; ')
                    .find((entry) => entry.startsWith('atlas_theme='))
                    ?.split('=')[1];
                const storedTheme = window.localStorage.getItem('atlas.theme') || cookieTheme;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = storedTheme === 'light' || storedTheme === 'dark' ? storedTheme : (prefersDark ? 'dark' : 'light');

                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-[#172121] antialiased dark:bg-[#101414] dark:text-emerald-50">
        @inertia
    </body>
</html>
