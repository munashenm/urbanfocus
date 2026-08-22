<?php

/**
 * Brand logo build sources → public/images/brands/{slug}.svg
 *
 * Sources live in public/images/brands/_src/
 * Run: php scripts/build-brand-logos.php
 *      powershell -File scripts/build-brand-logos.ps1
 */
return [
    'canvas' => [
        'width' => 160,
        'height' => 48,
        'padding_x' => 16,
        'padding_y' => 10,
        'fill' => '#475569',
    ],

    'brands' => [
        'ubiquiti' => ['source' => 'wm-ubiquiti.svg', 'label' => 'Ubiquiti'],
        'cambium-networks' => [
            'source' => 'wm-cambium-networks.svg',
            'label' => 'Cambium Networks',
            'view_box' => [231, 146, 258, 43],
            'padding_x' => 6,
            'padding_y' => 8,
        ],
        'dahua' => ['source' => 'wm-dahua.svg', 'label' => 'Dahua'],
        'hikvision' => ['source' => 'wm-hikvision.svg', 'label' => 'Hikvision'],
        'sophos' => [
            'source' => 'wm-sophos.svg',
            'label' => 'Sophos',
            'view_box' => [2, 76, 189, 41],
            'padding_x' => 6,
            'padding_y' => 8,
        ],
        'starlink' => [
            'source' => 'wm-starlink.svg',
            'label' => 'Starlink',
            'view_box' => [16, 125, 158, 40],
            'padding_x' => 6,
            'padding_y' => 8,
        ],
        'yealink' => ['source' => 'wm-yealink.svg', 'label' => 'Yealink'],
        'mikrotik' => ['source' => 'si-mikrotik.svg', 'label' => 'MikroTik', 'padding_y' => 6],
        'huawei' => ['source' => 'si-huawei.svg', 'label' => 'Huawei', 'padding_y' => 4],
        'kuycon' => ['source' => 'wm-kuycon.svg', 'label' => 'Kuycon'],
        'dell' => ['source' => 'si-dell.svg', 'label' => 'Dell', 'padding_y' => 4],
        'hp' => ['source' => 'si-hp.svg', 'label' => 'HP', 'padding_y' => 4],
        'lenovo' => [
            'source' => 'si-lenovo.svg',
            'label' => 'Lenovo',
            'view_box' => [0, 7.8, 24, 8.4],
            'padding_x' => 6,
            'padding_y' => 8,
        ],
        'microsoft' => ['source' => 'si-microsoft.svg', 'label' => 'Microsoft', 'padding_y' => 4],
        'samsung' => [
            'source' => 'si-samsung.svg',
            'label' => 'Samsung',
            'view_box' => [0, 10.15, 24, 3.7],
            'padding_x' => 6,
            'padding_y' => 10,
        ],
        'tp-link' => ['source' => 'si-tplink.svg', 'label' => 'TP-Link', 'padding_y' => 6],
        'logitech' => ['source' => 'si-logitech.svg', 'label' => 'Logitech', 'padding_y' => 4],
        'lg' => ['source' => 'si-lg.svg', 'label' => 'LG', 'padding_y' => 4],
    ],
];
