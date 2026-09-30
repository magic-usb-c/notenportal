{{-- Kurzanleitungen zum Beschaffen einer iCal-Adresse, eingeklappt (gleiches Muster wie andere Disclosures im Portal). --}}
<details class="np-details px-1">
    <summary class="inline-flex min-h-6 cursor-pointer list-none items-center gap-1 text-xs font-medium text-accent-text">
        <x-symbol name="chevron-right" strich="2" class="np-chevron size-3" />
        {{ __('Woher bekomme ich die Adresse?') }}
    </summary>
    <dl class="mt-2.5 flex flex-col gap-2.5 text-xs text-muted">
        <div>
            <dt class="font-medium text-text">{{ __('Nextcloud') }}</dt>
            <dd>{{ __('Kalender teilen → Link kopieren. Der Freigabelink wird automatisch in die Exportadresse umgeschrieben.') }}</dd>
        </div>
        <div>
            <dt class="font-medium text-text">{{ __('Outlook') }}</dt>
            <dd>{{ __('Kalender → Freigeben → Veröffentlichen → ICS-Link kopieren.') }}</dd>
        </div>
        <div>
            <dt class="font-medium text-text">{{ __('Google Kalender') }}</dt>
            <dd>{{ __('Einstellungen des Kalenders → «Privatadresse im iCal-Format».') }}</dd>
        </div>
        <div>
            <dt class="font-medium text-text">{{ __('Schulnetz') }}</dt>
            <dd>{{ __('iCal-Adresse aus dem Schulnetz hinterlegen: Prüfungen, Termine und Lektionen erscheinen dann in der Agenda.') }}</dd>
        </div>
    </dl>
    <p class="mt-2.5 text-xs text-muted">
        {{ __('Der Import ist aktuell nur lesend: Änderungen im Portal werden nicht in den Quellkalender zurückgeschrieben.') }}
    </p>
</details>
