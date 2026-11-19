<?php

/*
 * Black Friday 2026. Prices are VAT-inclusive ZAR.
 *
 * sale_price is what checkout charges while the window is open, and only when
 * it is below the product's current selling price. A null sale_price is pending:
 * the product stays at its current price and is not shown as a special.
 * Set the Lockerstor prices here, then deploy. Admin can change a live special
 * afterwards; a later deploy will not overwrite a sale that is already set.
 *
 * introductory products have no previous Urban Focus selling price, so they are
 * listed at the special price with no "was" price.
 *
 * AS7112RDX is already branded Asustor in the catalogue. Do not change that brand.
 */
return [
    'label' => 'Black Friday',
    'ends_at' => '2026-11-30 23:59:00',
    'timezone' => 'Africa/Johannesburg',
    'offers' => [
        [
            'name' => 'MSI Nvidia GeForce RTX 3070 Ti GAMING X TRIO 8G',
            'sale_price' => 18999,
            'skus' => ['RTX3070TIGAMINGXTRIO8G'],
            'esquire_ids' => ['8426712'],
        ],
        [
            'name' => 'MSI Nvidia GeForce RTX 3070 Ti VENTUS 3X 8G OC',
            'sale_price' => 18299,
            'skus' => ['RTX3070TIVENTUS3X8GB'],
            'esquire_ids' => ['8426713'],
        ],
        [
            'name' => 'Asus Zenbook DUO UX8407 Intel Core Ultra X9 Copilot Plus Laptop',
            'sale_price' => 59999,
            'introductory' => true,
            'skus' => ['UX8407AA-OU93210G1W'],
            'esquire_ids' => ['11391712'],
            'listing' => [
                'brand' => 'Asus',
                'category_path' => 'computing-office/laptops',
                'warranty_months' => 12,
                'image' => 'database/data/black-friday/ux8407.png',
                'supplier_product_url' => 'https://www.esquire.co.za/asus-zenbook-duo-ux8407-intel-core-ultra-x9-copilot-plus-laptop/11391712',
                'short_description' => 'Asus Zenbook DUO UX8407 Copilot+ laptop with Intel Core Ultra X9 388H, 32GB RAM, 1TB SSD and a 14-inch 3K OLED touchscreen.',
                'description' => '<p>The Asus Zenbook DUO UX8407 is a dual-screen Copilot+ laptop built around the Intel Core Ultra X9 388H, with an NPU rated up to 50 TOPS. It has 32GB of LPDDR5X memory and a 1TB PCIe 4.0 SSD.</p><p>The 14-inch 3K OLED touchscreen runs at 144Hz with 500-nit brightness. Intel Arc graphics, an FHD IR webcam and Windows 11 Home are included. Retail box, 1 year warranty.</p>',
                'specifications' => [
                    'Product code' => 'UX8407AA-OU93210G1W',
                    'Operating system' => 'Windows 11 Home',
                    'Processor' => 'Intel Core Ultra X9 388H (16 cores, up to 5.1 GHz, NPU up to 50 TOPS)',
                    'Graphics' => 'Intel Arc Graphics',
                    'Display' => '14-inch 3K (2880 x 1800) OLED touchscreen, 144Hz, 500 nits',
                    'Memory' => '32GB LPDDR5X on board',
                    'Storage' => '1TB M.2 NVMe PCIe 4.0 SSD',
                    'Ports' => '2x Thunderbolt 4, 1x USB 3.2 Gen 2 Type-A, HDMI 2.1, 3.5mm audio',
                    'Wireless' => 'Wi-Fi 7 and Bluetooth 5.4',
                    'Battery' => '99Wh, 100W USB-C adapter',
                    'Weight' => '1.65 kg',
                    'Warranty' => '1 year',
                ],
            ],
        ],
        [
            'name' => 'Dahua 86 Inch 4K UHD Interactive Smart Whiteboard',
            'sale_price' => 34999,
            'skus' => ['DHI-LCH86-MC410-B-S2'],
            'esquire_ids' => ['11390940'],
        ],
        [
            'name' => 'Asus 2U Rack Mount 12 bay AS7112RDX NAS',
            'sale_price' => 66999,
            'skus' => ['AS7112RDX'],
            'esquire_ids' => ['149601'],
        ],
        [
            'name' => 'Asustor Lockerstor 10 Bay Gen 3 NAS Enclosure',
            'sale_price' => null,
            'skus' => ['AS6810T'],
            'esquire_ids' => ['149603'],
        ],
        [
            'name' => 'Asustor Lockerstor 12RD Bay Lockerstor 2U Rack',
            'sale_price' => null,
            'skus' => ['AS6512RD'],
            'esquire_ids' => ['149604'],
        ],
        [
            'name' => 'Toshiba S300 AI Surveillance 24TB Hard Drive',
            'sale_price' => 19999,
            'introductory' => true,
            'skus' => ['HDEB034ZSA51F'],
            'esquire_ids' => ['150297', '11391773'],
            'listing' => [
                'brand' => 'Toshiba',
                'category_path' => 'computing-office/storage-devices',
                'warranty_months' => 36,
                'image' => 'database/data/black-friday/s300.jpg',
                'supplier_product_url' => 'https://www.esquire.co.za/toshiba-s300-ai-surveillance-24tb-hard-drive/11391773',
                'short_description' => 'Toshiba S300 AI 24TB 3.5-inch surveillance hard drive, 7200 rpm, for up to 64 cameras and 32 AI streams.',
                'description' => '<p>The Toshiba S300 AI is a 3.5-inch SATA hard drive for always-on security recording. It supports up to 64 video cameras and up to 32 AI streams, with a 7200 rpm spindle and a 512/1024 MB buffer.</p><p>It is built for 24/7 operation in NVRs, video archive and multi-bay surveillance storage. Retail box, 3 years warranty.</p>',
                'specifications' => [
                    'Product code' => 'HDEB034ZSA51F',
                    'Capacity' => '24TB',
                    'Form factor' => '3.5-inch',
                    'Interface' => 'SATA 6Gbit/s',
                    'Rotational speed' => '7200 rpm',
                    'Buffer' => '512/1024 MB',
                    'Cameras supported' => 'Up to 64',
                    'AI streams' => 'Up to 32',
                    'Operation' => '24/7',
                    'Warranty' => '3 years',
                ],
            ],
        ],
        [
            'name' => 'NOYAFA NF-8209 Pro Network Cable Tester',
            'sale_price' => 2499,
            'skus' => ['UF-NOY-NF8209PRO'],
            'esquire_ids' => [],
        ],
        [
            'name' => 'Goldtool Multi-Service Access Test Device',
            'sale_price' => 23999,
            'skus' => ['TCT-6250'],
            'esquire_ids' => ['9429333'],
        ],
    ],
];
