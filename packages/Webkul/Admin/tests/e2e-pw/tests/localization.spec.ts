import { test, expect } from "../setup";

/**
 * The admin panel is set up for Afghanistan: it is read right to left, in Dari,
 * and typeset in a font that has the letters. These checks look for the
 * configuration rather than for any label, so they hold whichever language the
 * installation was started in - the panel is the one a shop owner in Kabul
 * works in, and it is checked on the installation that is left in Dari.
 */

const locale = process.env.APP_LOCALE ?? "en";

const RIGHT_TO_LEFT_LOCALES = ["ar", "fa", "fa_AF", "he"];

test.describe("Admin panel localization", () => {
    test("serves the panel in the configured language and direction", async ({
        adminPage,
    }) => {
        await adminPage.goto("admin/dashboard");

        const html = adminPage.locator("html");

        await expect(html).toHaveAttribute("lang", locale);

        await expect(html).toHaveAttribute(
            "dir",
            RIGHT_TO_LEFT_LOCALES.includes(locale) ? "rtl" : "ltr",
        );
    });

    test("writes the panel in a font that has the letters", async ({
        adminPage,
    }) => {
        test.skip(locale !== "fa_AF", "the panel is not being served in Dari");

        const fontFiles = await Promise.all(
            [
                "/fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2",
                "/fonts/noto-arabic/afghani-sign.woff2",
            ].map((font) => adminPage.request.get(font)),
        );

        for (const fontFile of fontFiles) {
            expect(fontFile.status()).toBe(200);
        }

        await adminPage.goto("admin/dashboard");

        const family = await adminPage.evaluate(
            () => getComputedStyle(document.body).fontFamily,
        );

        expect(family).toContain("Vazirmatn");
    });
});
