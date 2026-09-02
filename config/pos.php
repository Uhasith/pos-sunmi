<?php

return [
    'receipt' => [
        'shop' => [
            'name' => env('POS_RECEIPT_SHOP_NAME'),
            'address_line_1' => env('POS_RECEIPT_ADDRESS_LINE_1'),
            'address_line_2' => env('POS_RECEIPT_ADDRESS_LINE_2'),
            'postal_code' => env('POS_RECEIPT_POSTAL_CODE'),
            'city' => env('POS_RECEIPT_CITY'),
            'country_code' => env('POS_RECEIPT_COUNTRY_CODE', 'NL'),
            'vat_number' => env('POS_RECEIPT_VAT_NUMBER'),
            'chamber_of_commerce_number' => env('POS_RECEIPT_KVK_NUMBER'),
        ],
    ],
];
