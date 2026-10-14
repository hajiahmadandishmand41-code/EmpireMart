<?php

namespace Webkul\Core\Helpers;

/**
 * The provinces of Afghanistan, keyed by their ISO 3166-2:AF code.
 *
 * The catalogue is written down once because two places need it: the seeder
 * that fills a fresh installation and the migration that fills a store which
 * was already running. The Persian name differs from the Dari one in Iranian
 * usage (قندهار) and Afghan usage (کندهار), so both are kept.
 */
class AfghanistanProvinces
{
    const ALL = [
        'BDS' => ['en' => 'Badakhshan', 'fa' => 'بدخشان', 'fa_AF' => 'بدخشان'],
        'BDG' => ['en' => 'Badghis', 'fa' => 'بادغیس', 'fa_AF' => 'بادغیس'],
        'BGL' => ['en' => 'Baghlan', 'fa' => 'بغلان', 'fa_AF' => 'بغلان'],
        'BAL' => ['en' => 'Balkh', 'fa' => 'بلخ', 'fa_AF' => 'بلخ'],
        'BAM' => ['en' => 'Bamyan', 'fa' => 'بامیان', 'fa_AF' => 'بامیان'],
        'DAY' => ['en' => 'Daykundi', 'fa' => 'دایکندی', 'fa_AF' => 'دایکندی'],
        'FRA' => ['en' => 'Farah', 'fa' => 'فراه', 'fa_AF' => 'فراه'],
        'FYB' => ['en' => 'Faryab', 'fa' => 'فاریاب', 'fa_AF' => 'فاریاب'],
        'GHA' => ['en' => 'Ghazni', 'fa' => 'غزنی', 'fa_AF' => 'غزنی'],
        'GHO' => ['en' => 'Ghor', 'fa' => 'غور', 'fa_AF' => 'غور'],
        'HEL' => ['en' => 'Helmand', 'fa' => 'هلمند', 'fa_AF' => 'هلمند'],
        'HER' => ['en' => 'Herat', 'fa' => 'هرات', 'fa_AF' => 'هرات'],
        'JOW' => ['en' => 'Jowzjan', 'fa' => 'جوزجان', 'fa_AF' => 'جوزجان'],
        'KAB' => ['en' => 'Kabul', 'fa' => 'کابل', 'fa_AF' => 'کابل'],
        'KAN' => ['en' => 'Kandahar', 'fa' => 'قندهار', 'fa_AF' => 'کندهار'],
        'KAP' => ['en' => 'Kapisa', 'fa' => 'کاپیسا', 'fa_AF' => 'کاپیسا'],
        'KHO' => ['en' => 'Khost', 'fa' => 'خوست', 'fa_AF' => 'خوست'],
        'KNR' => ['en' => 'Kunar', 'fa' => 'کنر', 'fa_AF' => 'کنر'],
        'KDZ' => ['en' => 'Kunduz', 'fa' => 'کندز', 'fa_AF' => 'کندز'],
        'LAG' => ['en' => 'Laghman', 'fa' => 'لغمان', 'fa_AF' => 'لغمان'],
        'LOG' => ['en' => 'Logar', 'fa' => 'لوگر', 'fa_AF' => 'لوگر'],
        'NAN' => ['en' => 'Nangarhar', 'fa' => 'ننگرهار', 'fa_AF' => 'ننگرهار'],
        'NIM' => ['en' => 'Nimroz', 'fa' => 'نیمروز', 'fa_AF' => 'نیمروز'],
        'NUR' => ['en' => 'Nuristan', 'fa' => 'نورستان', 'fa_AF' => 'نورستان'],
        'PIA' => ['en' => 'Paktia', 'fa' => 'پکتیا', 'fa_AF' => 'پکتیا'],
        'PKT' => ['en' => 'Paktika', 'fa' => 'پکتیکا', 'fa_AF' => 'پکتیکا'],
        'PAN' => ['en' => 'Panjshir', 'fa' => 'پنجشیر', 'fa_AF' => 'پنجشیر'],
        'PAR' => ['en' => 'Parwan', 'fa' => 'پروان', 'fa_AF' => 'پروان'],
        'SAM' => ['en' => 'Samangan', 'fa' => 'سمنگان', 'fa_AF' => 'سمنگان'],
        'SAR' => ['en' => 'Sar-e Pol', 'fa' => 'سرپل', 'fa_AF' => 'سرپل'],
        'TAK' => ['en' => 'Takhar', 'fa' => 'تخار', 'fa_AF' => 'تخار'],
        'URU' => ['en' => 'Urozgan', 'fa' => 'ارزگان', 'fa_AF' => 'ارزگان'],
        'WAR' => ['en' => 'Maidan Wardak', 'fa' => 'میدان وردک', 'fa_AF' => 'میدان وردک'],
        'ZAB' => ['en' => 'Zabul', 'fa' => 'زابل', 'fa_AF' => 'زابل'],
    ];

    /**
     * The ISO 3166-2:AF codes of every province.
     *
     * @return string[]
     */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * The name of a province in the given locale, falling back to Dari and then
     * to the English name when the locale is not translated.
     */
    public static function name(string $code, string $locale): ?string
    {
        $names = self::ALL[$code] ?? null;

        if (! $names) {
            return null;
        }

        return $names[$locale] ?? $names['fa_AF'] ?? $names['en'];
    }
}
