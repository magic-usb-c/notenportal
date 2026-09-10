// Befehlspalette (Ctrl/Cmd+K): Seiten, Aktionen und – für Admin/BB – Lernende finden.
export function registriereSuche(Alpine) {
    Alpine.data('npSuche', (cfg) => ({
        offen: false,
        q: '',
        index: 0,
        treffer: [],
        timer: null,

        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.oeffnen();
                }
            });
            this.$watch('q', () => this.suchen());
        },

        oeffnen() {
            this.offen = true;
            this.q = '';
            this.treffer = [];
            this.index = 0;
            this.$nextTick(() => this.$refs.eingabe?.focus());
        },

        get lokal() {
            const q = this.q.trim().toLowerCase();
            const alle = cfg.eintraege;
            return q ? alle.filter((e) => (e.label + ' ' + (e.gruppe ?? '')).toLowerCase().includes(q)) : alle;
        },

        get liste() {
            return [...this.treffer, ...this.lokal].slice(0, 12);
        },

        suchen() {
            this.index = 0;
            clearTimeout(this.timer);
            if (!cfg.url || this.q.trim().length < 2) {
                this.treffer = [];
                return;
            }
            this.timer = setTimeout(async () => {
                try {
                    const res = await fetch(`${cfg.url}?q=${encodeURIComponent(this.q.trim())}`, { headers: { Accept: 'application/json' } });
                    this.treffer = res.ok ? await res.json() : [];
                } catch {
                    this.treffer = [];
                }
            }, 180);
        },

        taste(e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); this.index = Math.min(this.index + 1, this.liste.length - 1); }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.index = Math.max(this.index - 1, 0); }
            if (e.key === 'Enter' && this.liste[this.index]) { e.preventDefault(); window.location.href = this.liste[this.index].url; }
        },
    }));
}
