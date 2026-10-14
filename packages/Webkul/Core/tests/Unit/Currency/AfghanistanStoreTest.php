<?php

use Illuminate\Support\Facades\DB;
use Webkul\Core\Helpers\SupportedCurrencies;
use Webkul\Core\Helpers\SupportedLocales;
use Webkul\Core\Models\Currency;

/**
 * The store sells in Afghanis from Afghanistan, so the currency and the locale
 * have to be known to the platform itself before any channel can be switched
 * over to them.
 */
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
    DB::table('currencies')->updateOrInsert(
        ['code' => 'AFN'],
        [
            'name' => 'افغانی افغانستان',
            'symbol' => '؋',
            'decimal' => 2,
            'group_separator' => '',
            'decimal_separator' => '',
            'currency_position' => 'left_with_space',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $afghani = Currency::where('code', 'AFN')->firstOrFail();

    $currentLocale = app()->getLocale();

    app()->setLocale('fa_AF');

    try {
        $formatted = core()->formatCurrency(1234.5, $afghani);
    } finally {
        app()->setLocale($currentLocale);
    }

    expect($formatted)->toBe('؋ ۱٬۲۳۴٫۵۰');
});
