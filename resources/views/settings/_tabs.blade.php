{{-- Tab-Leiste der Seite «Einstellungen»: eigene Route je Tab (keine Alpine-Tabs), nur sichtbar
     sobald die jeweilige Route existiert – so bleibt jeder Ausbauschritt für sich lauffähig. --}}
@php
    $npEinstellungenTabs = [
        ['route' => 'settings.profile', 'label' => __('Profil')],
        ['route' => 'notifications.settings', 'label' => __('Benachrichtigungen')],
        ['route' => 'settings.calendar', 'label' => __('Kalender')],
        ['route' => 'settings.data', 'label' => __('Daten')],
    ];
@endphp
<nav aria-label="{{ __('Bereiche') }}" class="flex gap-6 overflow-x-auto border-b border-border text-sm">
    @foreach($npEinstellungenTabs as $npTab)
        @continue(! \Illuminate\Support\Facades\Route::has($npTab['route']))
        @php($npTabAktiv = request()->routeIs($npTab['route']))
        <a href="{{ route($npTab['route']) }}" @if($npTabAktiv) aria-current="page" @endif
           class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 font-medium whitespace-nowrap
                  {{ $npTabAktiv ? 'border-accent text-text' : 'border-transparent text-muted hover:border-border-strong/50 hover:text-text' }}">
            {{ $npTab['label'] }}
        </a>
    @endforeach
</nav>
