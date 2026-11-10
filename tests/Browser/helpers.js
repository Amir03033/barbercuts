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
        [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]):not([type=hidden]), select, textarea')]
            .filter((element) => parseFloat(getComputedStyle(element).fontSize) < 16)
            .map((element) => element.name || element.id),
    );

    expect(tooSmall, 'inputs kleiner dan 16px').toEqual([]);
}

/** Zichtbare knoppen en links zijn minimaal 44px hoog. */
export async function expectTapTargets(page) {
    const tooSmall = await page.evaluate(() =>
        [...document.querySelectorAll('a, button')]
            .filter((element) => {
                const rect = element.getBoundingClientRect();

                return rect.width > 0 && rect.height > 0 && rect.height < 44;
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
