<?php

namespace Webkul\Installer\Database\Seeders\Core;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigTableSeeder extends Seeder
{
    /**
     * Address settings a store set up for Afghanistan starts with.
     */
    protected const AFGHAN_ADDRESS_SETTINGS = [
        'customer.address.requirements.postcode' => '0',
        'customer.address.information.street_lines' => '2',
    ];

    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('core_config')->delete();

        $now = Carbon::now();

        DB::table('core_config')->insert([
            [
                'id' => 1,
                'code' => 'sales.checkout.shopping_cart.allow_guest_checkout',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'emails.general.notifications.emails.general.notifications.registration',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'code' => 'emails.general.notifications.emails.general.notifications.customer_registration_confirmation_mail_to_admin',
                'value' => '0',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'code' => 'emails.general.notifications.emails.general.notifications.customer_account_credentials',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'code' => 'emails.general.notifications.emails.general.notifications.new_order',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'code' => 'emails.general.notifications.emails.general.notifications.new_order_mail_to_admin',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'code' => 'emails.general.notifications.emails.general.notifications.new_invoice',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'code' => 'emails.general.notifications.emails.general.notifications.new_invoice_mail_to_admin',
                'value' => '0',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'code' => 'emails.general.notifications.emails.general.notifications.new_refund',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'code' => 'emails.general.notifications.emails.general.notifications.new_refund_mail_to_admin',
                'value' => '0',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'code' => 'emails.general.notifications.emails.general.notifications.new_shipment',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 12,
                'code' => 'emails.general.notifications.emails.general.notifications.new_shipment_mail_to_admin',
                'value' => '0',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 13,
                'code' => 'emails.general.notifications.emails.general.notifications.new_inventory_source',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 14,
                'code' => 'emails.general.notifications.emails.general.notifications.cancel_order',
                'value' => '1',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 15,
                'code' => 'emails.general.notifications.emails.general.notifications.cancel_order_mail_to_admin',
                'value' => '0',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 16,
                'code' => 'general.design.categories.category_view',
                'value' => 'sidebar',
                'channel_code' => null,
                'locale_code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->seedAfghanAddressSettings($now);
    }

    /**
     * The address settings an Afghan store starts with: no postal code is asked
     * for, because the country does not use them, and an address is written
     * over two lines. Both settings are stored per channel, which is why a row
     * is written for every channel that was just created.
     */
    protected function seedAfghanAddressSettings(Carbon $now): void
    {
        if (config('app.default_country') !== 'AF') {
            return;
        }

        foreach (DB::table('channels')->pluck('code') as $channelCode) {
            foreach (self::AFGHAN_ADDRESS_SETTINGS as $code => $value) {
                DB::table('core_config')->insert([
                    'code' => $code,
                    'value' => $value,
                    'channel_code' => $channelCode,
                    'locale_code' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
