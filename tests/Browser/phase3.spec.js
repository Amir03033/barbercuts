import { expect, test } from '@playwright/test';
import { createShop, expectInputsAtLeast16px, expectNoHorizontalScroll, expectTapTargets, screenshot } from './helpers.js';

async function check(page, testInfo, name) {
    await page.evaluate(() => document.fonts.ready);
    await expectNoHorizontalScroll(page);
    await expectInputsAtLeast16px(page);
    await expectTapTargets(page);
    await screenshot(page, testInfo, 'fase-3', name);
}

test('de kapper boekt telefonisch, neemt een inloop aan en rekent af op een telefoon', async ({ page }, testInfo) => {
    test.setTimeout(150_000);

    const id = `${Date.now()}${testInfo.project.name === 'iphone-se' ? 's' : 'l'}`;
    await createShop(page, id);

    // De bottom nav werkt nu helemaal
    await page.goto('/app');
    await expect(page.getByRole('navigation', { name: 'Hoofdmenu' })).toBeInViewport({ ratio: 1 });
    await expect(page.getByText('Omzet vandaag')).toBeVisible();
    await check(page, testInfo, 'vandaag-leeg');

    // ── Telefonische boeking: nieuwe klant, eerstvolgende tijd, opslaan ──
    await page.getByRole('link', { name: 'Nieuwe afspraak' }).first().click();
    await expect(page.getByRole('heading', { name: 'Nieuwe afspraak' })).toBeVisible();

    const started = Date.now();
    await page.getByRole('button', { name: 'Nieuwe klant' }).click();
    await page.getByLabel('Naam').fill('Daan de Vries');
    await page.getByLabel('Telefoonnummer').fill('0612345678');

    // Kies een dag verderop, zodat de test op elk moment van de dag werkt
    await page.getByRole('radiogroup', { name: 'Kies een dag' }).locator('label:has(input:enabled)').nth(1).click();
    const firstTime = page.getByRole('radiogroup', { name: 'Kies een tijd' }).locator('label').first();
    await expect(firstTime).toHaveText('09:00');
    await firstTime.click();

    const save = page.getByRole('button', { name: 'Opslaan · 09:00' });
    await expect(save).toBeInViewport({ ratio: 1 });
    await check(page, testInfo, 'nieuwe-afspraak');
    await save.click();

    // Na opslaan: de agenda op die dag, met de afspraak
    await expect(page.getByRole('heading', { name: 'Agenda' })).toBeVisible();
    await expect(page.getByRole('button', { name: /09:00 Daan de Vries/ })).toBeVisible();

    // Doel: een telefonische boeking in < 15 seconden (fase 3, "Klaar als").
    expect(Date.now() - started).toBeLessThan(15_000);
    await check(page, testInfo, 'agenda');

    // ── Inloop: begint nu, daarna in 3 tikken voltooien ──
    await page.goto('/app/afspraken/nieuw');
    await page.getByLabel('Zoek op naam of telefoonnummer').fill('daan');
    await page.getByRole('button', { name: /Daan de Vries/ }).click();
    await page.getByRole('button', { name: 'Inloop: begint nu' }).click();

    const inloop = page.getByRole('button', { name: /Daan de Vries/ });

    if (await page.getByRole('alert').isVisible().catch(() => false)) {
        // Buiten werktijd (bv. 's avonds of op zondag) kan een inloop niet; dan stopt de test hier.
        test.info().annotations.push({ type: 'overgeslagen', description: 'Inloop en voltooien: nu buiten de werktijden van de zaak' });

        return;
    }

    await expect(page.getByRole('heading', { name: 'Agenda' })).toBeVisible();
    await page.goto('/app');
    await expect(page.getByText('Eerstvolgende')).toBeVisible();
    await check(page, testInfo, 'vandaag');

    // Tik 1: de afspraak
    await inloop.first().click();
    const sheet = page.getByRole('dialog', { name: 'Daan de Vries' });
    await expect(sheet).toBeVisible();
    await screenshot(page, testInfo, 'fase-3', 'afspraak-acties');

    // Tik 2: Voltooien
    await sheet.getByRole('button', { name: 'Voltooien' }).click();
    await expect(sheet.getByLabel('Bedrag')).toHaveValue('25,00');
    await screenshot(page, testInfo, 'fase-3', 'voltooien');

    // Tik 3: de betaalmethode slaat op
    await sheet.getByRole('button', { name: 'Pin' }).click();
    await expect(page.getByRole('status')).toHaveText('Voltooid · € 25,00');
    await expect(page.getByText('€ 25,00').first()).toBeVisible();

    // ── Klanten ──
    await page.getByRole('link', { name: 'Klanten' }).click();
    await expect(page.getByRole('heading', { name: 'Klanten' })).toBeVisible();
    await check(page, testInfo, 'klanten');
    await page.getByRole('link', { name: /Daan de Vries/ }).click();
    await expect(page.getByText('Totaal besteed')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Nieuwe afspraak' }).last()).toBeInViewport({ ratio: 1 });
    await check(page, testInfo, 'klant');

    // ── Omzet ──
    await page.goto('/app/omzet');
    await expect(page.getByText('Omzet van de zaak')).toBeVisible();
    await expect(page.getByText('€ 25,00').first()).toBeVisible();
    await expect(page.getByRole('link', { name: /Exporteer CSV/ })).toBeVisible();
    await check(page, testInfo, 'omzet');
});

test('de app is installeerbaar: manifest, iconen en service worker', async ({ page, request }) => {
    await page.goto('/inloggen');

    const manifestUrl = await page.locator('link[rel="manifest"]').getAttribute('href');
    const manifest = await (await request.get(manifestUrl)).json();

    expect(manifest.display).toBe('standalone');
    expect(manifest.start_url).toBe('/app');

    for (const icon of manifest.icons) {
        expect((await request.get(icon.src)).status()).toBe(200);
    }

    // De service worker registreert en wordt actief
    const state = await page.evaluate(async () => {
        const registration = await navigator.serviceWorker.ready;

        return registration.active?.state;
    });

    expect(['activating', 'activated']).toContain(state);
});
