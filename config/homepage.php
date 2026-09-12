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
            'eyebrow' => 'Specialist Technology',
            'title' => 'Hardware Security Keys & Specialist IT',
            'subtitle' => 'Nitrokey FIDO2 keys, PiKVM, private cloud and industrial IoT — supplied in South Africa with VAT invoices and nationwide courier.',
            'cta_primary' => ['label' => 'Shop Specialist Tech', 'route' => 'shop.index', 'params' => ['category' => 'specialist-technology']],
            'cta_secondary' => ['label' => 'Request a Quote', 'route' => 'b2b.quote'],
            'theme' => 'blue',
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

    'category_icons' => [
        'computing-office' => '💻',
        'networking-connectivity' => '🌐',
        'security-surveillance' => '📹',
        'solar-power' => '⚡',
        'digital-signage' => '🖵',
        'gaming-entertainment' => '🎮',
        'business-retail' => '🏪',
        'industrial-commercial' => '🏭',
        'specialist-technology' => '🔐',
        'software-licences' => '📦',
        'specialist-solutions' => '🛠',
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
        'Nitrokey',
        'PiKVM',
        'ZimaBoard',
        'Turris',
    ],

    'top_seller_categories' => [
        'networking-connectivity',
        'security-surveillance',
        'computing-office/storage-devices',
        'security-surveillance/intercom-systems',
        'computing-office/laptops',
        'computing-office/desktops',
        'computing-office/monitors',
        'specialist-technology',
        'specialist-solutions',
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
        'specialist-technology',
        'networking-connectivity',
        'computing-office/laptops',
        'security-surveillance',
    ],

    /*
    | Homepage shop-by-category tiles. Labels are product-oriented display
    | names; slug still resolves the real category URL (unchanged for SEO).
    | The full parent/child tree is shown on /shop, not here.
    */
    'shop_by_category' => [
        [
            'slug' => 'networking-connectivity',
            'label' => 'Networking Equipment',
            'blurb' => 'Switches, APs, routers and fibre',
        ],
        [
            'slug' => 'computing-office',
            'label' => 'Laptops & PCs',
            'blurb' => 'Notebooks, desktops and monitors',
        ],
        [
            'slug' => 'security-surveillance',
            'label' => 'CCTV & Security',
            'blurb' => 'Cameras, NVRs and access control',
        ],
        [
            'slug' => 'software-licences',
            'label' => 'Software Licences',
            'blurb' => 'Microsoft, security and productivity',
        ],
        [
            'slug' => 'digital-signage',
            'label' => 'Digital Displays',
            'blurb' => 'Signage, LED and interactive boards',
        ],
        [
            'slug' => 'specialist-technology',
            'label' => 'Security Keys & IoT',
            'blurb' => 'FIDO2 keys, PiKVM and edge hardware',
        ],
    ],

    /*
    | Sort order for leftover parent-category listings (Shop directory, nav).
    */
    'category_priority' => [
        'networking-connectivity',
        'computing-office',
        'security-surveillance',
        'software-licences',
        'digital-signage',
        'specialist-technology',
        'solar-power',
        'business-retail',
        'industrial-commercial',
        'specialist-solutions',
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
    | Specialist lead-time flags to keep off homepage product rows.
    */
    'exclude_availability_keys' => [
        'eu_stock',
        'special_order_eu',
    ],

    /*
    | Homepage popular-brand logo strip (slug order preserved).
    | Only brands with a logo file are shown — no text-only placeholders.
    */
    'featured_brand_slugs' => [
        'ubiquiti',
        'mikrotik',
        'hikvision',
        'dahua',
        'tp-link',
        'dell',
        'hp',
        'lenovo',
        'microsoft',
        'cambium-networks',
        'sophos',
        'huawei',
        'yealink',
        'starlink',
        'samsung',
        'logitech',
        'lg',
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
