-- Run in phpMyAdmin after git pull + syncing public/images/brands/
-- Adds or updates brand logos for the homepage carousel

INSERT INTO brands (name, slug, logo, sort_order, is_active, created_at, updated_at) VALUES
('Ubiquiti', 'ubiquiti', 'images/brands/ubiquiti.svg', 0, 1, NOW(), NOW()),
('Cambium Networks', 'cambium-networks', 'images/brands/cambium-networks.svg', 1, 1, NOW(), NOW()),
('Dahua', 'dahua', 'images/brands/dahua.svg', 2, 1, NOW(), NOW()),
('Hikvision', 'hikvision', 'images/brands/hikvision.svg', 3, 1, NOW(), NOW()),
('Sophos', 'sophos', 'images/brands/sophos.svg', 4, 1, NOW(), NOW()),
('Starlink', 'starlink', 'images/brands/starlink.svg', 5, 1, NOW(), NOW()),
('Yealink', 'yealink', 'images/brands/yealink.svg', 6, 1, NOW(), NOW()),
('MikroTik', 'mikrotik', 'images/brands/mikrotik.svg', 7, 1, NOW(), NOW()),
('Huawei', 'huawei', 'images/brands/huawei.svg', 8, 1, NOW(), NOW()),
('Kuycon', 'kuycon', 'images/brands/kuycon.svg', 9, 1, NOW(), NOW()),
('Dell', 'dell', 'images/brands/dell.svg', 10, 1, NOW(), NOW()),
('HP', 'hp', 'images/brands/hp.svg', 11, 1, NOW(), NOW()),
('Lenovo', 'lenovo', 'images/brands/lenovo.svg', 12, 1, NOW(), NOW()),
('Microsoft', 'microsoft', 'images/brands/microsoft.svg', 13, 1, NOW(), NOW()),
('Samsung', 'samsung', 'images/brands/samsung.svg', 14, 1, NOW(), NOW()),
('TP-Link', 'tp-link', 'images/brands/tp-link.svg', 15, 1, NOW(), NOW()),
('Logitech', 'logitech', 'images/brands/logitech.svg', 16, 1, NOW(), NOW()),
('LG', 'lg', 'images/brands/lg.svg', 17, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    logo = VALUES(logo),
    sort_order = VALUES(sort_order),
    is_active = 1,
    updated_at = NOW();
