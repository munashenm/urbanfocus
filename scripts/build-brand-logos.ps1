# Normalize brand SVGs to 160x48 monochrome canvas.
# Usage: powershell -File scripts/build-brand-logos.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$srcDir = Join-Path $root 'public/images/brands/_src'
$outDir = Join-Path $root 'public/images/brands'

$canvasW = 160
$canvasH = 48
$padX = 16
$padY = 10
$maxW = $canvasW - (2 * $padX)
$maxH = $canvasH - (2 * $padY)
$fill = '#475569'

function Get-ViewBox([string]$svg) {
    if ($svg -match 'viewBox="([^"]+)"') {
        $p = $Matches[1].Trim() -split '\s+'
        return [pscustomobject]@{ X = [double]$p[0]; Y = [double]$p[1]; W = [double]$p[2]; H = [double]$p[3] }
    }
    return [pscustomobject]@{ X = 0; Y = 0; W = 24; H = 24 }
}

function Test-BackgroundNode([string]$node, $vb) {
    if ($node -notmatch '<(path|rect)\b') { return $false }
    if ($node -match 'fill="#fff"' -or $node -match "fill='#fff'" -or $node -match 'fill="white"') { return $true }
    if ($node -match 'd="M0 0h[^"]+H0V0z"') { return $true }
    return $false
}

function Get-InnerContent([string]$svg, $vb) {
    if ($svg -notmatch '(?s)<svg[^>]*>(.*)</svg>') { return '' }
    $inner = $Matches[1]
    $inner = $inner -replace '(?s)<title>.*?</title>', ''
    $inner = $inner -replace '(?s)<desc>.*?</desc>', ''
    $inner = $inner -replace '(?s)<style[^>]*>.*?</style>', ''
    $inner = $inner -replace '(?s)<defs[^>]*>.*?</defs>', ''
    $inner = $inner -replace '(?s)<metadata[^>]*>.*?</metadata>', ''
    $inner = $inner -replace '(?s)<script[^>]*>.*?</script>', ''
    $inner = $inner -replace '(?s)<g[^>]*class="st0"[^>]*>.*?</g>', ''
    $inner = $inner -replace '\sfill="[^"]*"', ''
    $inner = $inner -replace '\sstroke="[^"]*"', ''
    $inner = $inner -replace '\sstyle="[^"]*"', ''
    $inner = $inner -replace '\sclass="[^"]*"', ''
    $inner = $inner -replace '\sclip-path="[^"]*"', ''
    $inner = $inner -replace '\scurrentColor', ''

    $parts = [regex]::Matches($inner, '(?s)<(path|polygon|polyline|rect|circle|ellipse|line|text|use)[^>]*/>|<(path|polygon|polyline|rect|circle|ellipse|line|text|use)[^>]*>.*?</\2>') | ForEach-Object { $_.Value }
    $filtered = @()
    foreach ($part in $parts) {
        if ($part -match '<use\b') { continue }
        if (Test-BackgroundNode $part $vb) { continue }
        $filtered += $part
    }
    return ($filtered -join "`n").Trim()
}

function Add-FillAttributes([string]$inner) {
    $inner = $inner -replace '<(path|polygon|rect|circle|ellipse|text)\b', "<`$1 fill=`"$fill`" "
    $inner = $inner -replace '<line\b', "<line stroke=`"$fill`" "
    return $inner
}

function Build-BrandLogo([string]$slug, [string]$label, [string]$sourceFile) {
    $path = Join-Path $srcDir $sourceFile
    if (-not (Test-Path $path)) { throw "Missing source: $path" }
    $svg = Get-Content $path -Raw -Encoding UTF8
    if ($svg -notmatch '<svg') { throw "Invalid SVG source: $path" }
    $vb = Get-ViewBox $svg
    $scale = [Math]::Min($maxW / $vb.W, $maxH / $vb.H)
    $drawW = $vb.W * $scale
    $drawH = $vb.H * $scale
    $tx = ($canvasW - $drawW) / 2
    $ty = ($canvasH - $drawH) / 2
    $inner = Get-InnerContent $svg $vb
    if ([string]::IsNullOrWhiteSpace($inner)) { throw "No graphic nodes found in $sourceFile" }
    $inner = Add-FillAttributes $inner
    $transform = "translate($([Math]::Round($tx,4)) $([Math]::Round($ty,4))) scale($([Math]::Round($scale,6))) translate($([Math]::Round(-$vb.X,4)) $([Math]::Round(-$vb.Y,4)))"
    $out = @"
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 $canvasW $canvasH" role="img" aria-label="$label">
  <g fill="$fill" transform="$transform">
$inner
  </g>
</svg>
"@
    [System.IO.File]::WriteAllText((Join-Path $outDir "$slug.svg"), $out.Trim() + "`n", [System.Text.UTF8Encoding]::new($false))
    Write-Output "Built $slug.svg"
}

$brands = @{
    'ubiquiti' = @{ label = 'Ubiquiti'; source = 'wm-ubiquiti.svg' }
    'cambium-networks' = @{ label = 'Cambium Networks'; source = 'wm-cambium-networks.svg' }
    'dahua' = @{ label = 'Dahua'; source = 'wm-dahua.svg' }
    'hikvision' = @{ label = 'Hikvision'; source = 'wm-hikvision.svg' }
    'sophos' = @{ label = 'Sophos'; source = 'wm-sophos.svg' }
    'starlink' = @{ label = 'Starlink'; source = 'wm-starlink.svg' }
    'yealink' = @{ label = 'Yealink'; source = 'wm-yealink.svg' }
    'mikrotik' = @{ label = 'MikroTik'; source = 'si-mikrotik.svg' }
    'huawei' = @{ label = 'Huawei'; source = 'si-huawei.svg' }
    'kuycon' = @{ label = 'Kuycon'; source = 'wm-kuycon.svg' }
    'dell' = @{ label = 'Dell'; source = 'si-dell.svg' }
    'hp' = @{ label = 'HP'; source = 'si-hp.svg' }
    'lenovo' = @{ label = 'Lenovo'; source = 'si-lenovo.svg' }
    'microsoft' = @{ label = 'Microsoft'; source = 'si-microsoft.svg' }
    'samsung' = @{ label = 'Samsung'; source = 'si-samsung.svg' }
    'tp-link' = @{ label = 'TP-Link'; source = 'si-tplink.svg' }
    'logitech' = @{ label = 'Logitech'; source = 'si-logitech.svg' }
    'lg' = @{ label = 'LG'; source = 'si-lg.svg' }
}

foreach ($slug in ($brands.Keys | Sort-Object)) {
    $meta = $brands[$slug]
    Build-BrandLogo -slug $slug -label $meta.label -sourceFile $meta.source
}

Write-Output 'Done.'
