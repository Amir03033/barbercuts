import { expect, test } from '@playwright/test';
import { expectNoHorizontalScroll, expectTapTargets, screenshot } from './helpers.js';

// Pagina's met lopende tekst hebben links midden in zinnen; die vallen buiten de eis voor tapdoelen van 44px.
const pages = [
    ['home', '/', true],
    ['prijzen', '/prijzen', false],
    ['contact', '/contact', true],
    ['privacy', '/privacy', false],
    ['voorwaarden', '/voorwaarden', false],
    ['verwerkersovereenkomst', '/verwerkersovereenkomst', false],
];

for (const [name, url, strictTaps] of pages) {
    test(`${name}: mobiel in orde`, async ({ page }, testInfo) => {
        await page.goto(url);
        await page.evaluate(() => document.fonts.ready);

        await expectNoHorizontalScroll(page);

        if (strictTaps) {
            await expectTapTargets(page);
        }

        await screenshot(page, testInfo, 'fase-4', name);
    });
}

test('landingspagina: de knop "Start gratis" is zichtbaar zonder te scrollen en leidt naar het aanmelden', async ({ page }) => {
    await page.goto('/');

    const start = page.getByRole('link', { name: 'Start gratis' }).first();
    await expect(start).toBeInViewport({ ratio: 1 });

    await start.click();
    await expect(page).toHaveURL(/\/aanmelden$/);
});

test('de footer verwijst naar alle juridische pagina\'s', async ({ page }) => {
    await page.goto('/');

    for (const name of ['Prijzen', 'Privacy', 'Voorwaarden', 'Verwerkersovereenkomst', 'Contact']) {
        await expect(page.getByRole('navigation', { name: 'Voetnavigatie' }).getByRole('link', { name })).toBeVisible();
    }
});

test('het adminpanel is bereikbaar voor het inloggen en blijft dicht voor niet-ingelogden', async ({ page }) => {
    const response = await page.goto('/admin');

    await expect(page).toHaveURL(/\/admin\/login$/);
    expect(response?.status()).toBeLessThan(500);
    await expect(page.getByRole('button', { name: /inloggen/i })).toBeVisible();
});
