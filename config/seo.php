<?php

return [
    'defaults' => [
        'title_suffix' => '| Urban Focus',
        'description' => 'Urban Focus — South African IT distributor. Buy laptops, networking, CCTV, Nitrokey FIDO2 security keys and specialist IT with nationwide delivery, VAT invoices and Paystack checkout.',
        'keywords' => 'IT supplier South Africa, networking equipment South Africa, buy laptops South Africa, Ubiquiti supplier, Hikvision supplier, business IT supplier',
        'locale' => 'en_ZA',
        'country' => 'ZA',
        'min_description_length' => 120,
        'max_description_length' => 160,
    ],

    'verification' => [
        'google' => env('GOOGLE_SITE_VERIFICATION'),
        'bing' => env('BING_SITE_VERIFICATION'),
    ],

    'analytics' => [
        'ga4_id' => env('GA4_MEASUREMENT_ID'),
        'google_ads_id' => env('GOOGLE_ADS_ID'),
        'meta_pixel_id' => env('META_PIXEL_ID'),
        'tiktok_pixel_id' => env('TIKTOK_PIXEL_ID'),
    ],

    'cache' => [
        'sitemap_ttl' => (int) env('SEO_SITEMAP_CACHE_TTL', 3600),
        'feed_ttl' => (int) env('SEO_FEED_CACHE_TTL', 21600),
    ],

    'indexing' => [
        'indexnow_key' => env('INDEXNOW_KEY'),
        'ping_search_engines' => (bool) env('SEO_PING_SEARCH_ENGINES', false),
    ],

    'sa_keywords' => [
        'buy laptops South Africa',
        'networking equipment South Africa',
        'Ubiquiti supplier South Africa',
        'Hikvision supplier South Africa',
        'cheap IT equipment South Africa',
        'computer accessories South Africa',
        'gaming laptops South Africa',
        'business IT supplier',
        'Nitrokey South Africa',
        'hardware security keys South Africa',
        'PiKVM South Africa',
        'Proxmox South Africa',
        'Nextcloud South Africa',
        'Hailo AI South Africa',
        'OPNsense firewall South Africa',
        'industrial IoT gateway South Africa',
    ],

    'sa_cities' => [
        'Johannesburg',
        'Cape Town',
        'Durban',
        'Pretoria',
        'Centurion',
        'Limpopo',
    ],

    'robots_disallow' => [
        '/admin',
        '/cart',
        '/checkout',
        '/account',
        '/login',
        '/register',
        '/storage/',
    ],

    'faq' => [
        [
            'group' => 'Orders & Payment',
            'question' => 'Which payment methods do you accept?',
            'answer' => 'We accept Paystack secure checkout including credit and debit card, Instant EFT, Apple Pay, Google Pay and manual EFT bank transfer. All prices on the website are shown in South African Rand (ZAR) and include VAT where applicable.',
        ],
        [
            'group' => 'Orders & Payment',
            'question' => 'Do you supply VAT invoices?',
            'answer' => 'Yes. VAT invoices are supplied for business, corporate, government and registered customer orders. Include your company details at checkout or request an invoice when placing a B2B quote.',
        ],
        [
            'group' => 'Orders & Payment',
            'question' => 'Can I get a formal quote before ordering?',
            'answer' => 'Yes. Use our Request a Quote or Upload RFQ options for bulk orders, corporate procurement and project pricing. Our team will respond with a formal quotation including VAT and delivery options.',
        ],
        [
            'group' => 'Delivery',
            'question' => 'Does Urban Focus deliver nationwide in South Africa?',
            'answer' => 'Yes. Urban Focus delivers IT products across South Africa including Johannesburg, Cape Town, Durban, Pretoria, Centurion and regional areas via trusted couriers.',
        ],
        [
            'group' => 'Delivery',
            'question' => 'How much does delivery cost?',
            'answer' => 'Standard courier delivery is charged at a flat rate on most orders. Free delivery may apply on orders above the threshold shown at checkout. Large or heavy items may require a manual shipping quote.',
        ],
        [
            'group' => 'Delivery',
            'question' => 'How long will my order take to arrive?',
            'answer' => 'In-stock items typically ship within 1–3 business days. Delivery time depends on your location and courier schedules. You will receive tracking details once your order has been dispatched.',
        ],
        [
            'group' => 'Returns & Warranty',
            'question' => 'What is your returns policy?',
            'answer' => 'Defective, damaged or incorrectly supplied items may be returned within 7 calendar days of delivery, subject to our returns policy. Products must be unused and in original packaging where applicable. See our Returns page for full details.',
        ],
        [
            'group' => 'Returns & Warranty',
            'question' => 'Are products covered by warranty?',
            'answer' => 'Yes. Products are supplied with manufacturer warranty where applicable. Warranty terms vary by brand and product type. See our Warranty Terms page or contact us for support on a specific item.',
        ],
        [
            'group' => 'Products & Support',
            'question' => 'Are your products genuine?',
            'answer' => 'Urban Focus supplies genuine IT products from authorised distribution channels. We stock leading brands including networking, CCTV, laptops, servers and software licensing for business and installer customers.',
        ],
        [
            'group' => 'Products & Support',
            'question' => 'What if a product is out of stock?',
            'answer' => 'If an item is temporarily out of stock you can use the Notify Me form on the product page, request sourcing via our Source a Product page, or contact us for an expected availability date.',
        ],
        [
            'group' => 'Products & Support',
            'question' => 'Do you offer B2B and bulk pricing?',
            'answer' => 'Yes. Urban Focus supports corporate, government, education and installer customers with bulk pricing, formal quotes, RFQ processing and dedicated account support.',
        ],
        [
            'group' => 'Products & Support',
            'question' => 'How can I contact Urban Focus?',
            'answer' => 'Contact us by phone, email or the Contact page on our website. Our team is available during business hours to assist with orders, quotes and product enquiries.',
        ],
    ],
];
