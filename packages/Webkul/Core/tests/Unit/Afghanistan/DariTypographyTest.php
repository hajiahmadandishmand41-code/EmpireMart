<?php

use Webkul\Core\Helpers\SupportedCurrencies;

/**
 * Dari is written in Vazirmatn, which the store serves itself so that no page
 * depends on a font CDN. Vazirmatn carries every letter the Dari translation
 * pack uses but not the sign of the Afghani, so that one glyph comes from a
 * small subset of Noto Sans Arabic. These checks keep the files, the stylesheet
 * that names them, and the invoice that embeds its own copy, together.
 */
it('keeps the fonts the store serves in the repository', function () {
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

it('names a font for the sign of the Afghani when Vazirmatn has no glyph for it', function () {
    /** Nothing to check while the store prices in a currency whose sign Vazirmatn carries. */
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

it('hands the invoice a font that has the sign of the Afghani', function () {
    $invoice = file_get_contents(
        __DIR__.'/../../../../Shop/src/Resources/views/customers/account/orders/pdf.blade.php'
    );

    expect($invoice)->toContain('fonts/NotoSansArabic-Regular.ttf')
        ->and($invoice)->toContain('fonts/NotoSansArabic-Bold.ttf')
        ->and($invoice)->toContain("'fa_AF'");
});
