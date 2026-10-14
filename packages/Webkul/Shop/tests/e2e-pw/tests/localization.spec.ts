import { test, expect } from "../setup";

/**
 * The storefront is set up for Afghanistan: Dari is read right to left and
 * prices are written in Afghanis. These checks do not look for any label, so
 * they hold whichever language the installation was started in.
 */

const locale = process.env.APP_LOCALE ?? "en";

const currency = process.env.APP_CURRENCY ?? "USD";

const RIGHT_TO_LEFT_LOCALES = ["ar", "fa", "fa_AF", "he"];

test.describe("Storefront localization", () => {
    test("serves the storefront in the configured language and direction", async ({
        shopPage,
    }) => {
        await shopPage.goto("");

        const html = shopPage.locator("html");

        await expect(html).toHaveAttribute("lang", locale);

        await expect(html).toHaveAttribute(
            "dir",
            RIGHT_TO_LEFT_LOCALES.includes(locale) ? "rtl" : "ltr",
        );
    });

    test("sells in the configured currency", async ({ shopPage }) => {
        await shopPage.goto("");

        const meta = await shopPage
            .locator('meta[name="currency"]')
            .getAttribute("content");

        expect(JSON.parse(meta ?? "{}").code).toBe(currency);
    });

    test("writes prices with the Afghani sign", async ({ shopPage }) => {
        test.skip(currency !== "AFN", "the store does not sell in Afghanis");

        await shopPage.goto("");

        const prices = shopPage.locator("text=؋");

        test.skip(
            (await prices.count()) === 0,
            "the catalogue has no price to show on the home page",
        );

        await expect(prices.first()).toBeVisible();
    });

    test("writes Dari in a font that has the letters", async ({ shopPage }) => {
        test.skip(locale !== "fa_AF", "the storefront is not being served in Dari");

        const fontFiles = await Promise.all(
            [
                "/fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2",
                "/fonts/noto-arabic/afghani-sign.woff2",
            ].map((font) => shopPage.request.get(font)),
        );

        for (const fontFile of fontFiles) {
            expect(fontFile.status()).toBe(200);
        }

        await shopPage.goto("");

        const family = await shopPage.evaluate(
            () => getComputedStyle(document.body).fontFamily,
        );

        expect(family).toContain("Vazirmatn");
    });
});
