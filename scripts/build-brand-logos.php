<?php

declare(strict_types=1);

/**
 * Normalize brand SVGs to a consistent monochrome canvas for the storefront.
 *
 * Usage: php scripts/build-brand-logos.php
 */

$root = dirname(__DIR__);
$config = require $root.'/config/brand_logos.php';
$srcDir = $root.'/public/images/brands/_src';
$outDir = $root.'/public/images/brands';

$canvasW = (float) ($config['canvas']['width'] ?? 160);
$canvasH = (float) ($config['canvas']['height'] ?? 48);
$paddingX = (float) ($config['canvas']['padding_x'] ?? 16);
$paddingY = (float) ($config['canvas']['padding_y'] ?? 10);
$fill = (string) ($config['canvas']['fill'] ?? '#475569');
$maxW = $canvasW - (2 * $paddingX);
$maxH = $canvasH - (2 * $paddingY);

$built = 0;

foreach ($config['brands'] as $slug => $meta) {
    $sourcePath = $srcDir.'/'.($meta['source'] ?? '');
    $label = (string) ($meta['label'] ?? $slug);
    $outPath = $outDir.'/'.$slug.'.svg';

    if (! is_file($sourcePath)) {
        fwrite(STDERR, "Missing source for {$slug}: {$sourcePath}\n");
        exit(1);
    }

    $brandPaddingX = (float) ($meta['padding_x'] ?? $paddingX);
    $brandPaddingY = (float) ($meta['padding_y'] ?? $paddingY);
    $brandMaxW = $canvasW - (2 * $brandPaddingX);
    $brandMaxH = $canvasH - (2 * $brandPaddingY);

    $normalized = normalizeBrandSvg(
        file_get_contents($sourcePath),
        $label,
        $canvasW,
        $canvasH,
        $brandMaxW,
        $brandMaxH,
        $brandPaddingX,
        $brandPaddingY,
        $fill,
        $meta['view_box'] ?? null
    );

    file_put_contents($outPath, $normalized);
    echo "Built {$slug}.svg\n";
    $built++;
}

echo "Done. {$built} brand logos written to public/images/brands/\n";

function normalizeBrandSvg(
    string $svg,
    string $label,
    float $canvasW,
    float $canvasH,
    float $maxW,
    float $maxH,
    float $paddingX,
    float $paddingY,
    string $fill,
    ?array $viewBoxOverride = null
): string {
    $svg = trim($svg);
    if ($svg === '') {
        throw new RuntimeException("Empty SVG for {$label}");
    }

    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = false;

    if (@$doc->loadXML($svg) === false) {
        throw new RuntimeException("Invalid SVG for {$label}");
    }

    $root = $doc->documentElement;
    if ($root === null || strtolower($root->tagName) !== 'svg') {
        throw new RuntimeException("Root element is not svg for {$label}");
    }

    [$srcX, $srcY, $srcW, $srcH] = is_array($viewBoxOverride) && count($viewBoxOverride) === 4
        ? array_map('floatval', array_values($viewBoxOverride))
        : resolveViewBox($root);

    if ($srcW <= 0 || $srcH <= 0) {
        throw new RuntimeException("Invalid viewBox for {$label}");
    }

    $scale = min($maxW / $srcW, $maxH / $srcH);
    $drawW = $srcW * $scale;
    $drawH = $srcH * $scale;
    $tx = ($canvasW - $drawW) / 2;
    $ty = ($canvasH - $drawH) / 2;

    $out = new DOMDocument('1.0', 'UTF-8');
    $outSvg = $out->createElement('svg');
    $outSvg->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    $outSvg->setAttribute('viewBox', "0 0 {$canvasW} {$canvasH}");
    $outSvg->setAttribute('role', 'img');
    $outSvg->setAttribute('aria-label', $label);
    $out->appendChild($outSvg);

    $group = $out->createElement('g');
    $group->setAttribute('fill', $fill);
    $group->setAttribute('transform', sprintf('translate(%.4F,%.4F) scale(%.6F) translate(%.4F,%.4F)', $tx, $ty, $scale, -$srcX, -$srcY));
    $outSvg->appendChild($group);

    importGraphicNodes($root, $group, $out, $fill);

    $result = $out->saveXML($outSvg) ?: '';
    $result = preg_replace('/\s+/u', ' ', $result) ?? $result;

    return trim($result)."\n";
}

/** @return array{0: float, 1: float, 2: float, 3: float} */
function resolveViewBox(DOMElement $svg): array
{
    $viewBox = trim($svg->getAttribute('viewBox'));
    if ($viewBox !== '' && preg_match('/^[\d.\-eE+]+\s+[\d.\-eE+]+\s+[\d.\-eE+]+\s+[\d.\-eE]+$/', $viewBox)) {
        $parts = preg_split('/\s+/', $viewBox) ?: [];

        return [(float) $parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3]];
    }

    $width = parseSvgLength($svg->getAttribute('width'));
    $height = parseSvgLength($svg->getAttribute('height'));

    return [0.0, 0.0, max($width, 24.0), max($height, 24.0)];
}

function parseSvgLength(string $value): float
{
    $value = trim($value);
    if ($value === '') {
        return 0.0;
    }

    return (float) preg_replace('/[^0-9.\-eE+]/', '', $value);
}

function importGraphicNodes(DOMNode $node, DOMElement $targetGroup, DOMDocument $targetDoc, string $fill): void
{
    foreach ($node->childNodes as $child) {
        if (! $child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->tagName);

        if (in_array($tag, ['title', 'desc', 'metadata', 'defs', 'style', 'script', 'sodipodi:namedview'], true)) {
            continue;
        }

        if ($tag === 'svg') {
            importGraphicNodes($child, $targetGroup, $targetDoc, $fill);

            continue;
        }

        if (isHiddenGraphic($child)) {
            continue;
        }

        if ($tag === 'g') {
            if (isBackgroundGraphic($child)) {
                continue;
            }

            $cloned = cloneGraphicElement($child, $targetDoc, $fill, false);
            $targetGroup->appendChild($cloned);
            importGraphicNodes($child, $cloned, $targetDoc, $fill);

            continue;
        }

        if (in_array($tag, ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line', 'text'], true)) {
            if (isBackgroundGraphic($child)) {
                continue;
            }

            $targetGroup->appendChild(cloneGraphicElement($child, $targetDoc, $fill));
        }
    }
}

function isHiddenGraphic(DOMElement $element): bool
{
    $display = strtolower(trim($element->getAttribute('display')));
    if ($display === 'none') {
        return true;
    }

    $style = strtolower($element->getAttribute('style'));
    if (str_contains($style, 'display:none') || str_contains($style, 'display: none')) {
        return true;
    }

    $class = ' '.$element->getAttribute('class').' ';

    return str_contains($class, ' st0 ');
}

function isBackgroundGraphic(DOMElement $element): bool
{
    $fill = strtolower(trim($element->getAttribute('fill')));
    if (in_array($fill, ['#fff', '#ffffff', 'white', 'rgb(255,255,255)', '#fffefe'], true)) {
        return true;
    }

    $d = $element->getAttribute('d');
    if ($d !== '' && preg_match('/^M0\s+0h[\d.]+\s*v[\d.]+\s*H0/i', $d)) {
        return true;
    }

    if (strtolower($element->tagName) === 'rect') {
        $width = parseSvgLength($element->getAttribute('width'));
        $height = parseSvgLength($element->getAttribute('height'));
        if ($width >= 180 && $height >= 180) {
            return true;
        }
    }

    return false;
}

function cloneGraphicElement(DOMElement $element, DOMDocument $targetDoc, string $fill, bool $deep = true): DOMElement
{
    $clone = $targetDoc->importNode($element, $deep);
    if (! $clone instanceof DOMElement) {
        throw new RuntimeException('Could not clone SVG element');
    }

    sanitizeElement($clone, $fill);

    return $clone;
}

function sanitizeElement(DOMElement $element, string $fill): void
{
    $tag = strtolower($element->tagName);

    $element->removeAttribute('class');
    $element->removeAttribute('style');
    $element->removeAttribute('id');
    $element->removeAttribute('fill-opacity');
    $element->removeAttribute('stroke');
    $element->removeAttribute('stroke-width');
    $element->removeAttribute('stroke-linecap');
    $element->removeAttribute('stroke-linejoin');

    foreach (['fill', 'stroke'] as $attr) {
        if ($element->hasAttribute($attr)) {
            $value = strtolower(trim($element->getAttribute($attr)));
            if ($value === 'none') {
                continue;
            }
            $element->setAttribute($attr, $fill);
        }
    }

    if (in_array($tag, ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'text'], true) && ! $element->hasAttribute('fill')) {
        $element->setAttribute('fill', $fill);
    }

    if ($tag === 'line') {
        $element->setAttribute('stroke', $fill);
        if (! $element->hasAttribute('stroke-width')) {
            $element->setAttribute('stroke-width', '1');
        }
    }

    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            sanitizeElement($child, $fill);
        }
    }
}
