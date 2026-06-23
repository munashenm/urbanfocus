<?php

/**
 * Zero-Laravel ping — confirms PHP runs in public_html.
 * Copy to public_html/uf-ping.php, visit once, then DELETE.
 */

header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Script: ".__FILE__."\n";
echo 'urbanfocus: '.(is_dir(dirname(__DIR__).'/urbanfocus') ? 'found' : 'NOT FOUND')."\n";
