<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $npTheme ?? 'gletscher' }}"
      @if($npAkzent ?? null) data-akzent="{{ $npAkzent }}" @endif
      @if(($npSchrift ?? 'normal') !== 'normal') data-schrift="{{ $npSchrift }}" @endif
      @if(($npSchriftart ?? 'standard') !== 'standard') data-schriftart="{{ $npSchriftart }}" @endif
      @if(($npBewegung ?? 'normal') !== 'normal') data-bewegung="{{ $npBewegung }}" @endif
      @if(($npEcken ?? 'rund') !== 'rund') data-ecken="{{ $npEcken }}" @endif
      @if(($npTransparenz ?? 'normal') !== 'normal') data-transparenz="{{ $npTransparenz }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – {{ config('app.name') }}</title>
    <script>
        (function () {
            try {
                const s = localStorage.getItem('theme');
                document.documentElement.classList.toggle('dark', s ? s === 'dark' : true);
            } catch(e) {}
        })();
    </script>
    @include('layouts._pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-akzent-eigen-stil :hex="$npAkzentEigen ?? null" />
</head>
<body class="font-sans antialiased bg-bg text-text min-h-screen flex flex-col items-center justify-center p-6">
    <div class="max-w-md w-full text-center space-y-4">
        <div class="text-7xl font-bold text-muted/30">{{ $code }}</div>
        <h1 class="text-2xl font-semibold text-text">{{ $title }}</h1>
        <p class="text-muted text-sm">{{ $message }}</p>
        <div class="pt-2">
            @auth
                <a href="{{ url('/') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-xl bg-accent text-white np-btn-primary">
                    {{ __('Zum Dashboard') }}
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-5 py-2.5 rounded-xl bg-accent text-white np-btn-primary">
                    {{ __('Zur Anmeldung') }}
                </a>
            @endauth
        </div>
    </div>
</body>
</html>
