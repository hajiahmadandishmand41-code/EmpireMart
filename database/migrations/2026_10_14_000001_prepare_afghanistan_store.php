<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Core\Helpers\AfghanistanProvinces;
use Webkul\Core\Helpers\SupportedCurrencies;

return new class extends Migration
{
    /**
     * The ISO 4217 code of the Afghan currency.
     */
    protected const CURRENCY_CODE = 'AFN';

    /**
     * The locale code of Afghan Dari.
     */
    protected const LOCALE_CODE = 'fa_AF';

    /**
     * ISO 3166-1 alpha-2 code of Afghanistan.
     */
    protected const COUNTRY_CODE = 'AF';


    /**
     * Run the migrations.
     *
     * Every step is additive and guarded, so the migration can be re-run on a
     * store that was already prepared. No catalog price, order, channel currency
     * or channel locale that is already in use is touched: switching the base
     * currency of a channel changes what every stored price means, so it goes
     * through `php artisan bagisto:afghanistan:setup`, which converts the prices
     * with a rate the shop owner supplies.
     */
    public function up(): void
    {
        $this->ensureCurrency();

        $this->ensureLocale();

        $this->attachLocaleToChannels();

        $this->seedAfghanProvinces();

        $this->applyAfghanAddressRequirements();
    }

    /**
     * Reverse the migrations.
     *
     * Removes only the rows this migration created and that nothing else
     * references, so a rollback never breaks a running store.
     */
    public function down(): void
    {
        $this->removeAfghanProvinces();

        DB::table('core_config')
            ->where('code', 'customer.address.requirements.postcode')
            ->where('value', '0')
            ->delete();

        $locale = DB::table('locales')->where('code', self::LOCALE_CODE)->first();

        if (
            $locale
            && ! DB::table('channel_locales')->where('locale_id', $locale->id)->exists()
            && ! DB::table('channel_translations')->where('locale', self::LOCALE_CODE)->exists()
        ) {
            DB::table('locales')->where('id', $locale->id)->delete();
        }

        $currency = DB::table('currencies')->where('code', self::CURRENCY_CODE)->first();

        if (
            $currency
            && ! DB::table('channel_currencies')->where('currency_id', $currency->id)->exists()
            && ! DB::table('orders')->where('base_currency_code', self::CURRENCY_CODE)->exists()
            && ! DB::table('orders')->where('order_currency_code', self::CURRENCY_CODE)->exists()
        ) {
            DB::table('currencies')->where('id', $currency->id)->delete();
        }
    }

    /**
     * Make sure the Afghani exists as a currency the store can work with.
     */
    protected function ensureCurrency(): void
    {
        if (DB::table('currencies')->where('code', self::CURRENCY_CODE)->exists()) {
            return;
        }

        DB::table('currencies')->insert([
            'code' => self::CURRENCY_CODE,
            'name' => $this->translate('installer::app.seeders.core.currencies.'.self::CURRENCY_CODE, 'افغانی افغانستان'),
            'symbol' => SupportedCurrencies::symbol(self::CURRENCY_CODE),
            'decimal' => SupportedCurrencies::decimal(self::CURRENCY_CODE),
            /**
             * The separators stay empty on purpose: the formatter then keeps the
             * ones the active locale is written with, so Dari prices group with
             * "٬" and decimal prices with "٫" while an English admin panel keeps
             * using "," and ".".
             */
            'group_separator' => '',
            'decimal_separator' => '',
            'currency_position' => SupportedCurrencies::position(self::CURRENCY_CODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Make sure Dari is a locale the store can work with.
     */
    protected function ensureLocale(): void
    {
        if (DB::table('locales')->where('code', self::LOCALE_CODE)->exists()) {
            return;
        }

        DB::table('locales')->insert([
            'code' => self::LOCALE_CODE,
            'name' => $this->translate('installer::app.seeders.core.locales.'.self::LOCALE_CODE, 'دری'),
            'direction' => 'rtl',
            'logo_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Offer Dari on every channel that exists.
     *
     * The default locale of a channel is deliberately left alone; the shop
     * middleware keeps working with whatever the channel already points at.
     */
    protected function attachLocaleToChannels(): void
    {
        $locale = DB::table('locales')->where('code', self::LOCALE_CODE)->first();

        if (! $locale) {
            return;
        }

        foreach (DB::table('channels')->pluck('id') as $channelId) {
            $attached = DB::table('channel_locales')
                ->where('channel_id', $channelId)
                ->where('locale_id', $locale->id)
                ->exists();

            if ($attached) {
                continue;
            }

            DB::table('channel_locales')->insert([
                'channel_id' => $channelId,
                'locale_id' => $locale->id,
            ]);
        }
    }

    /**
     * Insert the provinces of Afghanistan with their translations.
     */
    protected function seedAfghanProvinces(): void
    {
        if (! Schema::hasTable('country_states')) {
            return;
        }

        $countryId = DB::table('countries')->where('code', self::COUNTRY_CODE)->value('id');

        $locales = DB::table('locales')
            ->whereIn('code', ['en', 'fa', self::LOCALE_CODE])
            ->pluck('code')
            ->all();

        foreach (AfghanistanProvinces::codes() as $code) {
            $state = DB::table('country_states')
                ->where('country_code', self::COUNTRY_CODE)
                ->where('code', $code)
                ->first();

            if ($state) {
                $id = $state->id;
            } else {
                $id = DB::table('country_states')->insertGetId([
                    'country_id' => $countryId,
                    'country_code' => self::COUNTRY_CODE,
                    'code' => $code,
                    'default_name' => AfghanistanProvinces::name($code, 'en'),
                ]);
            }

            foreach ($locales as $locale) {
                $translated = DB::table('country_state_translations')
                    ->where('country_state_id', $id)
                    ->where('locale', $locale)
                    ->exists();

                if ($translated) {
                    continue;
                }

                DB::table('country_state_translations')->insert([
                    'country_state_id' => $id,
                    'locale' => $locale,
                    'default_name' => AfghanistanProvinces::name($code, $locale),
                ]);
            }
        }
    }

    /**
     * Afghanistan issues no postal codes, so the field must not be mandatory.
     *
     * Only written when the store has not decided the setting itself, and only
     * for the channel the value is meant for.
     */
    protected function applyAfghanAddressRequirements(): void
    {
        $channel = DB::table('channels')->orderBy('id')->value('code');

        $exists = DB::table('core_config')
            ->where('code', 'customer.address.requirements.postcode')
            ->where(function ($query) use ($channel) {
                $query->whereNull('channel_code')->orWhere('channel_code', $channel);
            })
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('core_config')->insert([
            'code' => 'customer.address.requirements.postcode',
            'value' => '0',
            'channel_code' => $channel,
            'locale_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Remove the provinces and translations added for Afghanistan.
     */
    protected function removeAfghanProvinces(): void
    {
        if (! Schema::hasTable('country_states')) {
            return;
        }

        $ids = DB::table('country_states')
            ->where('country_code', self::COUNTRY_CODE)
            ->whereIn('code', AfghanistanProvinces::codes())
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('country_state_translations')->whereIn('country_state_id', $ids)->delete();

        DB::table('country_states')->whereIn('id', $ids)->delete();
    }

    /**
     * Resolve a translation, falling back to a literal when the key is missing.
     */
    protected function translate(string $key, string $fallback): string
    {
        $translated = trans($key, [], self::LOCALE_CODE);

        return $translated === $key ? $fallback : $translated;
    }
};
