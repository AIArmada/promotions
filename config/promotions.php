<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],
        'json_column_type' => env('PROMOTIONS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => 'USD',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
    ],

];
