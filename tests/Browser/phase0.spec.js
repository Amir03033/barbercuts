import { expect, test } from '@playwright/test';
import { expectInputsAtLeast16px, expectNoHorizontalScroll, expectTapTargets, screenshot } from './helpers.js';

const pages = [
    ['home', '/'],
    ['inloggen', '/inloggen'],
    ['registreren', '/registreren'],
    ['wachtwoord-vergeten', '/wachtwoord-vergeten'],
    ['ui-platform', '/_ui'],
    ['ui-app', '/_ui/app'],
    ['ui-shop-licht', '/_ui/shop'],
    ['ui-shop-donker', '/_ui/shop?theme=dark&accent=%23facc15'],
];

for (const [name, url] of pages) {
    test(`${name}: mobiel in orde`, async ({ page }, testInfo) => {
        await page.goto(url);
        await page.evaluate(() => document.fonts.ready);

        await expectNoHorizontalScroll(page);
        await expectInputsAtLeast16px(page);
        await expectTapTargets(page);
        await screenshot(page, testInfo, 'fase-0', name);
    });
}

test('inloggen: primaire knop zichtbaar zonder scrollen', async ({ page }) => {
    await page.goto('/inloggen');

    await expect(page.getByRole('button', { name: 'Inloggen' })).toBeInViewport({ ratio: 1 });
});

test('inloggen: foutmelding bij verkeerde gegevens', async ({ page }) => {
    await page.goto('/inloggen');
    await page.getByLabel('E-mailadres').fill('bestaat-niet@example.com');
    await page.getByLabel('Wachtwoord').fill('verkeerd-wachtwoord');
    await page.getByRole('button', { name: 'Inloggen' }).click();

    await expect(page.getByRole('alert')).toContainText('klopt niet');
});

test('registreren: nieuw account komt op de bevestigingspagina', async ({ page }, testInfo) => {
    await page.goto('/registreren');
    await page.getByLabel('Naam').fill('Test Kapper');
    await page.getByLabel('E-mailadres').fill(`e2e-${testInfo.project.name}-${Date.now()}@example.com`);
    await page.getByLabel('Wachtwoord').fill('geheim-wachtwoord-123');
    await page.getByRole('button', { name: 'Account maken' }).click();

    await expect(page).toHaveURL(/e-mail-bevestigen/);
    await expect(page.getByText('Bevestig je e-mailadres')).toBeVisible();
    await expectNoHorizontalScroll(page);
    await screenshot(page, testInfo, 'fase-0', 'e-mail-bevestigen');
});

test('showcase: bottom sheet, toast en sticky footer werken', async ({ page }, testInfo) => {
    await page.goto('/_ui/app');

    const primary = page.getByRole('button', { name: /Primaire knop/ });
    await expect(primary).toBeInViewport({ ratio: 1 });
    await expect(page.getByRole('navigation', { name: 'Hoofdmenu' })).toBeInViewport({ ratio: 1 });

    await page.getByRole('button', { name: 'Open bottom sheet' }).click();
    const sheet = page.getByRole('dialog', { name: 'Daan de Vries' });
    await expect(sheet).toBeVisible();
    await screenshot(page, testInfo, 'fase-0', 'ui-bottom-sheet');

    await sheet.getByRole('button', { name: 'Voltooien' }).click();
    await expect(sheet).toBeHidden();
    await expect(page.getByRole('status')).toHaveText('Voltooid');
});

test('showcase: formulier valideert via Livewire', async ({ page }) => {
    await page.goto('/_ui');
    await page.getByRole('button', { name: /Opslaan/ }).click();

    await expect(page.getByText('naam is verplicht', { exact: false })).toBeVisible();

    await page.getByLabel('Naam').fill('Daan de Vries');
    await page.getByLabel('Telefoonnummer').fill('0612345678');
    await page.getByRole('button', { name: /Opslaan/ }).click();

    await expect(page.getByRole('status')).toHaveText('Opgeslagen');
});
