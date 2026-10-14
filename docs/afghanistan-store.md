# Afghanistan store — Dari and the Afghani

This checkout is set up for a shop in Afghanistan: the sales channel sells in
Afghanis (AFN), the storefront and the admin panel are written in Afghan Dari
(`fa_AF`, right to left), and the store runs on Kabul time with Afghanistan as
its default country.

Everything below is what has to be done on the machine that runs the store. The
defaults already ship in this repository, so a fresh installation needs no manual
step beyond the installer itself.

## What this changed

Nothing was removed and no feature was replaced: the store is Bagisto v2.4.10, configured,
translated and pointed at Afghanistan. The areas touched, and where to look:

| Area | Files | What to look at |
| --- | --- | --- |
| Store settings | `config/app.php`, `.env.example` | timezone `Asia/Kabul`, locale `fa_AF`, currency `AFN`, default country `AF` |
| Existing installations | `database/migrations/2026_10_14_000001_prepare_afghanistan_store.php` | idempotent: currency, locale, channel links, provinces, address rules; reverses only its own rows |
| Price conversion | `packages/Webkul/Core/src/Console/Commands/AfghanistanStoreSetup.php` | `bagisto:afghanistan:setup --rate=…`, one transaction, dry run, scoped to a channel |
| Currency | `SupportedCurrencies.php`, `Concerns/CurrencyFormatter.php` | AFN with its symbol, two decimals and a left position |
| Provinces | `Core/src/Helpers/AfghanistanProvinces.php`, `Installer/…/StatesTableSeeder.php`, `Core.php::groupedStatesByCountries()` | 34 provinces, Dari names, translated for the locale in use |
| Translations | all 14 packages' `Resources/lang/fa_AF/*.php`, `lang/fa_AF/*.php`, plus the keys added to English and the other 21 locales | Dari packs, key for key with English |
| Typography | `Core/src/Resources/views/blade/rtl-fonts.blade.php`, the four layouts, the invoice, `public/fonts/*` | self-hosted Vazirmatn and the sign of the Afghani |
| Right to left | 105 views and both panels' assets | logical utilities and mirrored icons, `font-family` and `dir` from the locale |
| Validation | `Core/src/Rules/PhoneNumber.php`, `{Shop,Admin}/…/plugins/vee-validate.js`, `locales/fa_AF.json` | Afghan phone formats and Dari messages, server and browser |
| Installer | `Installer/…/ConfigTableSeeder.php`, `StatesTableSeeder.php`, `CurrencyTableSeeder.php` | a fresh installation comes up in Dari, priced in Afghanis, with the provinces |
| Tests | `Core/tests/Unit/Afghanistan/`, `Shop/tests/Feature/Afghanistan/`, `Shop/tests/e2e-pw/tests/localization.spec.ts` | the Dari half of the suite |
| CI | `.github/workflows/pest_tests.yml`, `shop_playwright_tests.yml`, `admin_playwright_tests.yml`, `pint_tests.yml` | the Dari installation step, and the English installation the platform's own suite needs |
| Built assets | `public/themes/{shop,admin}/default/build/` | the bundles for the changes above |
| Documentation | this file, `CHANGELOG.md` | |

105 views changed in all, 86 translation files, 19 font files, 15 built bundles and 30 files of
code and configuration.

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
`rtl:`.

Two scripts keep this honest, and both run from the repository root:

```bash
python3 scripts/rtl-audit.py         # views still written for one direction only
python3 scripts/check-built-css.py   # classes used by views that are not in the built CSS
```

The first fails on any one-sided utility that has neither a logical form nor an
`rtl:` counterpart, and on direction-carrying icons without a mirrored variant;
it currently reports nothing. The second fails when a view was changed without
rebuilding the bundles, which is the mistake that makes an RTL fix silently
disappear; it currently reports every class present. Run both after touching a
view, then rebuild.

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
python3 scripts/rtl-audit.py              # the views' direction rules
python3 scripts/check-built-css.py        # views and built bundles still agree
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

Those two workflows run on `main`, on pull requests and on demand, rather than
on every push to a branch: each of them starts more than twenty jobs and takes
hours, and while they held the queue the Pest, Pint and translation jobs for the
same push never got to start. Start them from the Actions tab
(`workflow_dispatch`) when the browser suites are wanted.

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

## Deployment

### What cannot host this store: Vercel

The repository is wired to a Vercel project, and its check has been red on every
commit since the sources were imported - not because of anything in the Dari
work. Bagisto is a Laravel application: it needs PHP 8.3 with `intl`, `gd`,
`mbstring` and `pdo_mysql`, a MySQL database, and a **writable** `storage/`
directory for compiled views, caches and logs. Vercel's builder instead finds
the Vite configuration in the repository root, tries to build a static front end
from it, and fails; the platform has no PHP runtime in this project and no
database, and its functions have a size limit far below a Bagisto checkout with
its `vendor/` tree.

`vercel.json` therefore points Vercel's Ignored Build Step at
`scripts/vercel-ignore-build.sh`, which skips the build and prints the reason
into the build log: exit code 0 to Vercel means *skip*, exit code 1 means
*continue*. That stops the permanent red check and stops the build minutes being
spent. It does **not** put the store online - nothing is deployed to Vercel. If
the Vercel project is not wanted at all, unlink it in the Vercel dashboard
(Settings → Git) and delete `vercel.json` and `scripts/vercel-ignore-build.sh`.

### What does host it: the production image in this repository

`docker/production/` builds a single container that comes up installed,
migrated and seeded, with the web server and the database inside it - see
`docker/production/README.md` for the variants (nginx, Apache, OpenLiteSpeed ×
MySQL, MariaDB) and for the external-database mode.

The image is built from this repository and installs Bagisto from its
`.env.example`, which this work sets to the Afghan store:

```bash
APP_TIMEZONE=Asia/Kabul
APP_LOCALE=fa_AF
APP_CURRENCY=AFN
APP_DEFAULT_COUNTRY=AF
```

so an image built from this branch boots into a store that is already in Dari,
already priced in Afghanis, with the 34 provinces seeded and the channel linked
to the locale and the currency. Build and run it with:

```bash
docker build -f docker/production/nginx/Dockerfile -t empiremart:afghan .
docker run -d -p 80:80 --name empiremart \
    -e APP_URL=https://your-store.example \
    -e APP_KEY=base64:... \
    empiremart:afghan
```

`.github/workflows/docker_publish.yml` builds and pushes the image for all six
variants when a `v*` tag is pushed, or on demand. It needs two repository
secrets, `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` (a Docker Hub access token,
not the account password); without them that workflow stops at the push step.
It publishes under `webkul/bagisto`, so change `IMAGE_NAME` in the workflow if
the image should go to a different Docker Hub namespace.

Two things to know about the published image: it seeds Bagisto's demo products
at build time (upstream behaviour), and it writes the locale, currency and
timezone from `-e` variables into `.env` at boot. If the store has to start
empty, install from source instead - `A new installation` above - and delete the
demo products afterwards, or set `APP_URL`/`APP_KEY` and empty those tables.

### Deploying onto a PHP host

The same steps as `A new installation` above, on the server:

1. PHP 8.3 with `bcmath calendar curl exif gd gmp intl mbstring pdo_mysql soap
   sockets xml zip imagick`, Composer, Node 20+, MySQL 8.
2. `composer install --no-dev --optimize-autoloader`,
   `cp .env.example .env`, `php artisan key:generate`.
3. `.env`: `APP_URL`, the database credentials, and the Afghan settings listed
   in `What the store is set to` above.
4. `php artisan migrate --force` (the Afghan migration runs here on an existing
   database), then `php artisan bagisto:afghanistan:setup --rate=…` if there are
   prices in another currency to convert.
5. `npm ci && npm run build` in `packages/Webkul/Shop` and
   `packages/Webkul/Admin`, or copy the built bundles that are committed under
   `public/themes/{shop,admin}/default/build/`.
6. `php artisan storage:link`, `php artisan optimize`,
   and the queue worker and scheduler from the deployment section of Bagisto's
   own documentation.

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
* every logical utility and every `rtl:` mirror the views use is present in the
  built stylesheets of both panels (the check skips classes built from arbitrary
  values with commas in them, such as `group-[.inactive]/item:ltr:shadow-[…]`,
  which the upstream build already covers);
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
* A few icons the panels call for have no glyph in the fonts they ship with
  (`icon-warning` in both panels, `icon-clock` and `icon-error` in the admin
  panel), so those spans draw nothing. It is the same in upstream Bagisto, and
  the sentences next to them carry the meaning; the icons the shop's own
  pagination and filters called for were pointed at the glyphs that do exist
  (`icon-left-arrow`, `icon-right-arrow`, `icon-arrow-down`, `icon-cancel`).
* The language switcher falls back to a generic globe icon for Dari, because no
  flag image ships for it (`packages/Webkul/Installer/src/Resources/assets/images/seeders/locales/`);
  five other locales are in the same position. A Dari flag can be uploaded
  against the locale in Settings → Locales, which is where the other flags come
  from.
