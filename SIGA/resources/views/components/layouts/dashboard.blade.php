<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('UTN System') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="app" id="app" x-data="{
        mobileOpen: false,
        collapsed: false,
        dark: localStorage.getItem('theme') === 'dark',
        fontLevel: localStorage.getItem('fontLevel') || 'a',
        init() {
            if (this.dark) document.documentElement.setAttribute('data-theme', 'dark');
            this.updateZoom(this.fontLevel);
        },
        toggleDark() {
            this.dark = !this.dark;
            localStorage.setItem('theme', this.dark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', this.dark ? 'dark' : 'light');
        },
        setFont(level) {
            this.fontLevel = level;
            localStorage.setItem('fontLevel', level);
            this.updateZoom(level);
        },
        updateZoom(level) {
            const zoom = level === 'aaa' ? 1.3 : level === 'aa' ? 1.15 : 1;
            document.documentElement.style.setProperty('--font-zoom', zoom);
        }
    }">

        <div class="backdrop" :class="{ 'show': mobileOpen }" @click="mobileOpen = false"></div>

        {{--
            Persistence is declared with the `x-persist` HTML attribute directly
            on <aside class="sidebar"> (inside the component itself), NOT with
            the `@persist(...) @endpersist` Blade directive here. That directive
            wraps its content in an extra `<div x-persist="sidebar">`, which
            becomes the *actual* flex child of `.app` instead of `<aside>` —
            breaking `align-items: stretch` so the sidebar's white background
            no longer reaches the bottom of the viewport (its height collapses
            to its content instead of stretching). This is a documented Livewire
            behavior (see livewire/livewire#5936); the attribute form avoids the
            extra wrapper entirely while still preserving the same DOM node
            (and its Alpine state) across wire:navigate transitions.
        --}}
        <x-siga.sidebar />

        <div class="main">
            <x-siga.topbar :title="$title ?? null" :subtitle="$subtitle ?? null" />

            <main class="content">
                {{ $slot }}
            </main>

            <footer class="footer">
                <span>© {{ date('Y') }} {{ __('National Technical University') }}. {{ __('All rights reserved.') }}</span>
                <span>{{ __('Marketing and Sales Management Directorate') }} · <a href="https://www.utn.ac.cr" target="_blank" rel="noopener">www.utn.ac.cr</a></span>
            </footer>
        </div>
    </div>

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist
    @fluxScripts
</body>

</html>
