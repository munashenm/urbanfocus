<?php

declare(strict_types=1);

/**
 * Download missing brand logo sources, then normalize to public/images/brands/.
 * Usage: php scripts/fetch-brand-logo-sources.php
 */

$root = dirname(__DIR__);
$srcDir = $root.'/public/images/brands/_src';

$downloads = [
    'wm-hikvision.svg' => 'https://i.logos-download.com/7346/1662-e0fec4de277018c6f990bcb916b3aea9.svg/Hikvision_Logo.svg',
    'wm-sophos.svg' => 'https://cdn.worldvectorlogo.com/logos/sophos.svg',
    'wm-starlink.svg' => 'https://cdn.worldvectorlogo.com/logos/starlink.svg',
    'wm-cambium-networks.svg' => 'https://commons.wikimedia.org/wiki/Special:FilePath/Cambium_Networks_logo.svg',
    'wm-kuycon.svg' => 'https://www.kuycon.us/static/svg/logo-black.svg',
];

$context = stream_context_create([
    'http' => [
        'timeout' => 45,
        'header' => "User-Agent: UrbanFocus-BrandSync/1.0 (+https://www.urbanfocus.co.za)\r\n",
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

foreach ($downloads as $filename => $url) {
    $path = $srcDir.'/'.$filename;
    if (is_file($path) && filesize($path) > 200) {
        echo "Skip {$filename} (exists)\n";
        continue;
    }

    echo "Fetch {$filename}...\n";
    $body = @file_get_contents($url, false, $context);
    if ($body === false || strlen($body) < 100) {
        fwrite(STDERR, "Failed: {$url}\n");
        continue;
    }

    file_put_contents($path, $body);
    echo "Saved {$filename} (".strlen($body)." bytes)\n";
}

echo "Running build-brand-logos.php\n";
passthru(PHP_BINARY.' '.escapeshellarg($root.'/scripts/build-brand-logos.php'), $code);
exit($code);
