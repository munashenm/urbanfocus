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
    /*
    | Extra terms matched against product name and short description.
    | Category terms above are always checked on products too.
    */
    'excluded_product_terms' => [
        'lady shaver',
        'electric shaver',
        'shaver',
        'dictionary',
        'dictionaries',
        'shoe rack',
        'shoe racks',
        'bathroom accessor',
        'vacuum sealer',
        'counterbook',
        'counter book',
        'a4 counterbook',
        'exercise book',
        'hard cover book',
        'soft cover book',
        'mop bucket',
        'ironing board',
        'clothes horse',
        'laundry basket',
        'food sealer',
        'vacuum bag',
    ],
];
