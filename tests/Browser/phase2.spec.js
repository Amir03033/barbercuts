import { expect, test } from '@playwright/test';
import { createShop, expectInputsAtLeast16px, expectNoHorizontalScroll, expectTapTargets, screenshot } from './helpers.js';

async function check(page, testInfo, name) {
    await page.evaluate(() => document.fonts.ready);
    await expectNoHorizontalScroll(page);
    await expectInputsAtLeast16px(page);
    await expectTapTargets(page);
    await screenshot(page, testInfo, 'fase-2', name);
}

test('een gast boekt op een telefoon, verplaatst de afspraak en annuleert', async ({ page }, testInfo) => {
    test.setTimeout(120_000);

    const id = `${testInfo.project.name}-${Date.now()}`;
    const slug = await createShop(page, id);

    // Verder als gast
    await page.context().clearCookies();

    await page.goto(`/${slug}`);
    await expect(page.getByRole('link', { name: 'Afspraak maken' })).toBeInViewport({ ratio: 1 });
    await check(page, testInfo, 'zaakpagina');

    const started = Date.now();
    await page.getByRole('link', { name: 'Afspraak maken' }).click();

    // Stap 1 — dienst
    await expect(page.getByRole('heading', { name: 'Kies een dienst' })).toBeVisible();
    await check(page, testInfo, 'boeken-1-dienst');
    await page.getByRole('button', { name: /Knippen 30 min/ }).click();

    // Stap 2 (kapper) wordt overgeslagen: de zaak heeft één kapper.
    // Stap 3 — dag + tijd op één scherm
    await expect(page.getByRole('heading', { name: 'Kies een dag en tijd' })).toBeVisible();
    const next = page.getByRole('button', { name: /Kies een tijd|Verder met/ });
    await expect(next).toBeInViewport({ ratio: 1 });
    await expect(next).toBeDisabled();

    // Kies de tweede vrije dag: dan valt de afspraak ruim buiten de limiet voor verplaatsen en annuleren.
    await page.getByRole('radiogroup', { name: 'Kies een dag' }).locator('label:has(input:enabled)').nth(1).click();
    await expect(page.getByRole('radiogroup', { name: 'Kies een dag' }).locator('input:checked')).toHaveCount(1);

    // Een volgende dag begint bij de openingstijd
    const firstTime = page.getByRole('radiogroup', { name: 'Kies een tijd' }).locator('label').first();
    await expect(firstTime).toHaveText('09:00');
    const time = (await firstTime.textContent()).trim();
    await firstTime.click();
    await expect(page.getByRole('button', { name: `Verder met ${time}` })).toBeEnabled();
    await check(page, testInfo, 'boeken-3-dag-en-tijd');
    await page.getByRole('button', { name: `Verder met ${time}` }).click();

    // Stap 4 — gegevens: als gast, met de voordelen van een account rustig erboven
    await expect(page.getByRole('heading', { name: 'Je gegevens' })).toBeVisible();
    await expect(page.getByText('Sneller boeken met een account')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Bevestigen' })).toBeInViewport({ ratio: 1 });
    await page.getByLabel('Naam').fill('Daan de Vries');
    await page.getByLabel('Telefoonnummer').fill('0687654321');
    await check(page, testInfo, 'boeken-4-gegevens');
    await page.getByRole('button', { name: 'Bevestigen' }).click();

    // Bevestigd
    await expect(page.getByRole('heading', { name: 'Je afspraak staat vast' })).toBeVisible();
    await expect(page).toHaveURL(/\/afspraak\/[A-Za-z0-9]{40}$/);

    // Doel: een gast boekt in < 60 seconden (fase 2, "Klaar als").
    expect(Date.now() - started).toBeLessThan(60_000);

    await expect(page.getByText(time, { exact: false }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Zet in je agenda' })).toHaveAttribute('href', /agenda\.ics$/);
    await expect(page.getByText('Bewaar deze afspraak in je account')).toBeVisible();
    await check(page, testInfo, 'bevestigd');

    const manageUrl = page.url();

    // Het tijdstip is nu bezet voor de volgende klant
    await page.goto(`/${slug}/boeken`);
    await page.getByRole('button', { name: /Knippen 30 min/ }).click();
    await page.getByRole('radiogroup', { name: 'Kies een dag' }).locator('label:has(input:enabled)').nth(1).click();
    await expect(page.getByRole('radiogroup', { name: 'Kies een tijd' }).locator('label').first()).toHaveText('09:30');
    await expect(page.getByRole('radiogroup', { name: 'Kies een tijd' }).getByText(time, { exact: true })).toHaveCount(0);

    // Verplaatsen via de beheerlink
    await page.goto(manageUrl);
    await expect(page.getByRole('heading', { name: 'Je afspraak', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Verplaatsen' }).click();
    await expect(page.getByRole('heading', { name: 'Afspraak verplaatsen' })).toBeVisible();

    const times = page.getByRole('radiogroup', { name: 'Kies een tijd' }).locator('label');
    const newTime = (await times.nth(3).textContent()).trim();
    await times.nth(3).click();
    await check(page, testInfo, 'verplaatsen');
    await page.getByRole('button', { name: `Verplaats naar ${newTime}` }).click();
    await expect(page.getByRole('status')).toHaveText('Je afspraak is verplaatst');
    await expect(page.getByText(`${newTime} –`)).toBeVisible();

    // Annuleren met bevestiging
    await page.getByRole('button', { name: 'Annuleren' }).click();
    const sheet = page.getByRole('dialog', { name: 'Afspraak annuleren?' });
    await expect(sheet).toBeVisible();
    await screenshot(page, testInfo, 'fase-2', 'annuleren');
    await sheet.getByRole('button', { name: 'Ja, annuleer mijn afspraak' }).click();
    await expect(page.getByText('Geannuleerd', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Nieuwe afspraak maken' })).toBeVisible();
    await check(page, testInfo, 'geannuleerd');
});

test('een gast maakt na het boeken een account en ziet de afspraak in /account', async ({ page }, testInfo) => {
    test.setTimeout(120_000);

    const id = `acc-${Date.now()}${testInfo.project.name === 'iphone-se' ? 's' : 'l'}`;
    const slug = await createShop(page, id);
    await page.context().clearCookies();

    await page.goto(`/${slug}/boeken`);
    await page.getByRole('button', { name: /Knippen 30 min/ }).click();
    await page.getByRole('radiogroup', { name: 'Kies een tijd' }).locator('label').first().click();
    await page.getByRole('button', { name: /Verder met/ }).click();
    await page.getByLabel('Naam').fill('Yusuf Kaya');
    await page.getByLabel('Telefoonnummer').fill('0611223344');
    await page.getByLabel('E-mailadres').fill(`yusuf-${id}@example.com`);
    await page.getByRole('button', { name: 'Bevestigen' }).click();

    await expect(page.getByRole('heading', { name: 'Je afspraak staat vast' })).toBeVisible();
    await expect(page.getByLabel('E-mailadres')).toHaveValue(`yusuf-${id}@example.com`);
    await page.getByLabel('Kies een wachtwoord').fill('geheim-wachtwoord-123');
    await page.getByRole('button', { name: 'Account maken' }).click();
    await expect(page.getByRole('status')).toHaveText('Je account is aangemaakt');

    await page.getByRole('link', { name: 'Al mijn afspraken' }).click();
    await expect(page.getByRole('heading', { name: 'Mijn afspraken' })).toBeVisible();
    await expect(page.getByText(`Kapper ${id}`)).toBeVisible();
    await check(page, testInfo, 'account');
});
