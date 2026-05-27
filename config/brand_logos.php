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
        'cambium-networks' => ['source' => 'wm-cambium-networks.svg', 'label' => 'Cambium Networks'],
        'dahua' => ['source' => 'wm-dahua.svg', 'label' => 'Dahua'],
        'hikvision' => ['source' => 'wm-hikvision.svg', 'label' => 'Hikvision'],
        'sophos' => ['source' => 'wm-sophos.svg', 'label' => 'Sophos'],
        'starlink' => ['source' => 'wm-starlink.svg', 'label' => 'Starlink'],
        'yealink' => ['source' => 'wm-yealink.svg', 'label' => 'Yealink'],
        'mikrotik' => ['source' => 'si-mikrotik.svg', 'label' => 'MikroTik'],
        'huawei' => ['source' => 'si-huawei.svg', 'label' => 'Huawei'],
        'kuycon' => ['source' => 'wm-kuycon.svg', 'label' => 'Kuycon'],
        'dell' => ['source' => 'si-dell.svg', 'label' => 'Dell'],
        'hp' => ['source' => 'si-hp.svg', 'label' => 'HP'],
        'lenovo' => ['source' => 'si-lenovo.svg', 'label' => 'Lenovo'],
        'microsoft' => ['source' => 'si-microsoft.svg', 'label' => 'Microsoft'],
        'samsung' => ['source' => 'si-samsung.svg', 'label' => 'Samsung'],
        'tp-link' => ['source' => 'si-tplink.svg', 'label' => 'TP-Link'],
        'logitech' => ['source' => 'si-logitech.svg', 'label' => 'Logitech'],
        'lg' => ['source' => 'si-lg.svg', 'label' => 'LG'],
    ],
];
