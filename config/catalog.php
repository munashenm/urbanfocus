<?php

return [
    /*
    | Non-IT category/product terms — matched case-insensitively against
    | category names (and import category paths). Products in matching
    | categories are removed and future CSV rows are skipped.
    */
    'excluded_category_terms' => [
        'lady shaver',
        'electric shaver',
        'dictionary',
        'dictionaries',
        'shoe rack',
        'shoe racks',
        'bathroom accessor',
        'bathroom accessories',
        'vacuum sealer',
        'counterbook',
        'counter book',
        'a4 counterbook',
        'personal care',
        'beauty',
        'cosmetic',
        'homeware',
        'home ware',
        'kitchenware',
        'stationery',
        'fashion',
        'apparel',
        'clothing',
        'footwear',
        'furniture',
        'toys',
        'garden',
        'pets',
        'health & beauty',
        'home & living',
        'household',
    ],
];
