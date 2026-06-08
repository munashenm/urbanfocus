<?php

return [
    'index_title' => 'IT Insights & Guides | Urban Focus Blog',
    'index_description' => 'Buying guides, networking tips, software licensing, cybersecurity and IT procurement insights for South African businesses from Urban Focus.',

    'categories' => [
        'laptops' => [
            'label' => 'Laptops & Computers',
            'description' => 'Business laptops, desktops and workstations',
            'cta_label' => 'Browse laptops',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'business-laptops-south-africa'],
        ],
        'software' => [
            'label' => 'Software & Licensing',
            'description' => 'Microsoft 365, antivirus, licensing and productivity software',
            'cta_label' => 'Request software licensing',
            'cta_route' => 'b2b.quote',
            'cta_params' => [],
        ],
        'procurement' => [
            'label' => 'IT Procurement',
            'description' => 'Bulk orders, RFQs and B2B supply',
            'cta_label' => 'Request a quote',
            'cta_route' => 'b2b.quote',
            'cta_params' => [],
        ],
        'networking' => [
            'label' => 'Networking',
            'description' => 'Switches, Wi‑Fi, fibre and ISP infrastructure',
            'cta_label' => 'Shop networking',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'ubiquiti-supplier-south-africa'],
        ],
        'education' => [
            'label' => 'Education Technology',
            'description' => 'IT hardware and connectivity for schools and campuses',
            'cta_label' => 'Talk to our education team',
            'cta_route' => 'b2b.quote',
            'cta_params' => [],
        ],
        'cybersecurity' => [
            'label' => 'Cybersecurity',
            'description' => 'Network security, firewalls, surveillance and data protection',
            'cta_label' => 'Secure your business',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'cctv-equipment-supplier'],
        ],
        'business' => [
            'label' => 'Business Technology',
            'description' => 'Productivity, infrastructure and IT strategy for South African businesses',
            'cta_label' => 'Explore business IT',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'corporate-it-supplier-south-africa'],
        ],
        'guides' => [
            'label' => 'Buying Guides',
            'description' => 'How to choose IT hardware and software',
            'cta_label' => 'Browse products',
            'cta_route' => 'shop.index',
            'cta_params' => [],
        ],
        'cctv' => [
            'label' => 'CCTV & Security',
            'description' => 'Cameras, NVRs and commercial surveillance',
            'cta_label' => 'Shop CCTV',
            'cta_route' => 'solutions.show',
            'cta_params' => ['slug' => 'cctv-equipment-supplier'],
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
        'laptops' => 'images/blog/laptops.svg',
        'software' => 'images/blog/software.svg',
        'procurement' => 'images/blog/procurement.svg',
        'networking' => 'images/blog/networking.svg',
        'education' => 'images/blog/education.svg',
        'cybersecurity' => 'images/blog/cybersecurity.svg',
        'business' => 'images/blog/business.svg',
        'guides' => 'images/blog/guides.svg',
        'cctv' => 'images/blog/cctv.svg',
        'news' => 'images/blog/news.svg',
    ],
];
