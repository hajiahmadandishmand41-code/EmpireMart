# Afghanistan store — Dari and the Afghani

This checkout is set up for a shop in Afghanistan: the sales channel sells in
Afghanis (AFN), the storefront and the admin panel are written in Afghan Dari
(`fa_AF`, right to left), and the store runs on Kabul time with Afghanistan as
its default country.

Everything below is what has to be done on the machine that runs the store. The
defaults already ship in this repository, so a fresh installation needs no manual
step beyond the installer itself.

## What the store is set to

| Setting | Value | Where it lives |
|---|---|---|
| Base currency | `AFN`, written `؋ ۱٬۲۳۴٫۵۰` | `config/app.php`, `APP_CURRENCY`, the channel's base currency |
| Locale | `fa_AF` (Dari), direction `rtl` | `config/app.php`, `APP_LOCALE`, the channel's default locale |
| Timezone | `Asia/Kabul` | `config/app.php`, `APP_TIMEZONE`, the channel timezone |
| Default country | `AF` | `config/app.php`, `APP_DEFAULT_COUNTRY` |
| Provinces | the 34 provinces of Afghanistan, named in Dari | `country_states` / `country_state_translations` |
| Postal code | not required | Admin → Configuration → Customer → Address |
| Street lines | two | Admin → Configuration → Customer → Address |
| Flat rate delivery | 150 AFN | Admin → Configuration → Sales → Shipping Methods |

`.env` keys to set on a deployment:

```
APP_NAME=EmpireMart
APP_URL=https://your-domain.af
APP_TIMEZONE=Asia/Kabul
APP_LOCALE=fa_AF
APP_DEFAULT_COUNTRY=AF
APP_CURRENCY=AFN
```

`APP_FALLBACK_LOCALE` may stay `en`: it is only used for content that has no Dari
translation.

## A new installation

1. `composer install`, then `npm install && npm run build` inside
   `packages/Webkul/Shop`, `packages/Webkul/Admin` and `packages/Webkul/Installer`
   if the front-end sources were changed.
2. `php artisan bagisto:install` (or the web installer). Choose **افغانی افغانستان
   (AFN)** as the default currency and **دری** as the default locale. Both are
   already preselected when `APP_CURRENCY` and `APP_LOCALE` are set.
3. `php artisan indexer:index --mode=full`.

The installer seeds the Afghani, Dari, the Afghan provinces and the address
settings, so nothing here needs to be done by hand.

## An existing installation

Take a database backup first — the prices are about to be rewritten.

```bash
mysqldump -u user -p bagisto > bagisto-before-afn.sql

php artisan migrate            # registers AFN + Dari, provinces, address settings
php artisan bagisto:afghanistan:setup --rate=<afghanis per unit of the old currency> --dry-run
php artisan bagisto:afghanistan:setup --rate=<rate>   # asks for confirmation
```

The command converts the stored amounts and moves the channel to the Afghani:

* product prices, costs and special prices (`product_attribute_values`),
* customer group prices (`product_customer_group_prices`),
* fixed cart-rule and catalog-rule discounts,
* the flat rate and minimum-order settings.

It does **not** touch orders, invoices, refunds or shipments: those keep the
currency they were placed in and stay readable as they were. Percentages are
left alone because a percentage of a price does not change when the unit does.

Exchange rates are not guessed either. `--rate` is the rate the shop owner sets
the prices with, and without it the command only reports what would be converted.
Switching a store from another currency is a business decision, not something a
migration should decide on its own.

After the conversion:

```bash
php artisan indexer:index --type=price --mode=full
php artisan product:price-rule:index
php artisan cache:clear
```

## What to check in the admin panel

* **Settings → Channels** — the base currency is AFN, Dari is among the locales,
  and the timezone is Asia/Kabul.
* **Settings → Locales** — `دری` is listed with direction `rtl`.
* **Settings → Currencies** — AFN carries the symbol `؋` and two decimal places.
* **Configuration → Customer → Address** — postal code is not required (the field
  is not collected in Afghanistan), an address is written over two lines, and the
  state field offers the provinces.
* **Configuration → Sales → Shipping Methods** — the flat rate is 150 AFN, in the
  base currency. Change it to the current tariff.
* **Customers → Taxes** — tax rates are per country; add the Afghan ones the shop
  actually charges rather than leaving the Indian defaults in place.
* **Appearance → Themes** and **Content** — any content written before the
  conversion is still in the old wording and needs translating.

## Right-to-left layout

The direction comes from the locale: `SupportedLocales::direction('fa_AF')` is
`rtl`, the `<html>` tag carries it, and both panels' CSS is written for it
already (`[dir="rtl"]` rules, `rtl:` variants, `rtl:icon-*` glyphs). What was
missing were the places that use a fixed side instead of a logical one.

Tailwind's logical utilities are now used for spacing, borders, corners and
alignment (`ps-`/`pe-`, `ms-`/`me-`, `border-s`/`border-e`,
`rounded-s`/`rounded-e`, `text-start`/`text-end`, `start-`/`end-`). They compute
the same values as their physical twins while the page is LTR, so nothing moved
for English, and they follow the writing direction once the page is RTL.
Icons that point along the text (`icon-arrow-left`, `icon-sort-right`, the
carousel and pagination arrows, the date-picker and gallery arrows) carry the
mirrored class as an `rtl:` variant, and `space-x-*` groups carry
`rtl:space-x-reverse`.

When editing a view, keep to that: prefer the logical utility; if the physical
one is unavoidable, add the `rtl:` counterpart next to it. The two exceptions
are deliberate — `left-1/2 -translate-x-1/2` centring, which is direction
independent, and pairs that already spell out both directions with `ltr:` and
`rtl:`. After changing views, rebuild (below) and mirror the change in the CSS
check described under "What has been verified".

## Asset build

The RTL type and the direction of the layout come from Blade partials and are
live immediately. The validation messages for Dari live in
`packages/Webkul/Shop/src/Resources/assets/locales/fa_AF.json` and
`packages/Webkul/Admin/src/Resources/assets/locales/fa_AF.json`, which are
compiled — run the front-end build of both packages before deploying, or the
browser keeps the old bundle.

The Vazirmatn webfonts are committed under `public/fonts/vazirmatn` (SIL OFL,
licence text included), so no storefront page depends on a font CDN. Vazirmatn
carries every letter Dari is written with but not the Afghani sign, so that one
glyph is served from a 692-byte subset of Noto Sans Arabic
(`public/fonts/noto-arabic/afghani-sign.woff2`, also SIL OFL) and pulled in by
`unicode-range: U+060B` — the browser downloads it only for that character.

## Tests

```bash
php artisan bagisto:translations:check   # Dari files match English key for key
./vendor/bin/pest                         # PHPUnit/Pest suites, locale independent
./vendor/bin/pint --test                  # code style
```

The suites that only make sense in Dari run against a Dari installation:

```bash
./vendor/bin/pest packages/Webkul/Core/tests/Unit/Afghanistan \
                  packages/Webkul/Shop/tests/Feature/Afghanistan
```

`DariOrderTest` walks the storefront: a product at 1000 AFN goes into an Afghan
cart, a ten percent coupon takes a hundred Afghanis off, the order is placed,
invoiced and read back. Every amount is asserted twice — as the number the shop
calculated and as the string the customer sees, `؋ ۱۰۰۰٫۰۰` and `؋ ۹۰۰٫۰۰` —
and the invoice is asserted to be a right-to-left document in a font that has
the sign of the Afghani. Both paths are those run by the "Installing the store
in Dari" steps of `.github/workflows/pest_tests.yml`.

End-to-end, with the store running:

```bash
cd packages/Webkul/Shop
npx playwright test --config=tests/e2e-pw/playwright.config.ts tests/e2e-pw/tests/localization.spec.ts
```

That spec asserts the direction and language the storefront is served in, the
currency it prices in, and that the Dari font file loads and is applied — it
looks for configuration rather than for a label, so it holds in every language.

The rest of the upstream Playwright suites find the shop and the admin panel by
their English labels. Run them against an installation with `APP_LOCALE=en`
(which is what `.github/workflows/*_playwright_tests.yml` do), or translate the
labels they look for when the Dari panel becomes the one under test.

## Acceptance walkthrough

With the store in Dari and pricing in Afghanis, confirm on the storefront:

1. Home, category and product pages render right to left, in Vazirmatn, with
   prices such as `؋ ۱٬۲۳۴٫۵۰`.
2. Add to cart, apply a discount code, and check the cart summary — sub total,
   discount, tax, shipping and grand total must agree with the admin side.
3. Checkout as a guest: country defaults to Afghanistan, the province list is
   there, and the postal code is optional.
4. Place the order; the confirmation, the invoice PDF and the order emails must
   be right to left and show Afghanis.
5. In the admin panel, open the order, invoice it, and compare the totals.
6. Open the same pages on a phone (or a narrow window): the drawer, the mini
   cart and the checkout steps must stay usable right to left.

## What has been verified, and how

Checked on the machine this work was done on, without a database:

* every PHP file in the repository parses (PHP 8.3, whole tree, no syntax error);
* the translation gate's own rules hold — each package's locale folders match
  the supported locales, and every English key exists in Dari with the same file
  and line structure (0 differences);
* both front-end bundles build (`npm run build` in `packages/Webkul/Shop` and
  `packages/Webkul/Admin`), and the validation plugins and locale modules parse
  as ES modules;
* the validation catalogue is read back out of both plugins and asserted to
  carry 23 languages with Dari beside them, and the Dari messages inside;
* every non-ASCII character the Dari pack uses exists in a font this repository
  serves — Vazirmatn for the letters, the Noto subset for the sign of the
  Afghani, which Vazirmatn does not have;
* the Afghani is formatted by ICU as `؋ ۱٬۲۳۴٫۵۰` in Dari and `؋ 1,234.50` in
  English, with two decimals rather than the whole units ICU would default to
  for a currency without minor units of its own;
* no view is left with a one-sided direction utility: every `pl-`/`pr-`,
  `ml-`/`mr-`, `left-`/`right-`, `text-left`/`text-right`, `border-l`/`border-r`
  and `rounded-l`/`rounded-r` either is logical now, carries its `rtl:`
  counterpart, or is part of a `translate-x` centring pair (audited with a
  script over `views`, `assets` and `*.vue`);
* every direction-related class the views use is present in the built
  stylesheets of both panels, `rtl:icon-arrow-*` and the logical utilities
  included;
* the text was shaped and drawn offline with HarfBuzz from the exact font files
  this repository serves — Vazirmatn for the letters, the Noto subset for the
  sign — which is what confirms the joining forms, the Persian digits and the
  `؋` really come out of that font stack.

Not run here — this machine has no PHP runtime, no Composer and no MySQL, so
Laravel cannot start: the Pest suites, the Playwright suites, a real checkout,
an invoice PDF, and the migration and conversion command against a live
database. Those are wired into CI and listed above as the steps to run.

## Known remaining items

* The storefront and admin panel are translated; the seeded demo content,
  the CMS pages and the theme sections from the previous installation are not.
* Iranian Persian (`fa`) is shipped as it came from upstream, minus the strings
  that were still English. It is there for completeness, not as the store
  language.
* Tax rates and exchange rates are settings, not data: they are seeded empty and
  have to be entered for Afghanistan.
* The invoice PDF embeds Noto Sans Arabic from `public/fonts` for Persian and
  Dari, the way the other scripts are embedded. mPDF fetches it over the URL the
  invoice was requested from, so the server has to be able to reach `APP_URL` —
  the same requirement the Chinese, Japanese, Hindi, Bengali and Sinhala
  invoices already have. Open one invoice after deploying and check the sign of
  the Afghani is printed; if the font cannot be fetched, mPDF draws the invoice
  in its own font and leaves the sign out, and the fix is the URL, not the
  template.
* The upstream Playwright suites need an English installation, as described above.
* The language switcher falls back to a generic globe icon for Dari, because no
  flag image ships for it (`packages/Webkul/Installer/src/Resources/assets/images/seeders/locales/`);
  five other locales are in the same position. A Dari flag can be uploaded
  against the locale in Settings → Locales, which is where the other flags come
  from.
