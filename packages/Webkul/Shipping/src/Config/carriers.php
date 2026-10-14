<?php

return [
    'flatrate' => [
        'code' => 'flatrate',
        'title' => 'Flat Rate',
        'description' => 'Flat Rate Shipping',
        'active' => true,
        /**
         * The delivery charge a fresh install starts with, in the base currency of
         * the store, which is the Afghani here. It is a store setting, not a
         * conversion: the shop owner changes it under
         * Settings → Sales → Shipping Methods whenever the tariff moves.
         */
        'default_rate' => '150',
        'type' => 'per_unit',
        'class' => 'Webkul\Shipping\Carriers\FlatRate',
    ],

    'free' => [
        'code' => 'free',
        'title' => 'Free Shipping',
        'description' => 'Free Shipping',
        'active' => true,
        'default_rate' => '0',
        'class' => 'Webkul\Shipping\Carriers\Free',
    ],
];
