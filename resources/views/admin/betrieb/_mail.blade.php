{{-- Mailversand als gruppierte Listen – in «Betrieb» und im Einrichtungsschritt «E-Mail». --}}
@php
    $wert = fn (string $k) => old($k, $werte[$k]);
    $fehlerAttr = fn (string $k) => $errors->has($k) ? 'aria-invalid=true aria-describedby='.$k.'-fehler' : '';
@endphp
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Server') }}</h2>
    <div class="np-karte np-gruppe">
        <x-einstellung :label="__('Server')" fuer="mail_host" name="mail_host"
                       :hinweis="$werte['source'] === 'env' && $werte['env_host'] ? __('Vorgabe der Installation: :host', ['host' => $werte['env_host']]) : null">
            <input id="mail_host" name="mail_host" type="text" maxlength="190" value="{{ $wert('mail_host') }}" placeholder="{{ $werte['env_host'] ?? '' }}"
                   autocomplete="off" spellcheck="false" class="np-feld w-72" {{ $fehlerAttr('mail_host') }}>
        </x-einstellung>
        <x-einstellung :label="__('Port')" fuer="mail_port" name="mail_port">
            <input id="mail_port" name="mail_port" type="number" min="1" max="65535" value="{{ $wert('mail_port') }}"
                   class="np-feld w-24 text-right tabular-nums" {{ $fehlerAttr('mail_port') }}>
        </x-einstellung>
        <x-einstellung :label="__('Verschlüsselung')" fuer="mail_encryption" name="mail_encryption">
            <select id="mail_encryption" name="mail_encryption" required class="np-feld w-72">
                @foreach(\App\Services\Notifications\MailSettings::ENCRYPTIONS as $schluessel => $text)
                    <option value="{{ $schluessel }}" @selected($wert('mail_encryption') === $schluessel)>{{ __($text) }}</option>
                @endforeach
            </select>
        </x-einstellung>
        <x-einstellung :label="__('Benutzer')" fuer="mail_username" name="mail_username">
            <input id="mail_username" name="mail_username" type="text" maxlength="190" value="{{ $wert('mail_username') }}"
                   autocomplete="off" spellcheck="false" class="np-feld w-72" {{ $fehlerAttr('mail_username') }}>
        </x-einstellung>
        <x-einstellung :label="__('Passwort')" fuer="mail_password" name="mail_password">
            <input id="mail_password" name="mail_password" type="password" maxlength="190" value=""
                   placeholder="{{ $werte['has_password'] ? __('gesetzt') : '' }}" autocomplete="new-password"
                   class="np-feld w-72" {{ $fehlerAttr('mail_password') }}>
        </x-einstellung>
        @if($werte['has_password'])
            <x-einstellung :label="__('Passwort entfernen')" fuer="mail_password_clear">
                <input id="mail_password_clear" name="mail_password_clear" type="checkbox" role="switch" value="1" class="np-schalter">
            </x-einstellung>
        @endif
    </div>
</section>

<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Absender') }}</h2>
    <div class="np-karte np-gruppe">
        <x-einstellung :label="__('Absenderadresse')" fuer="mail_from_address" name="mail_from_address">
            <input id="mail_from_address" name="mail_from_address" type="email" maxlength="190" value="{{ $wert('mail_from_address') }}" placeholder="{{ $werte['env_from'] ?? '' }}"
                   autocomplete="off" class="np-feld w-72" {{ $fehlerAttr('mail_from_address') }}>
        </x-einstellung>
        <x-einstellung :label="__('Anzeigename')" fuer="mail_from_name" name="mail_from_name">
            <input id="mail_from_name" name="mail_from_name" type="text" maxlength="120" value="{{ $wert('mail_from_name') }}" placeholder="{{ $werte['effective_from_name'] }}"
                   autocomplete="off" class="np-feld w-72" {{ $fehlerAttr('mail_from_name') }}>
        </x-einstellung>
    </div>
</section>

<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Testbetrieb') }}</h2>
    <div class="np-karte">
        <x-einstellung :label="__('Alle Mails umleiten an')" fuer="mail_redirect_to" name="mail_redirect_to">
            <input id="mail_redirect_to" name="mail_redirect_to" type="email" maxlength="190" value="{{ $wert('mail_redirect_to') }}"
                   autocomplete="off" class="np-feld w-72" {{ $fehlerAttr('mail_redirect_to') }}>
        </x-einstellung>
    </div>
    <p class="mt-2 px-1 text-xs text-muted">{{ __('Leer lassen, damit jede Mail an ihre Empfänger geht.') }}</p>
</section>
