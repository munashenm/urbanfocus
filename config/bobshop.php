<?php

return [
    /*
    | Bidorbuy / Bob Shop trade feed (XML). Products with zero quantity are excluded.
    | Contact hello@bidorbuy.co.za to register your feed URL after deployment.
    */
    'default_category' => 'Computers & Electronics',

    'max_description_length' => 8000,

    /*
    | Bob Shop BulkLoad CSV — Primary Category must be Bob Shop's numeric category ID
    | (find IDs in Seller View when listing manually). Map your category slugs below.
    */
    'default_primary_category_id' => env('BOBSHOP_PRIMARY_CATEGORY_ID', '2521'),

    'primary_category_ids' => [
        // 'networking' => '8226',
        // 'laptops-notebooks' => '2521',
    ],

    'listing' => [
        'type' => 'FIXED_PRICE',
        'location' => 'South Africa',
        'currency' => 'R',
        'condition' => 'NEW',
        'relist_option' => 'RELIST_DAILY_ALL',
        'relist_count' => '1',
        'listing_days' => (int) env('BOBSHOP_LISTING_DAYS', 30),
        'start_hour' => 1,
        'start_minute' => 0,
    ],

    'bulkload' => [
        'require_stock' => false,
        'min_quantity' => 1,
        'use_placeholder_image' => true,
    ],

    'xml_require_stock' => true,

    /*
    | Official Bob Shop XML trade feed (Bob-Shop-XML-Spec.xml).
    | Structure: ROOT > Version (optional) > Products > Product.
    */
    'xml' => [
        'include_version' => true,
        'allow_offers' => filter_var(env('BOBSHOP_ALLOW_OFFERS', false), FILTER_VALIDATE_BOOL),
        'location' => env('BOBSHOP_LOCATION', 'South Africa'),
        'shipping_product_class' => env('BOBSHOP_SHIPPING_CLASS', ''),
        'require_gtin' => filter_var(env('BOBSHOP_REQUIRE_GTIN', false), FILTER_VALIDATE_BOOL),
        'max_image_url_length' => 300,
    ],

    /*
    | Exact header row from Bob Shop BulkloadTradeSample.csv (45 columns).
    */
    'bulkload_headers' => [
        'Listing Type [mandatory] ENGLISH_AUCTION or FIXED_PRICE',
        'TITLE [Text Title for Item - Max 100 chars]',
        'Primary Category [mandatory Number]',
        'Secondary Category [optional Number - incurs a fee]',
        'Location [optional Specify location country of the product]',
        'Traders Reference [optional Max 100 chars]',
        'Start Date/Time [mandatory Date dd/mm/yyyy HH:mm]',
        'Stop Date/Time [mandatory Date dd/mm/yyyy HH:mm]',
        'Number of Items [mandatory Number]',
        'Number of Items Per Lot [optional Number]',
        'Auction Starting Bid Amount [mandatory for auctions Currency 0.00]',
        'Auction Bid Increment  [mandatory for auctions - specify an amount in decimal format. Currency 0.00]',
        'Auction Reserve Amount [optional only for auctions Currency 0.00]',
        'Buy Now Price (Fixed Price) [mandatory field for Buy Now items Currency 0.00]',
        'Recommended Retail Price [optional Currency 0.00]',
        'Allow Offers yes or ',
        'Currency [mandatory R for ZAR] R',
        'Item Condition [mandatory] NEW or SECOND_HAND or REFURBISHED',
        'Image URLs [Optional but recommended. Separate multiple URLs with colon \':\']',
        'Auto Re-list Options [optional]   RELIST_DAILY or RELIST_IMMEDIATELY or RELIST_DAILY_ALL or RELIST_IMMEDIATELY_ALL',
        'Number of Auto Relists [optional Number] The number of times to relist - if relisting is specified by the previous column',
        'Item Description [optional but recommended Max 8000 chars] Text or html description of the item for sale. html descriptions can provide attractive and sophisticated descriptions including additional images using html tags such as <img src=http://www.somedomain.com/images/item1.gif>',
        'Discreet Listing [optional] yes or ',
        'Home Page Featured Listing [NOT AVAILABLE] yes or ',
        'Category Page Featured Listing [NOT AVAILABLE] yes or ',
        'Priority Listing [NOT AVAILABLE] yes or ',
        'Highlighted Listing [NOT AVAILABLE] yes or ',
        'Bold Title Listing [NOT AVAILABLE] yes or ',
        'Promotional Listing [optional - Incurs a Fee] yes or ',
        'Premium Listings [NOT AVAILABLE]yes or ',
        'Copy Enhancements if Relisting [optional] yes or ',
        'Warranty Type [optional]  or NOT_OFFERED or REPLACEMENT or DEALER or MANUFACTURER',
        'Warranty Remarks [optional Max 300 chars]',
        'Guarantee Type [optional]  or MONEY_BACK_7_DAYS or MONEY_BACK_10_DAYS or MONEY_BACK_15_DAYS or MONEY_BACK_30_DAYS or REPLACEMENT_7_DAYS or REPLACEMENT_10_DAYS or REPLACEMENT_15_DAYS or REPLACEMENT_30_DAYS',
        'Guarantee Remarks [optional Max 300 chars]',
        'Prompts [optional]',
        'Shipping Option [optional]',
        'Automatic Extension Minutes [not available for most users blank or the number 1]',
        'Global Trade Item Number(GTIN) [optional] GTIN or ',
        'Width [optional in cm]',
        'Length [optional in cm]',
        'Height [optional in cm]',
        'Weight [optional in kg]',
        'Boost [optional - Incurs a Fee]',
        'End of Record [mandatory] set to \'End\'',
    ],
];
