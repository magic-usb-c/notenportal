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
<nav aria-label="{{ __('Bereiche') }}" class="np-segment">
    @foreach($npEinstellungenTabs as $npTab)
        @continue(! \Illuminate\Support\Facades\Route::has($npTab['route']))
        <a href="{{ route($npTab['route']) }}" @if(request()->routeIs($npTab['route'])) aria-current="page" @endif>{{ $npTab['label'] }}</a>
    @endforeach
</nav>
