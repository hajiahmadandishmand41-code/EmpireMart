<?php

namespace Webkul\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AfghanistanStoreSetup extends Command
{
    /**
     * The names of the tables that keep a monetary amount in the base currency
     * of a channel, and how to reach the amount inside them.
     */
    protected const MONEY_KEYS = [
        'sales.carriers.flatrate.default_rate',
        'sales.order_settings.minimum_order.minimum_order_amount',
    ];

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'bagisto:afghanistan:setup
                            {--rate= : Afghanis that one unit of the current base currency is worth}
                            {--channel=default : Code of the channel that is switched to the Afghani}
                            {--default-locale= : Also make the channel serve this locale by default}
                            {--dry-run : Report what would be converted without writing anything}
                            {--force : Convert without asking for confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch a channel to the Afghani (AFN) and convert the stored prices with an explicit rate';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $channel = DB::table('channels')->where('code', $this->option('channel'))->first();

        if (! $channel) {
            $this->components->error('No channel found with the code '.$this->option('channel').'.');

            return self::FAILURE;
        }

        $currency = DB::table('currencies')->where('code', 'AFN')->first();

        if (! $currency) {
            $this->components->error('The Afghani (AFN) is missing from the currencies table. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $baseCurrency = DB::table('currencies')->where('id', $channel->base_currency_id)->first();

        $this->components->info(sprintf(
            'Channel "%s" currently sells in %s and stores its prices in %s.',
            $channel->code,
            $baseCurrency->code ?? 'an unknown currency',
            $baseCurrency->code ?? 'an unknown currency',
        ));

        if (($baseCurrency->code ?? null) === 'AFN') {
            return $this->switchWithoutConversion($channel, $currency);
        }

        $rate = (float) $this->option('rate');

        if ($rate <= 0) {
            return $this->explainConversions($baseCurrency);
        }

        return $this->convert($channel, $currency, $baseCurrency, $rate);
    }

    /**
     * The channel already sells in Afghanis; only the wiring is missing.
     */
    protected function switchWithoutConversion(object $channel, object $currency): int
    {
        $this->components->warn('The channel already uses the Afghani, so no price is converted.');

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        $this->attachCurrency($channel, $currency);

        $this->applyDefaultLocale($channel);

        $this->components->success('The Afghani is attached to the channel.');

        return self::SUCCESS;
    }

    /**
     * Explain what the conversion does when no rate was supplied.
     */
    protected function explainConversions(object $baseCurrency): int
    {
        $this->newLine();

        $this->components->error('No conversion rate was given.');

        $this->line('The stored prices are amounts in '.$baseCurrency->code.'. Afghanis are a different unit, so the');
        $this->line('store needs the rate the shop owner sets the prices with. Nothing is converted');
        $this->line('until that rate is supplied, because relabelling '.$baseCurrency->code.' amounts as Afghanis');
        $this->line('would silently change every price in the catalogue.');
        $this->newLine();

        $this->components->info('The amounts that would be converted:');

        $this->table(['Table', 'Rows'], $this->moneyFootprint());

        $this->newLine();

        $this->components->info('Run the command again with the rate, for example:');

        $this->line('  php artisan bagisto:afghanistan:setup --rate=70.5 --dry-run');

        return self::SUCCESS;
    }

    /**
     * Convert every stored amount and move the channel to the Afghani.
     */
    protected function convert(object $channel, object $currency, object $baseCurrency, float $rate): int
    {
        $footprint = $this->moneyFootprint();

        $this->newLine();

        $this->table(
            ['Amount', 'Rows', 'Converted with'],
            array_map(fn ($row) => [$row[0], $row[1], 'x '.$rate], array_filter($footprint, fn ($row) => $row[1] > 0)),
        );

        $this->newLine();

        $this->components->info(sprintf(
            'Every amount in %s is multiplied by %s and stored as Afghanis.',
            $baseCurrency->code,
            $rate,
        ));

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to convert these amounts? A database backup is the only way back.')) {
            $this->components->warn('Nothing was converted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($channel, $currency, $rate) {
            $this->scaleAttributePrices($rate, $channel->code);

            $this->scaleCustomerGroupPrices($rate);

            $this->scaleRuleDiscounts($rate);

            $this->scaleConfiguration($rate);

            $this->attachCurrency($channel, $currency);

            DB::table('channels')->where('id', $channel->id)->update([
                'base_currency_id' => $currency->id,
                'updated_at' => now(),
            ]);
        });

        $this->applyDefaultLocale($channel);

        $this->newLine();

        $this->components->info('Rebuilding the price and flat indices…');

        /**
         * The prices are already written at this point, so a search engine or a
         * queue that is not answering must not make the command look failed.
         */
        try {
            Artisan::call('indexer:index', ['--type' => ['price', 'flat'], '--mode' => ['full']]);

            Artisan::call('product:price-rule:index');
        } catch (\Throwable $exception) {
            $this->components->warn('The indices could not be rebuilt: '.$exception->getMessage());
            $this->components->warn('Run `php artisan indexer:index --type=price --mode=full` before customers see the catalogue.');
        }

        $this->components->success('The channel now sells in Afghanis.');

        $this->newLine();

        $this->verify();

        return self::SUCCESS;
    }

    /**
     * Multiply the price attributes of every product.
     */
    protected function scaleAttributePrices(float $rate, string $channelCode): void
    {
        DB::table('product_attribute_values')
            ->whereIn('attribute_id', function ($query) {
                $query->select('id')->from('attributes')->where('type', 'price');
            })
            ->whereNotNull('float_value')
            /**
             * A price that is shared by the whole catalogue is stored once, with
             * no channel; a price that belongs to a single channel only carries
             * that channel's code, so the prices of the other channels stay as
             * they are.
             */
            ->where(function ($query) use ($channelCode) {
                $query->whereNull('channel')->orWhere('channel', $channelCode);
            })
            ->update(['float_value' => DB::raw('float_value * '.$rate)]);
    }

    /**
     * Multiply the fixed prices of every customer group price.
     */
    protected function scaleCustomerGroupPrices(float $rate): void
    {
        DB::table('product_customer_group_prices')
            ->where('value_type', 'fixed')
            ->whereNotNull('value')
            ->update(['value' => DB::raw('value * '.$rate)]);
    }

    /**
     * Multiply the discounts that are stored as an amount rather than a percentage.
     */
    protected function scaleRuleDiscounts(float $rate): void
    {
        DB::table('cart_rules')
            ->whereIn('action_type', ['by_fixed', 'cart_fixed'])
            ->whereNotNull('discount_amount')
            ->update(['discount_amount' => DB::raw('discount_amount * '.$rate)]);

        DB::table('catalog_rules')
            ->where('action_type', 'by_fixed')
            ->whereNotNull('discount_amount')
            ->update(['discount_amount' => DB::raw('discount_amount * '.$rate)]);
    }

    /**
     * Multiply the shipping and order settings that hold an amount.
     */
    protected function scaleConfiguration(float $rate): void
    {
        $rows = DB::table('core_config')
            ->whereIn('code', self::MONEY_KEYS)
            ->whereNotNull('value')
            ->get(['id', 'value']);

        foreach ($rows as $row) {
            DB::table('core_config')->where('id', $row->id)->update([
                'value' => (string) round((float) $row->value * $rate, 4),
            ]);
        }
    }

    /**
     * Offer the Afghani on the channel.
     */
    protected function attachCurrency(object $channel, object $currency): void
    {
        $attached = DB::table('channel_currencies')
            ->where('channel_id', $channel->id)
            ->where('currency_id', $currency->id)
            ->exists();

        if ($attached) {
            return;
        }

        DB::table('channel_currencies')->insert([
            'channel_id' => $channel->id,
            'currency_id' => $currency->id,
        ]);
    }

    /**
     * Move the default locale of the channel when one was asked for.
     */
    protected function applyDefaultLocale(object $channel): void
    {
        $code = $this->option('default-locale');

        if (! $code) {
            return;
        }

        $locale = DB::table('locales')->where('code', $code)->first();

        if (! $locale) {
            $this->components->warn('The locale '.$code.' does not exist, the default locale was left alone.');

            return;
        }

        $attached = DB::table('channel_locales')
            ->where('channel_id', $channel->id)
            ->where('locale_id', $locale->id)
            ->exists();

        if (! $attached) {
            DB::table('channel_locales')->insert([
                'channel_id' => $channel->id,
                'locale_id' => $locale->id,
            ]);
        }

        if ($channel->default_locale_id === $locale->id) {
            return;
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: the default locale would become '.$code.'.');

            return;
        }

        DB::table('channels')->where('id', $channel->id)->update([
            'default_locale_id' => $locale->id,
            'updated_at' => now(),
        ]);

        $this->components->info('The channel now serves '.$code.' by default.');
    }

    /**
     * Count the amounts a conversion would touch.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function moneyFootprint(): array
    {
        $attributePrices = DB::table('product_attribute_values')
            ->whereIn('attribute_id', function ($query) {
                $query->select('id')->from('attributes')->where('type', 'price');
            })
            ->whereNotNull('float_value')
            ->count();

        $customerGroupPrices = DB::table('product_customer_group_prices')
            ->where('value_type', 'fixed')
            ->whereNotNull('value')
            ->count();

        $cartRuleDiscounts = DB::table('cart_rules')
            ->whereIn('action_type', ['by_fixed', 'cart_fixed'])
            ->whereNotNull('discount_amount')
            ->count();

        $catalogRuleDiscounts = DB::table('catalog_rules')
            ->where('action_type', 'by_fixed')
            ->whereNotNull('discount_amount')
            ->count();

        $configuration = DB::table('core_config')
            ->whereIn('code', self::MONEY_KEYS)
            ->whereNotNull('value')
            ->count();

        return [
            ['Product prices, costs and special prices', $attributePrices],
            ['Customer group prices', $customerGroupPrices],
            ['Cart rule discounts', $cartRuleDiscounts],
            ['Catalog rule discounts', $catalogRuleDiscounts],
            ['Shipping and order settings', $configuration],
        ];
    }

    /**
     * Remind the shop owner what to check after the conversion.
     */
    protected function verify(): void
    {
        $this->components->info('Check these afterwards:');

        $this->line('  • Run `php artisan bagisto:translations:check` and open a product in the admin panel.');
        $this->line('  • Set APP_CURRENCY=AFN and APP_LOCALE=fa_AF in .env so the console and queues use the same settings.');
        $this->line('  • Walk through a checkout and print an invoice to confirm the totals and the Dari wording.');
        $this->line('  • Exchange rates are not touched: add them under Settings → Currencies if the store sells in more than one currency.');
    }
}
