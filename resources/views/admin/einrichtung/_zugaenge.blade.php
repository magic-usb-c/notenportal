@if($zugaenge)
    <section class="np-karte overflow-hidden">
        <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-text">{{ __('Zugänge') }} · {{ count($zugaenge) }}</h3>
            <button type="button" onclick="window.print()" class="np-knopf np-knopf-sekundaer print:hidden">{{ __('Drucken') }}</button>
        </div>
        <div class="overflow-x-auto px-2 pb-2">
            <table class="np-tabelle text-sm">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Rolle') }}</th>
                        <th>{{ __('E-Mail') }}</th>
                        <th>{{ __('Startpasswort') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($zugaenge as $z)
                        <tr>
                            <td class="whitespace-nowrap">{{ $z['name'] }}</td>
                            <td class="text-muted">{{ $z['rolle'] }}</td>
                            <td>{{ $z['email'] }}</td>
                            <td class="select-all">{{ $z['passwort'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
