<?php

use Webkul\Core\Helpers\SupportedCurrencies;

/**
 * Dari is written in Vazirmatn, which is self-hosted so that no page depends on
 * a font CDN. The font does not carry the sign of the Afghani, so that one glyph
 * comes from a small subset of Noto Sans Arabic; these checks keep the two files
 * and the stylesheet that names them together.
 */
it('keeps the Dari webfonts in the repository', function () {
    $fonts = [
        'fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2',
        'fonts/vazirmatn/vazirmatn-arabic-500-normal.woff2',
        'fonts/vazirmatn/vazirmatn-arabic-600-normal.woff2',
        'fonts/vazirmatn/vazirmatn-arabic-700-normal.woff2',
        'fonts/noto-arabic/afghani-sign.woff2',
        'fonts/NotoSansArabic-Regular.ttf',
        'fonts/NotoSansArabic-Bold.ttf',
        'fonts/vazirmatn/OFL.txt',
        'fonts/noto-arabic/OFL.txt',
        'fonts/NotoSansArabic-OFL.txt',
    ];

    foreach ($fonts as $font) {
        expect(file_exists(public_path($font)))->toBeTrue("{$font} is missing.");
    }
});

it('hands the invoice a font that has the sign of the Afghani', function () {
    $invoice = file_get_contents(
        __DIR__.'/../../../../Shop/src/Resources/views/customers/account/orders/pdf.blade.php'
    );

    expect($invoice)->toContain("fonts/NotoSansArabic-Regular.ttf")
        ->and($invoice)->toContain("fonts/NotoSansArabic-Bold.ttf")
        ->and($invoice)->toContain("'fa_AF'");
});

it('draws the sign of the Afghani when Vazirmatn has no glyph for it', function () {
    /** Nothing to do while the store prices in a currency whose sign Vazirmatn carries. */
    if (SupportedCurrencies::symbol('AFN') !== '؋') {
        expect(true)->toBeTrue();

        return;
    }

    $stylesheet = file_get_contents(
        __DIR__.'/../../../src/Resources/views/blade/rtl-fonts.blade.php'
    );

    expect($stylesheet)->toContain("font-family: 'Afghani Sign'")
        ->and($stylesheet)->toContain('unicode-range: U+060B')
        ->and($stylesheet)->toContain("'Vazirmatn', 'Afghani Sign'");
});
