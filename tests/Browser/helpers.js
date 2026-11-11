import { expect } from '@playwright/test';

/** Geen horizontale scroll: de pagina is nooit breder dan het scherm. */
export async function expectNoHorizontalScroll(page) {
    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));

    expect(scrollWidth, 'pagina scrolt horizontaal').toBeLessThanOrEqual(clientWidth);
}

/** Inputs zijn minimaal 16px, anders zoomt iOS in bij focus. */
export async function expectInputsAtLeast16px(page) {
    const tooSmall = await page.evaluate(() =>
        [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]):not([type=hidden]):not([type=file]):not([type=color]), select, textarea')]
            .filter((element) => parseFloat(getComputedStyle(element).fontSize) < 16)
            .map((element) => element.name || element.id),
    );

    expect(tooSmall, 'inputs kleiner dan 16px').toEqual([]);
}

/** Zichtbare knoppen en links zijn minimaal 44px hoog. */
export async function expectTapTargets(page) {
    const tooSmall = await page.evaluate(() =>
        // Blokken in de tijdlijn van de agenda zijn zo hoog als de afspraak duurt (15 minuten = 24px) en tellen niet mee.
        [...document.querySelectorAll('a, button')]
            .filter((element) => ! element.hasAttribute('data-timeline-item'))
            .filter((element) => {
                const rect = element.getBoundingClientRect();

                return rect.width > 0 && rect.height > 0 && rect.height < 43.5;
            })
            .map((element) => `${element.textContent.trim().slice(0, 30)} (${Math.round(element.getBoundingClientRect().height)}px)`),
    );

    expect(tooSmall, 'tapdoelen kleiner dan 44px').toEqual([]);
}

/** Screenshot voor de PR: docs/screenshots/<fase>/<naam>-<project>.png */
export async function screenshot(page, testInfo, phase, name) {
    await page.screenshot({
        path: `docs/screenshots/${phase}/${name}-${testInfo.project.name}.png`,
        fullPage: true,
    });
}

/**
 * Meld via de onboarding een nieuwe zaak aan (solo-kapper, standaardtijden en -diensten) en geef de slug terug.
 * Daarna is de eigenaar ingelogd; gebruik page.context().clearCookies() om als gast verder te gaan.
 */
export async function createShop(page, id) {
    const slug = `kapper-${id}`;

    await page.goto('/aanmelden');
    await page.getByLabel('Je naam').fill('Jay Fontein');
    await page.getByLabel('E-mailadres').fill(`jay-${id}@example.com`);
    await page.getByLabel('Wachtwoord').fill('geheim-wachtwoord-123');
    await page.getByRole('button', { name: 'Verder' }).click();

    await page.getByLabel('Naam van je zaak').fill(`Kapper ${id}`);
    await expect(page.getByLabel('Je boekingslink')).toHaveValue(slug);
    await page.getByLabel('Plaats').fill('Utrecht');
    await page.getByLabel('Telefoonnummer van de zaak').fill('0612345678');
    await page.getByRole('button', { name: 'Verder' }).click();

    await expect(page.getByRole('heading', { name: /Werk je alleen/ })).toBeVisible();
    await page.getByRole('button', { name: 'Verder' }).click();

    await expect(page.getByRole('heading', { name: 'Wanneer ben je open?' })).toBeVisible();
    await page.getByRole('button', { name: 'Verder' }).click();

    await expect(page.getByRole('heading', { name: 'Je diensten' })).toBeVisible();
    await page.getByRole('button', { name: 'Klaar' }).click();

    await expect(page.getByRole('heading', { name: /staat online/ })).toBeVisible();

    return slug;
}
