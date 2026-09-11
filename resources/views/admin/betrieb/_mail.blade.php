@php
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $label = 'text-xs uppercase tracking-widest text-muted font-medium';
    $wert = fn (string $k) => old($k, $werte[$k]);
@endphp
<div class="flex flex-col gap-6">
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
            <label for="mail_host" class="{{ $label }}">Server</label>
            <input id="mail_host" name="mail_host" type="text" maxlength="190" value="{{ $wert('mail_host') }}" class="{{ $feld }}" autocomplete="off" placeholder="{{ $werte['env_host'] ?? '' }}">
            @if($werte['source'] === 'env' && $werte['env_host'])
                <p class="mt-1 text-xs text-muted">Vorgabe der Installation: {{ $werte['env_host'] }}</p>
            @endif
            @error('mail_host')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="mail_port" class="{{ $label }}">Port</label>
            <input id="mail_port" name="mail_port" type="number" min="1" max="65535" value="{{ $wert('mail_port') }}" class="{{ $feld }} tabular-nums">
            @error('mail_port')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <label for="mail_encryption" class="{{ $label }}">Verschlüsselung *</label>
            <select id="mail_encryption" name="mail_encryption" required class="{{ $feld }}">
                @foreach(\App\Services\Notifications\MailSettings::ENCRYPTIONS as $wert_key => $wert_label)
                    <option value="{{ $wert_key }}" @selected($wert('mail_encryption') === $wert_key)>{{ $wert_label }}</option>
                @endforeach
            </select>
            @error('mail_encryption')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="mail_username" class="{{ $label }}">Benutzer</label>
            <input id="mail_username" name="mail_username" type="text" maxlength="190" value="{{ $wert('mail_username') }}" class="{{ $feld }}" autocomplete="off">
            @error('mail_username')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="mail_password" class="{{ $label }}">Passwort</label>
            <input id="mail_password" name="mail_password" type="password" maxlength="190" value=""
                   placeholder="{{ $werte['has_password'] ? 'gesetzt' : '' }}" class="{{ $feld }}" autocomplete="new-password">
            @error('mail_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            @if($werte['has_password'])
                <label for="mail_password_clear" class="mt-2 flex items-center gap-2 text-xs text-muted">
                    <input id="mail_password_clear" name="mail_password_clear" type="checkbox" value="1" class="rounded border-border text-accent focus:ring-ring">
                    Passwort entfernen
                </label>
            @endif
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="mail_from_address" class="{{ $label }}">Absenderadresse</label>
            <input id="mail_from_address" name="mail_from_address" type="email" maxlength="190" value="{{ $wert('mail_from_address') }}" class="{{ $feld }}" placeholder="{{ $werte['env_from'] ?? '' }}" autocomplete="off">
            @error('mail_from_address')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="mail_from_name" class="{{ $label }}">Anzeigename</label>
            <input id="mail_from_name" name="mail_from_name" type="text" maxlength="120" value="{{ $wert('mail_from_name') }}" class="{{ $feld }}" placeholder="{{ $werte['effective_from_name'] }}" autocomplete="off">
            @error('mail_from_name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="mail_redirect_to" class="{{ $label }}">Alle Mails umleiten an <span class="normal-case font-normal text-muted">(Testbetrieb)</span></label>
        <input id="mail_redirect_to" name="mail_redirect_to" type="email" maxlength="190" value="{{ $wert('mail_redirect_to') }}" class="{{ $feld }}" autocomplete="off">
        @error('mail_redirect_to')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
</div>
