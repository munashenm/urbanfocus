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
            'cta_primary' => ['label' => 'Shop Networking', 'route' => 'categories.show', 'params' => ['category' => 'networking']],
            'cta_secondary' => ['label' => 'Upload RFQ', 'route' => 'b2b.rfq'],
            'theme' => 'blue',
        ],
        [
            'eyebrow' => 'Business Computing',
            'title' => 'Business Laptops & Workstations',
            'subtitle' => 'Dell, HP, Lenovo and Microsoft devices for corporate, government and education deployments.',
            'cta_primary' => ['label' => 'Shop Laptops', 'route' => 'categories.show', 'params' => ['category' => 'laptops-notebooks']],
            'cta_secondary' => ['label' => 'Bulk Pricing', 'route' => 'b2b.quote'],
            'theme' => 'dark',
        ],
    ],

    'solution_blocks' => [
        [
            'title' => 'Networking Solutions',
            'subtitle' => 'Switches, access points, routers & fibre',
            'category_slug' => 'networking',
            'icon' => 'network',
        ],
        [
            'title' => 'Business Laptops',
            'subtitle' => 'Corporate notebooks & mobile workstations',
            'category_slug' => 'laptops-notebooks',
            'icon' => 'laptop',
        ],
        [
            'title' => 'CCTV & Security',
            'subtitle' => 'Cameras, NVRs & access control',
            'category_slug' => 'cctv-security',
            'icon' => 'security',
        ],
        [
            'title' => 'Software Licensing',
            'subtitle' => 'Microsoft, antivirus & subscriptions',
            'category_slug' => 'software-licensing',
            'icon' => 'software',
        ],
    ],

    'category_icons' => [
        'laptops-notebooks' => '💻',
        'desktops' => '🖥️',
        'monitors-displays' => '🖵',
        'networking' => '🌐',
        'servers' => '🗄️',
        'printers' => '🖨️',
        'software-licensing' => '📋',
        'peripherals' => '⌨️',
        'components-storage' => '💾',
        'cctv-security' => '📹',
        'telephony-voip' => '📞',
        'ups-power' => '⚡',
    ],

    /*
    | Products shown in the homepage "Top Sellers" section must match one of these
    | brand names (case-insensitive). Add aliases if your CSV uses alternate spellings.
    */
    'top_seller_brands' => [
        'Dahua',
        'TP-Link',
        'Dell',
        'Asustor',
        'ASUS',
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
];
