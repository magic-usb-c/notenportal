{{-- Kurzanleitungen zum Beschaffen einer iCal-Adresse, eingeklappt (gleiches Muster wie andere Disclosures im Portal). --}}
<details class="group np-details rounded-lg border border-border bg-bg/40 px-3 py-2.5">
    <summary class="flex min-h-9 cursor-pointer list-none items-center gap-1.5 text-xs font-medium text-muted hover:text-text">
        <span class="inline-block transition-transform duration-200 group-open:rotate-90" aria-hidden="true">▸</span>
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
    <p class="mt-2.5 pt-2.5 border-t border-border text-xs text-muted">
        {{ __('Der Import ist aktuell nur lesend: Änderungen im Portal werden nicht in den Quellkalender zurückgeschrieben.') }}
    </p>
</details>
