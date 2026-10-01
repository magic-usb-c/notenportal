{{-- Hell/Dunkel vor dem CSS setzen, damit nichts flackert. Angemeldet gilt die Wahl im Profil: hell, dunkel
     oder wie das Gerät – dann auch live, wenn macOS am Abend umschaltet. Der Browser merkt sich die Wahl
     (localStorage), damit Anmeldung und Fehlerseiten nach dem Abmelden gleich aussehen. --}}
@php
    $darstellungNutzer = rescue(fn () => auth()->user(), null, false);
    $darstellungWahl = $darstellungNutzer?->darstellung ?? 'system';
@endphp
<script>
    (function () {
        const wurzel = document.documentElement;
        window.npDarstellung = @js($darstellungWahl);
        try {
            @if($darstellungNutzer)
                if (window.npDarstellung === 'system') localStorage.removeItem('theme');
                else localStorage.setItem('theme', window.npDarstellung === 'dunkel' ? 'dark' : 'light');
            @else
                const lokal = localStorage.getItem('theme');
                if (lokal) window.npDarstellung = lokal === 'dark' ? 'dunkel' : 'hell';
            @endif
        } catch (e) {}
        const geraet = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        window.npDarstellungAnwenden = function () {
            const dunkel = window.npDarstellung === 'system' ? !!(geraet && geraet.matches) : window.npDarstellung === 'dunkel';
            wurzel.classList.toggle('dark', dunkel);
        };
        window.npDarstellungAnwenden();
        if (geraet) geraet.addEventListener('change', window.npDarstellungAnwenden);
    })();
</script>
