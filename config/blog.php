<?php

return [
    'index_title' => 'IT Insights & Guides | Urban Focus Blog',
    'index_description' => 'Buying guides, networking tips, CCTV advice and IT procurement insights for South African businesses from Urban Focus.',

    'categories' => [
        'networking' => [
            'label' => 'Networking',
            'description' => 'Switches, Wi‑Fi, fibre and ISP infrastructure',
            'cta_label' => 'Shop networking',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'ubiquiti-supplier-south-africa'],
        ],
        'laptops' => [
            'label' => 'Business Laptops',
            'description' => 'Fleet procurement and corporate notebooks',
            'cta_label' => 'Browse laptops',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'business-laptops-south-africa'],
        ],
        'cctv' => [
            'label' => 'CCTV & Security',
            'description' => 'Cameras, NVRs and commercial surveillance',
            'cta_label' => 'Shop CCTV',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'cctv-equipment-supplier'],
        ],
        'procurement' => [
            'label' => 'Procurement',
            'description' => 'Bulk orders, RFQs and B2B supply',
            'cta_label' => 'Request a quote',
            'cta_route' => 'b2b.quote',
            'cta_params' => [],
        ],
        'guides' => [
            'label' => 'Buying Guides',
            'description' => 'How to choose IT hardware and software',
            'cta_label' => 'Browse products',
            'cta_route' => 'shop.index',
            'cta_params' => [],
        ],
        'news' => [
            'label' => 'Industry News',
            'description' => 'South African and global IT headlines',
            'cta_label' => 'View all products',
            'cta_route' => 'shop.index',
            'cta_params' => [],
        ],
    ],

    'category_placeholders' => [
        'networking' => 'images/blog/networking.svg',
        'laptops' => 'images/blog/laptops.svg',
        'cctv' => 'images/blog/cctv.svg',
        'procurement' => 'images/blog/procurement.svg',
        'guides' => 'images/blog/guides.svg',
        'news' => 'images/blog/news.svg',
    ],
];
