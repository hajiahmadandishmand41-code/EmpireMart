<?php

use Illuminate\Support\Facades\DB;

/**
 * Switching the base currency of a channel changes what every stored price
 * means, so the command has to be explicit about the rate it converts with and
 * must never relabel prices on its own.
 */
beforeEach(function () {
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

    $this->afghani = DB::table('currencies')->where('code', 'AFN')->first();
});

it('asks for an exchange rate instead of converting on its own', function () {
    $channel = DB::table('channels')->where('code', 'default')->first();

    $before = DB::table('product_attribute_values')->sum('float_value');

    $this->artisan('bagisto:afghanistan:setup', ['--channel' => 'default'])
        ->expectsOutputToContain('No conversion rate was given.')
        ->assertSuccessful();

    expect(DB::table('product_attribute_values')->sum('float_value'))->toBe($before)
        ->and(DB::table('channels')->where('id', $channel->id)->value('base_currency_id'))->toBe($channel->base_currency_id);
});

it('converts every stored price and moves the channel to the Afghani', function () {
    $channel = DB::table('channels')->where('code', 'default')->first();

    $baseCurrencyCode = DB::table('currencies')->where('id', $channel->base_currency_id)->value('code');

    $prices = DB::table('product_attribute_values')
        ->whereIn('attribute_id', function ($query) {
            $query->select('id')->from('attributes')->where('type', 'price');
        })
        ->whereNotNull('float_value')
        ->pluck('float_value', 'id');

    if ($prices->isEmpty()) {
        $this->markTestSkipped('The seeded catalogue has no price attribute values to convert.');
    }

    $this->artisan('bagisto:afghanistan:setup', [
        '--channel' => 'default',
        '--rate' => 70,
        '--force' => true,
    ])->assertSuccessful();

    $converted = DB::table('product_attribute_values')
        ->whereIn('id', $prices->keys())
        ->pluck('float_value', 'id');

    foreach ($prices as $id => $price) {
        expect(round((float) $converted[$id], 4))
            ->toBe(round((float) $price * 70, 4));
    }

    $afghaniCurrencyId = DB::table('currencies')->where('code', 'AFN')->value('id');

    expect(DB::table('channels')->where('id', $channel->id)->value('base_currency_id'))->toBe($afghaniCurrencyId)
        ->and(DB::table('channel_currencies')
            ->where('channel_id', $channel->id)
            ->where('currency_id', $afghaniCurrencyId)
            ->exists()
        )->toBeTrue();
});

it('leaves the store alone during a dry run', function () {
    $channel = DB::table('channels')->where('code', 'default')->first();

    $prices = DB::table('product_attribute_values')->pluck('float_value', 'id')->all();

    $this->artisan('bagisto:afghanistan:setup', [
        '--channel' => 'default',
        '--rate' => 70,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run: nothing was written.')
        ->assertSuccessful();

    expect(DB::table('product_attribute_values')->pluck('float_value', 'id')->all())->toBe($prices)
        ->and(DB::table('channels')->where('id', $channel->id)->value('base_currency_id'))->toBe($channel->base_currency_id);
});

it('reports a channel that does not exist', function () {
    $this->artisan('bagisto:afghanistan:setup', ['--channel' => 'a-channel-that-is-not-there'])
        ->expectsOutputToContain('No channel found')
        ->assertFailed();
});
