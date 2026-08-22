<?php

return [
    'hero_slides' => [
        [
            'eyebrow' => 'Enterprise & SME IT Supply',
            'title' => 'South Africa\'s Trusted IT Distributor',
            'subtitle' => 'Networking, laptops, servers, software licensing and security — backed by professional support, VAT invoices, and nationwide delivery.',
            'cta_primary' => ['label' => 'Shop Now', 'route' => 'shop.index'],
            'cta_secondary' => ['label' => 'Request a Quote', 'route' => 'b2b.quote'],
            'theme' => 'navy',
        ],
        [
            'eyebrow' => 'Networking & Connectivity',
            'title' => 'Professional Networking Solutions',
            'subtitle' => 'Ubiquiti, MikroTik, Cambium, TP-Link and enterprise switches — for ISPs, installers and businesses.',
            'cta_primary' => ['label' => 'Shop Networking', 'route' => 'shop.index', 'params' => ['category' => 'networking-connectivity']],
            'cta_secondary' => ['label' => 'Upload RFQ', 'route' => 'b2b.rfq'],
            'theme' => 'blue',
        ],
        [
            'eyebrow' => 'Business Computing',
            'title' => 'Business Laptops & Workstations',
            'subtitle' => 'Dell, HP, Lenovo and Microsoft devices for corporate, government and education deployments.',
            'cta_primary' => ['label' => 'Shop Laptops', 'route' => 'shop.index', 'params' => ['category' => 'computing-office/laptops']],
            'cta_secondary' => ['label' => 'Bulk Pricing', 'route' => 'b2b.quote'],
            'theme' => 'dark',
        ],
    ],

    'solution_blocks' => [
        [
            'title' => 'Networking Solutions',
            'subtitle' => 'Switches, access points, routers & fibre',
            'category_path' => 'networking-connectivity',
            'icon' => 'network',
        ],
        [
            'title' => 'Business Laptops',
            'subtitle' => 'Corporate notebooks & mobile workstations',
            'category_path' => 'computing-office/laptops',
            'icon' => 'laptop',
        ],
        [
            'title' => 'CCTV & Security',
            'subtitle' => 'Cameras, NVRs & access control',
            'category_path' => 'security-surveillance',
            'icon' => 'security',
        ],
        [
            'title' => 'Software Licensing',
            'subtitle' => 'Microsoft, antivirus & subscriptions',
            'category_path' => 'computing-office/software',
            'icon' => 'software',
        ],
    ],

    'category_icons' => [
        'computing-office' => '💻',
        'networking-connectivity' => '🌐',
        'security-surveillance' => '📹',
        'solar-power' => '⚡',
        'digital-signage' => '🖵',
        'gaming-entertainment' => '🎮',
        'business-retail' => '🏪',
        'industrial-iot' => '🏭',
    ],

    /*
    | Homepage "Top Sellers": products must match a brand below (case-insensitive)
    | and sit in one of the categories listed under top_seller_categories.
    */
    'top_seller_brands' => [
        'Dahua',
        'TP-Link',
        'Dell',
        'Asustor',
        'ASUS',
        'Asus',
        'Hikvision',
        'D-Link',
        'Goldtool',
        'Intellinet',
        'Ubiquiti',
        'MikroTik',
        'Cambium Networks',
        'HP',
        'Lenovo',
        'Microsoft',
        'Sophos',
        'Huawei',
        'Samsung',
        'Logitech',
        'LG',
        'Yealink',
        'Starlink',
    ],

    'top_seller_categories' => [
        'networking-connectivity',
        'security-surveillance',
        'computing-office/storage-devices',
        'security-surveillance/intercom-systems',
        'computing-office/laptops',
        'computing-office/desktops',
        'computing-office/monitors',
        'solar-power',
    ],

    /*
    | Each homepage product row is a complete 4x2 grid.
    */
    'row_limit' => 8,

    /*
    | First homepage product row: mix from these category paths (2 each),
    | not whatever was imported most recently (HDMI sockets, helmet cameras).
    */
    'popular_category_paths' => [
        'networking-connectivity',
        'computing-office/laptops',
        'security-surveillance',
        'computing-office/storage-devices',
    ],

    /*
    | Shop-by-category tiles: South Africa IT demand first.
    */
    'category_priority' => [
        'networking-connectivity',
        'computing-office',
        'security-surveillance',
        'solar-power',
        'business-retail',
        'industrial-iot',
        'digital-signage',
    ],

    /*
    | Keep accessories and niche imports off homepage product rows.
    */
    'exclude_name_terms' => [
        'hdmi',
        'helmet',
        'body worn',
        'body-worn',
        'bwc-',
        'playstation',
        'ps5',
        'dash cam',
        'dashcam',
    ],

    /*
    | Brand logos shown above homepage product rows (slug order preserved).
    | Networking uses product cards only — no logo strip.
    */
    'section_brands' => [
        'laptops' => ['dell', 'hp', 'lenovo', 'microsoft'],
        'cctv' => ['hikvision', 'dahua'],
        'top_sellers' => ['ubiquiti', 'mikrotik', 'hikvision', 'dahua', 'tp-link', 'dell'],
    ],

    /*
    | When loading category product rows, prefer these brand slugs first.
    */
    'section_product_brands' => [
        'laptops' => ['dell', 'hp', 'lenovo', 'microsoft', 'asus'],
        'cctv' => ['hikvision', 'dahua'],
    ],

    /*
    | Homepage Networking Solutions row — switches/APs/routers from major brands only.
    */
    'networking_showcase' => [
        'brand_slugs' => [
            'ubiquiti', 'mikrotik', 'tp-link', 'cambium-networks', 'huawei', 'cisco',
        ],
        'category_slugs' => [
            'networking-connectivity/switches',
            'networking-connectivity/access-points',
            'networking-connectivity/routers',
            'networking-connectivity/fibre-equipment',
        ],
        'exclude_brands' => [
            'Locally Sourced', 'Linkbasic', 'Scoop', 'Rackstuds', 'Cudy', 'Reyee',
        ],
        'exclude_name_terms' => [
            'trunking', 'bracket', 'mount', 'rack stud', 'cable tray', 'patch panel',
            'stand off', 'tripod', 'pigtail', 'patch cord', 'cable tie',
        ],
    ],
];
