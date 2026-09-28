// Nimmt die Screenshots für docs/systemdoku/ auf.
//
// Aufruf:
//   NP_DOKU_PW=<passwort> node tools/systemdoku-bilder.mjs <baseUrl> [ausgabe-dir]
//
// Nur gegen eine Demoinstanz richten – die Bilder landen in der Dokumentation und
// dürfen keine echten Personen zeigen. Das Passwort kommt ausschliesslich aus der
// Umgebungsvariable NP_DOKU_PW, nie als Argument (Argumente stehen in der Prozessliste).
//
// Voraussetzung: playwright mit Chromium. Liegt es ausserhalb des Projekts, das Skript
// von dort aus aufrufen – Node löst Pakete relativ zum Skriptpfad auf.

import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.argv[2];
const ausgabe = process.argv[3] ?? 'docs/systemdoku/bilder';
const passwort = process.env.NP_DOKU_PW;

if (!baseUrl || !passwort) {
    console.error('Aufruf: NP_DOKU_PW=<passwort> node tools/systemdoku-bilder.mjs <baseUrl> [ausgabe-dir]');
    process.exit(1);
}

// Konto je Rolle. Die Demoinstanz legt diese Adressen an; alle drei teilen dasselbe Passwort.
const LAEUFE = [
    { konto: null, pfade: ['/login'] },
    {
        konto: 'anna.graf@demo.example',
        pfade: [
            '/dashboard', '/grades', '/grades/create', '/grades/calculator', '/grades/import',
            '/modules', '/exams', '/documents',
            '/settings/profile', '/settings/calendar', '/settings/notifications', '/settings/data',
        ],
    },
    {
        konto: 'david.steiner@demo.example',
        pfade: ['/trainer', '/trainer/learners', '/trainer/learners/3', '/trainer/exams'],
    },
    {
        konto: 'laura.frei@demo.example',
        pfade: [
            '/admin', '/admin/setup', '/admin/users', '/admin/learners',
            '/admin/master-data/professions', '/admin/master-data/subjects',
            '/admin/master-data/categories', '/admin/master-data/semesters',
            '/admin/master-data/modules', '/admin/master-data/modules/catalog',
            '/admin/operations', '/admin/notifications', '/admin/reports/grades',
            '/admin/activity', '/admin/mail-log',
        ],
    },
];

// Der schwebende Meldeknopf und sein Hinweis gehören nicht in eine Anleitung.
const AUFRAEUMEN = `
  [x-data*="feedback"], #feedback-knopf, .np-feedback-knopf { display: none !important; }
  [data-feedback-hinweis], [x-ref="feedbackHinweis"] { display: none !important; }
`;

fs.mkdirSync(ausgabe, { recursive: true });
const browser = await chromium.launch();

for (const lauf of LAEUFE) {
    const context = await browser.newContext({
        viewport: { width: 1366, height: 860 },
        locale: 'de-CH',
        timezoneId: 'Europe/Zurich',
        colorScheme: 'light',
    });
    await context.addInitScript(() => {
        try {
            localStorage.setItem('theme', 'light');
            localStorage.setItem('np.feedback.hint', '1');
        } catch (e) { /* privates Fenster */ }
    });

    const page = await context.newPage();

    if (lauf.konto) {
        await page.goto(`${baseUrl}/login`);
        await page.fill('input[name="email"]', lauf.konto);
        await page.fill('input[name="password"]', passwort);
        await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
    }

    for (const p of lauf.pfade) {
        const res = await page.goto(baseUrl + p, { waitUntil: 'networkidle' });
        await page.addStyleTag({ content: AUFRAEUMEN });
        await page.waitForTimeout(500);
        const name = `${p.replace(/^\/|\/$/g, '').replace(/[^a-z0-9]+/gi, '-') || 'start'}.png`;
        await page.screenshot({ path: path.join(ausgabe, name) });
        console.log(`${res?.status()} ${p} → ${name}`);
    }

    await context.close();
}

await browser.close();
