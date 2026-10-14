<?php

use Illuminate\Support\Facades\DB;
use Webkul\Core\Helpers\AfghanistanProvinces;
use Webkul\Core\Helpers\SupportedCurrencies;
use Webkul\Core\Helpers\SupportedLocales;
use Webkul\Core\Models\Currency;

/**
 * The store sells in Afghanis from Afghanistan, so these pin what the platform
 * has to know before any channel can be switched over to them, and what the
 * conversion command does with the money it finds.
 */

/**
 * The Afghani as the store seeds it.
 */
function afghaniCurrency(): object
{
    DB::table('currencies')->updateOrInsert(
        ['code' => 'AFN'],
        [
            'name' => 'افغانی افغانستان',
            'symbol' => SupportedCurrencies::symbol('AFN'),
            'decimal' => SupportedCurrencies::decimal('AFN'),
            'group_separator' => '',
            'decimal_separator' => '',
            'currency_position' => SupportedCurrencies::position('AFN'),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    return DB::table('currencies')->where('code', 'AFN')->first();
}

/**
 * A currency the store is leaving behind, so the conversion has something to do.
 */
function formerCurrency(): object
{
    DB::table('currencies')->updateOrInsert(
        ['code' => 'USD'],
        [
            'name' => 'United States Dollar',
            'symbol' => '$',
            'decimal' => 2,
            'group_separator' => ',',
            'decimal_separator' => '.',
            'currency_position' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    return DB::table('currencies')->where('code', 'USD')->first();
}

/**
 * Sell the default channel in the given currency, with a delivery charge set.
 */
function sellIn(object $currency, string $flatRate = '10'): void
{
    DB::table('channels')->where('code', 'default')->update(['base_currency_id' => $currency->id]);

    DB::table('core_config')->updateOrInsert(
        ['code' => 'sales.carriers.flatrate.default_rate', 'channel_code' => 'default'],
        ['value' => $flatRate, 'locale_code' => null, 'created_at' => now(), 'updated_at' => now()],
    );
}

/**
 * The delivery charge the store has stored.
 */
function flatRate(): ?string
{
    return DB::table('core_config')
        ->where('code', 'sales.carriers.flatrate.default_rate')
        ->where('channel_code', 'default')
        ->value('value');
}

it('knows the Afghani as a currency', function () {
    expect(SupportedCurrencies::codes())->toContain('AFN')
        ->and(SupportedCurrencies::symbol('AFN'))->toBe('؋')
        ->and(SupportedCurrencies::decimal('AFN'))->toBe(2)
        ->and(SupportedCurrencies::position('AFN'))->toBe('left_with_space');
});

it('leaves the symbol position of other currencies to the locale', function () {
    expect(SupportedCurrencies::position('USD'))->toBeNull()
        ->and(SupportedCurrencies::position('INR'))->toBeNull();
});

it('knows Dari as a right to left locale', function () {
    expect(SupportedLocales::codes())->toContain('fa_AF')
        ->and(SupportedLocales::direction('fa_AF'))->toBe('rtl');
});

it('carries every province of Afghanistan in Dari and in Persian', function () {
    expect(AfghanistanProvinces::codes())->toHaveCount(34);

    foreach (AfghanistanProvinces::codes() as $code) {
        expect(AfghanistanProvinces::name($code, 'fa_AF'))->not->toBeEmpty()
            ->and(AfghanistanProvinces::name($code, 'en'))->not->toBeEmpty();
    }

    /** Kandahar is written with a ق in Iranian Persian and with a ک in Dari. */
    expect(AfghanistanProvinces::name('KAN', 'fa'))->toBe('قندهار')
        ->and(AfghanistanProvinces::name('KAN', 'fa_AF'))->toBe('کندهار')
        ->and(AfghanistanProvinces::name('KAB', 'fa_AF'))->toBe('کابل');
});

it('ships a Dari translation for every English translation file', function () {
    $langRoots = collect(glob(base_path('packages/Webkul/*/src/Resources/lang')))
        ->push(base_path('lang'));

    $missing = [];

    foreach ($langRoots as $root) {
        $englishDirectory = $root.'/en';

        if (! is_dir($englishDirectory)) {
            continue;
        }

        foreach (glob($englishDirectory.'/*.php') as $englishFile) {
            $dariFile = $root.'/fa_AF/'.basename($englishFile);

            if (! file_exists($dariFile)) {
                $missing[] = str_replace(base_path().'/', '', $dariFile);
            }
        }
    }

    expect($missing)->toBeEmpty();
});

it('writes an Afghani price with Dari digits and the Afghani sign', function () {
    $afghani = Currency::find(afghaniCurrency()->id);

    $previousLocale = app()->getLocale();

    app()->setLocale('fa_AF');

    try {
        $formatted = core()->formatCurrency(1234.5, $afghani);
    } finally {
        app()->setLocale($previousLocale);
    }

    expect($formatted)->toBe('؋ ۱٬۲۳۴٫۵۰');
});

it('names a province in the language the storefront is read in', function () {
    $kabul = DB::table('country_states')
        ->where('country_code', 'AF')
        ->where('code', 'KAB')
        ->first();

    if (! $kabul) {
        $this->markTestSkipped('This installation has no Afghan provinces seeded.');
    }

    DB::table('country_state_translations')->updateOrInsert(
        ['country_state_id' => $kabul->id, 'locale' => 'fa_AF'],
        ['default_name' => 'کابل'],
    );

    $previousLocale = app()->getLocale();

    app()->setLocale('fa_AF');

    try {
        $named = collect(core()->groupedStatesByCountries()['AF'] ?? [])
            ->firstWhere('code', 'KAB');
    } finally {
        app()->setLocale($previousLocale);
    }

    expect($named)->not->toBeNull()
        ->and($named->default_name)->toBe('کابل');
});

it('converts nothing while no exchange rate is given', function () {
    afghaniCurrency();

    sellIn(formerCurrency(), '10');

    $this->artisan('bagisto:afghanistan:setup', ['--channel' => 'default'])
        ->expectsOutputToContain('No conversion rate was given.')
        ->assertExitCode(0);

    expect(flatRate())->toBe('10');
});

it('reports a channel that does not exist', function () {
    $this->artisan('bagisto:afghanistan:setup', ['--channel' => 'a-channel-that-is-not-there'])
        ->expectsOutputToContain('No channel found')
        ->assertExitCode(1);
});

it('converts the amounts it finds and moves the channel to the Afghani', function () {
    $afghani = afghaniCurrency();

    sellIn(formerCurrency(), '10');

    $this->artisan('bagisto:afghanistan:setup', [
        '--channel' => 'default',
        '--rate' => 70,
        '--force' => true,
    ])->assertExitCode(0);

    expect(flatRate())->toBe('700')
        ->and(DB::table('channels')->where('code', 'default')->value('base_currency_id'))->toBe($afghani->id);
});

it('leaves the amounts alone during a dry run', function () {
    afghaniCurrency();

    sellIn(formerCurrency(), '10');

    $this->artisan('bagisto:afghanistan:setup', [
        '--channel' => 'default',
        '--rate' => 70,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run: nothing was written.')
        ->assertExitCode(0);

    expect(flatRate())->toBe('10');
});
