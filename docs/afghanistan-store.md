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

## Asset build

The RTL type and the direction of the layout come from Blade partials and are
live immediately. The validation messages for Dari live in
`packages/Webkul/Shop/src/Resources/assets/locales/fa_AF.json` and
`packages/Webkul/Admin/src/Resources/assets/locales/fa_AF.json`, which are
compiled — run the front-end build of both packages before deploying, or the
browser keeps the old bundle.

The Vazirmatn webfonts are committed under `public/fonts/vazirmatn` (SIL OFL,
licence text included), so no storefront page depends on a font CDN.

## Tests

```bash
php artisan bagisto:translations:check   # Dari files match English key for key
./vendor/bin/pest                         # PHPUnit/Pest suites, locale independent
./vendor/bin/pint --test                  # code style
```

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

## Known remaining items

* The storefront and admin panel are translated; the seeded demo content,
  the CMS pages and the theme sections from the previous installation are not.
* Iranian Persian (`fa`) is shipped as it came from upstream, minus the strings
  that were still English. It is there for completeness, not as the store
  language.
* Tax rates and exchange rates are settings, not data: they are seeded empty and
  have to be entered for Afghanistan.
* The upstream Playwright suites need an English installation, as described above.
