@php
    $locale = app()->getLocale();
    $dir = config("mawid.locales.$locale.dir", 'ltr');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name') }}</title>

        {{-- Applique le thème avant le rendu pour éviter un flash clair/sombre. --}}
        <script>
            (function () {
                var fallback = @json(config('mawid.default_theme'));
                var theme;
                try { theme = localStorage.getItem('mawid-theme'); } catch (e) {}
                theme = theme || fallback;
                var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
