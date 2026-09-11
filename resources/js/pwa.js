// Installierbare Web-App (Block AC): Service-Worker-Registrierung (nur Produktion, nur
// HTTPS oder localhost) und Cache-Leerung beim Abmelden (geteilte Geräte), siehe public/sw.js.

export function registrierePwa() {
    if (!('serviceWorker' in navigator)) return;

    const sichereAdresse = location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);
    if (import.meta.env.PROD && sichereAdresse) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }

    // Logout-Formulare (Navigation, mobiles Menü): SW-Caches leeren, bevor die Sitzung endet.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (new URL(form.action, location.href).pathname !== '/logout') return;

        navigator.serviceWorker.controller?.postMessage({ type: 'ABMELDEN' });
    });
}
