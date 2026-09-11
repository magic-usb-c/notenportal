@if($zugaenge)
    <section class="rounded-2xl border border-border bg-card overflow-hidden">
        <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-text">{{ __('Zugänge') }} · {{ count($zugaenge) }}</h3>
            <button type="button" onclick="window.print()" class="inline-flex items-center px-3 min-h-9 rounded-lg glass-btn text-text text-sm print:hidden">{{ __('Drucken') }}</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-text">
                <thead class="text-xs text-muted">
                    <tr class="border-b border-border">
                        <th class="text-left px-5 py-2 font-medium">{{ __('Name') }}</th>
                        <th class="text-left px-3 py-2 font-medium">{{ __('Rolle') }}</th>
                        <th class="text-left px-3 py-2 font-medium">{{ __('E-Mail') }}</th>
                        <th class="text-left px-5 py-2 font-medium">{{ __('Startpasswort') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($zugaenge as $z)
                        <tr>
                            <td class="px-5 py-2 whitespace-nowrap">{{ $z['name'] }}</td>
                            <td class="px-3 py-2 text-muted">{{ $z['rolle'] }}</td>
                            <td class="px-3 py-2">{{ $z['email'] }}</td>
                            <td class="px-5 py-2 font-mono select-all">{{ $z['passwort'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
