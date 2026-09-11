@props(['fehler' => null])

{{--
    Notendrawer für «+ Note» (Erfassen) und «Bearbeiten»: von jeder Seite per Event zu öffnen
    ($dispatch('np-note', { url, titel })), lädt das Formular per Fetch nach. Ein Validierungsfehler
    (serverseitiges Redirect zurück auf die aufrufende Seite) zeigt das Formular sofort mit alter
    Eingabe, ohne erneut zu laden – dafür wird $fehler (App\Services\Noten\NoteService::drawerNachFehler)
    auf der jeweiligen Seite mitgegeben.
--}}
<div x-data="npNoteDrawer(@js(['titel' => $fehler['titel'] ?? 'Neue Note', 'server' => (bool) $fehler]))"
     x-on:np-note.window="oeffnen($event.detail.url, $event.detail.titel)">
    <x-drawer name="note" titel="Note">
        <x-slot:kopf><span x-text="titel">{{ $fehler['titel'] ?? 'Neue Note' }}</span></x-slot:kopf>
        @if($fehler)
            <template x-if="server">
                <div>@include('lernender.noten.partials.formular', $fehler['daten'])</div>
            </template>
        @endif
        <div x-show="! server" x-html="html"></div>
        <div x-show="laedt" x-cloak class="flex flex-col gap-4" aria-hidden="true">
            <div class="mx-auto h-20 w-36 rounded-xl bg-surface-2"></div>
            <div class="h-10 rounded-lg bg-surface-2"></div>
            <div class="h-10 rounded-lg bg-surface-2"></div>
        </div>
    </x-drawer>
</div>

<script>
    function npNoteDrawer(start) {
        return {
            titel: start.titel,
            server: start.server,
            html: '',
            laedt: false,
            init() {
                if (this.server) this.$nextTick(() => this.$dispatch('open-drawer', 'note'));
            },
            async oeffnen(url, titel) {
                this.titel = titel;
                this.server = false;
                this.html = '';
                this.laedt = true;
                this.$dispatch('open-drawer', 'note');
                try {
                    const ziel = new URL(url, window.location.origin);
                    ziel.searchParams.set('drawer', '1');
                    // X-Requested-With: die Session merkt sich den Fragment-Abruf nicht als «vorherige URL»
                    const res = await fetch(ziel, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok || res.redirected) throw new Error(res.status);
                    this.html = await res.text();
                    this.$nextTick(() => this.$root.querySelector('#note_wert')?.focus());
                } catch (e) {
                    window.location.href = url;
                } finally {
                    this.laedt = false;
                }
            },
        };
    }
</script>
