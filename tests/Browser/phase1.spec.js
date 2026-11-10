import { expect, test } from '@playwright/test';
import { expectInputsAtLeast16px, expectNoHorizontalScroll, expectTapTargets, screenshot } from './helpers.js';

async function check(page, testInfo, name) {
    await page.evaluate(() => document.fonts.ready);
    await expectNoHorizontalScroll(page);
    await expectInputsAtLeast16px(page);
    await expectTapTargets(page);
    await screenshot(page, testInfo, 'fase-1', name);
}

test('een nieuwe zaak meldt zich aan op een telefoon en richt de zaak in', async ({ page }, testInfo) => {
    test.setTimeout(120_000);

    const id = `${testInfo.project.name}-${Date.now()}`;
    const shopName = `Kapper ${id}`;
    const slug = `kapper-${id}`;
    const started = Date.now();

    // Stap 1 — account
    await page.goto('/aanmelden');
    await check(page, testInfo, 'aanmelden-1-account');
    await page.getByLabel('Je naam').fill('Jay Fontein');
    await page.getByLabel('E-mailadres').fill(`jay-${id}@example.com`);
    await page.getByLabel('Wachtwoord').fill('geheim-wachtwoord-123');
    await page.getByRole('button', { name: 'Verder' }).click();

    // Stap 2 — zaak: de link wordt voorgesteld uit de naam
    await expect(page.getByRole('heading', { name: 'Je zaak' })).toBeVisible();
    await page.getByLabel('Naam van je zaak').fill(shopName);
    await expect(page.getByLabel('Je boekingslink')).toHaveValue(slug);
    await page.getByLabel('Plaats').fill('Utrecht');
    await page.getByLabel('Telefoonnummer van de zaak').fill('0612345678');
    await check(page, testInfo, 'aanmelden-2-zaak');
    await page.getByRole('button', { name: 'Verder' }).click();

    // Stap 3 — kappers
    await expect(page.getByRole('heading', { name: /Werk je alleen/ })).toBeVisible();
    await page.getByText('Meer kappers', { exact: true }).click();
    await page.getByLabel('Naam kapper 1').fill('Sam');
    await check(page, testInfo, 'aanmelden-3-kappers');
    await page.getByRole('button', { name: 'Verder' }).click();

    // Stap 4 — tijden: pauze toevoegen op dinsdag
    await expect(page.getByRole('heading', { name: 'Wanneer ben je open?' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Verder' })).toBeInViewport({ ratio: 1 });
    await page.getByRole('button', { name: 'Pauze' }).first().click();
    await expect(page.getByLabel('Dinsdag tot').first()).toHaveValue('12:30');
    await check(page, testInfo, 'aanmelden-4-tijden');
    await page.getByRole('button', { name: 'Verder' }).click();

    // Stap 5 — diensten
    await expect(page.getByRole('heading', { name: 'Je diensten' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Klaar' })).toBeInViewport({ ratio: 1 });
    await page.getByLabel('Prijs').first().fill('27,50');
    await check(page, testInfo, 'aanmelden-5-diensten');
    await page.getByRole('button', { name: 'Klaar' }).click();

    // Stap 6 — klaar
    await expect(page.getByRole('heading', { name: `${shopName} staat online` })).toBeVisible();
    await expect(page.getByText(`/${slug}`)).toBeVisible();
    await check(page, testInfo, 'aanmelden-6-klaar');

    // Doel: < 3 minuten (§8). Een script is veel sneller dan een mens; dit bewaakt vooral dat er geen trage stap in zit.
    expect(Date.now() - started).toBeLessThan(60_000);

    // De boekingspagina staat online
    await page.goto(`/${slug}`);
    await expect(page.getByRole('heading', { name: shopName })).toBeVisible();
    await expect(page.getByText('€ 27,50')).toBeVisible();
    await expect(page.getByText('09:00 – 12:30, 13:00 – 18:00')).toBeVisible();
    await check(page, testInfo, 'zaakpagina');

    // De kapper-app
    await page.goto('/app');
    await expect(page.getByText('Maak je zaak af')).toBeVisible();
    await check(page, testInfo, 'app-vandaag');

    await page.getByRole('link', { name: 'Meer' }).click();
    await expect(page.getByRole('heading', { name: 'Meer' })).toBeVisible();
    await check(page, testInfo, 'app-meer');

    // Kapper toevoegen via een Livewire-actie: de zaak moet ook bij updaterequests bekend zijn
    await page.getByRole('link', { name: 'Kappers' }).click();
    await expect(page.getByText('Jay Fontein')).toBeVisible();
    await page.getByRole('button', { name: 'Kapper toevoegen' }).click();
    const sheet = page.getByRole('dialog', { name: 'Kapper toevoegen' });
    await sheet.getByLabel('Naam').fill('Mo');
    await sheet.getByRole('button', { name: 'Toevoegen' }).click();
    await expect(page.getByRole('link', { name: /Mo/ })).toBeVisible();
    await check(page, testInfo, 'app-kappers');

    // Werktijden van een kapper
    await page.getByRole('link', { name: /Sam/ }).click();
    await expect(page.getByRole('heading', { name: 'Werktijden' })).toBeVisible();
    await check(page, testInfo, 'app-kapper-bewerken');

    // Diensten
    await page.goto('/app/diensten');
    await expect(page.getByText('€ 27,50')).toBeVisible();
    await check(page, testInfo, 'app-diensten');
    await page.getByRole('link', { name: 'Dienst toevoegen' }).click();
    await page.getByLabel('Naam').fill('Wenkbrauwen');
    await page.getByLabel('Prijs').fill('10');
    await check(page, testInfo, 'app-dienst-nieuw');
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByText('Wenkbrauwen')).toBeVisible();

    // Openingstijden en instellingen
    await page.goto('/app/openingstijden');
    await check(page, testInfo, 'app-openingstijden');
    await page.goto('/app/instellingen');
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeInViewport({ ratio: 1 });
    await check(page, testInfo, 'app-instellingen');

    // Uiterlijk: het voorbeeld volgt het gekozen thema meteen
    await page.goto('/app/uiterlijk');
    await page.getByText('Donker', { exact: true }).click();
    await expect(page.locator('[data-theme="dark"]')).toBeVisible();
    await page.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('status')).toHaveText('Uiterlijk opgeslagen');
    await check(page, testInfo, 'app-uiterlijk');

    await page.goto(`/${slug}`);
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await check(page, testInfo, 'zaakpagina-donker');
});
