# Downloads 16:9 Knowledge Centre featured photos (Unsplash + Wikimedia Commons).
# Crop: 1200x675. Unsplash License / CC images only — no Unsplash+ / premium_photo.
$ErrorActionPreference = 'Continue'
$outDir = Join-Path $PSScriptRoot '..\public\images\blog\featured'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

# Verified Unsplash photo IDs (images.unsplash.com/photo-...).
$photos = @{
  'macbook-vs-business-laptop' = 'photo-1517430816045-df4b7de11d1d' # Dell + MacBook side by side
  'office-collaboration-laptops' = 'photo-1600880292203-757bb62b4baf'
  'office-productivity-software' = 'photo-1486312338219-ce68d2c6f44d'
  'enterprise-network-cabling' = 'photo-1558494949-ef010cbdcc31'
  'network-ethernet-cables' = 'photo-1691435828932-911a7801adfb' # MikroTik Cloud Router Switch
  'small-business-router' = 'photo-1606904825846-647eb07f5be2'
  'fibre-network-optics' = 'photo-1551703599-6b3e8379aa8c'
  'business-laptop-desk' = 'photo-1498050108023-c5249f4df085'
  'modern-business-notebook' = 'photo-1593642632823-8f785ba67e45'
  'laptop-keyboard-workspace' = 'photo-1517694712202-14dd9538aa97'
  'laptop-coding-desk' = 'photo-1531297484001-80022131f5a1'
  'students-with-laptops' = 'photo-1523240795612-9a054b0db644'
  'outdoor-security-camera' = 'photo-1528312635006-8ea0bc49ec63'
  'indoor-dome-camera' = 'photo-1650172452637-8a1c183f2524'
  'topic-cctv-wall' = 'photo-1474301105119-cdc36a303335'
  'cybersecurity-operations' = 'photo-1614064548237-096f735f344f'
  'laptop-security-lock' = 'photo-1614064642578-7faacdc6336e'
  'procurement-documents' = 'photo-1450101499163-c8848c66ca85'
  'tender-meeting-table' = 'photo-1542744173-8e7e53415bb0'
  'school-classroom-computers' = 'photo-1602114324193-e1c1b41dcde5'
  'classroom-interactive-display' = 'photo-1509062522246-3755977927d7'
  'strategy-meeting' = 'photo-1552664730-d307ca884978'
  'open-plan-office-it' = 'photo-1748256622734-92241ae7b43f'
  'it-budget-review' = 'photo-1554224155-6726b3ff858f'
  'office-multifunction-printer' = 'photo-1650094980833-7373de26feb6'
  'dual-office-monitors' = 'photo-1641990458432-9c60bcdf8e84'
  'all-in-one-desktop' = 'photo-1483470134942-13bbf4677d84'
  'docked-laptop-workstation' = 'photo-1722159475082-0a2331580de3'
  'refurbished-laptop-inspection' = 'photo-1588872657578-7efd1f1555ed'
  'solar-power-plant' = 'photo-1509391366360-2e959784a276'
  'mobile-cell-tower' = 'photo-1770958252510-16f12e32c473'
  'commercial-drone' = 'photo-1473968512647-3e447244af8f'
  'executive-boardroom' = 'photo-1454165804606-c3d57bc86b40'
  'it-sales-analytics' = 'photo-1460925895917-afdab827c52f'
  'telecom-city-offices' = 'photo-1486406146926-c627a92ad1ab'
  'fraud-cyber-alert' = 'photo-1563013544-824ae1b704d3'
  'corporate-headquarters' = 'photo-1497366811353-6870744d04b2'
  'child-using-tablet' = 'photo-1495654794940-1c0cd2aeedc1'
  'business-smartphone-call' = 'photo-1511707171634-5f897ff02aa9'
  'topic-laptop-silver' = 'photo-1525547719571-a2d4ac8945e2'
  'topic-laptop-hands' = 'photo-1496181133206-80ce9b88a853'
  'topic-network-rack' = 'photo-1680691257251-5fead813b73e' # ethernet switch / patch panel
  'topic-software-office' = 'photo-1553877522-43269d4ea984'
  'topic-cyber-screens' = 'photo-1573164713714-d95e436ab8d6'
  'topic-procurement-desk' = 'photo-1434030216411-0bbae0c224f0'
  'topic-education-lab' = 'photo-1588072432836-e10032774350'
  'topic-business-office' = 'photo-1497366754035-f200968a6e72'
  'topic-it-workspace' = 'photo-1483058712412-4245e9b90334'
  'topic-news-city-tech' = 'photo-1480714378408-67cf0d13bc1b'
  'topic-news-office-team' = 'photo-1522202176988-66273c2fd55f'
  'topic-server-cables' = 'photo-1573164713988-8665fc963095' # technician beside server racks
}

# Wikimedia Commons originals (cropped to 16:9 locally). Genuine product / facility photos.
$wikimedia = @{
  'office-wifi-access' = @{
    File = 'Unifi_AC_Lite_Access_Point.jpg'
    Anchor = 'top'
  }
  'public-wifi-infrastructure' = @{
    File = 'Ubiquiti_Unifi_AP.jpg'
    Anchor = 'center'
  }
  'ups-power-equipment' = @{
    File = 'APC_BR700G-TW_20140114.jpg'
    Anchor = 'center'
  }
  'topic-news-datacentre' = @{
    File = 'CERN_Server_03.jpg'
    Anchor = 'center'
  }
}

$ua = 'UrbanFocus/1.0 (knowledge-centre featured images; https://www.urbanfocus.co.za)'
$ok = 0
$fail = @()

function Convert-ToFeaturedJpeg {
  param(
    [string]$SourcePath,
    [string]$DestPath,
    [string]$Anchor = 'center',
    [int]$Width = 1200,
    [int]$Height = 675
  )
  Add-Type -AssemblyName System.Drawing
  $src = [System.Drawing.Image]::FromFile((Resolve-Path $SourcePath))
  try {
    $targetRatio = $Width / $Height
    $srcRatio = $src.Width / $src.Height
    if ($srcRatio -gt $targetRatio) {
      $cropH = $src.Height
      $cropW = [int]($cropH * $targetRatio)
      $cropY = 0
      $cropX = [int](($src.Width - $cropW) / 2)
    } else {
      $cropW = $src.Width
      $cropH = [int]($cropW / $targetRatio)
      $cropX = 0
      switch ($Anchor) {
        'top' { $cropY = 0 }
        'bottom' { $cropY = [Math]::Max(0, $src.Height - $cropH) }
        default { $cropY = [int](($src.Height - $cropH) / 2) }
      }
    }
    $dest = New-Object System.Drawing.Bitmap $Width, $Height
    $g = [System.Drawing.Graphics]::FromImage($dest)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.DrawImage($src, (New-Object System.Drawing.Rectangle 0, 0, $Width, $Height), $cropX, $cropY, $cropW, $cropH, [System.Drawing.GraphicsUnit]::Pixel)
    $g.Dispose()
    $codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
    $enc = New-Object System.Drawing.Imaging.EncoderParameters 1
    $enc.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter ([System.Drawing.Imaging.Encoder]::Quality, [long]82)
    $dest.Save($DestPath, $codec, $enc)
    $dest.Dispose()
  } finally {
    $src.Dispose()
  }
}

function Save-WebpFromJpeg {
  param([string]$JpegPath, [string]$WebpPath)
  $ffmpeg = Get-Command ffmpeg -ErrorAction SilentlyContinue
  if ($ffmpeg) {
    & ffmpeg -y -loglevel error -i $JpegPath -vf 'scale=1200:675' -c:v libwebp -quality 80 $WebpPath
    return (Test-Path $WebpPath)
  }
  $magick = Get-Command magick -ErrorAction SilentlyContinue
  if ($magick) {
    & magick $JpegPath -quality 80 $WebpPath
    return (Test-Path $WebpPath)
  }
  return $false
}

foreach ($name in ($photos.Keys | Sort-Object)) {
  $id = $photos[$name]
  $webp = Join-Path $outDir "$name.webp"
  $jpg = Join-Path $outDir "$name.jpg"
  foreach ($pair in @(@{fmt='webp'; path=$webp}, @{fmt='jpg'; path=$jpg})) {
    $url = "https://images.unsplash.com/${id}?auto=format&fit=crop&w=1200&h=675&q=80&fm=$($pair.fmt)"
    try {
      Invoke-WebRequest -Uri $url -OutFile $pair.path -UseBasicParsing -TimeoutSec 60 -Headers @{ 'User-Agent' = $ua }
      $len = (Get-Item $pair.path).Length
      if ($len -lt 4000) { throw "file too small ($len bytes)" }
      Write-Output "OK $name.$($pair.fmt) $len"
      $ok++
    } catch {
      Write-Output "FAIL $name.$($pair.fmt) $($_.Exception.Message)"
      $fail += "$name.$($pair.fmt)"
      if (Test-Path $pair.path) { Remove-Item $pair.path -Force }
    }
  }
}

foreach ($name in ($wikimedia.Keys | Sort-Object)) {
  $meta = $wikimedia[$name]
  $file = $meta.File
  $tmp = Join-Path $env:TEMP "uf-blog-$name-src"
  $url = "https://commons.wikimedia.org/wiki/Special:FilePath/$file"
  $jpg = Join-Path $outDir "$name.jpg"
  $webp = Join-Path $outDir "$name.webp"
  try {
    Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing -TimeoutSec 90 -Headers @{ 'User-Agent' = $ua; 'Accept' = 'image/*' }
    Convert-ToFeaturedJpeg -SourcePath $tmp -DestPath $jpg -Anchor $meta.Anchor
    $len = (Get-Item $jpg).Length
    if ($len -lt 8000) { throw "cropped jpeg too small ($len bytes)" }
    Write-Output "OK $name.jpg $len (wikimedia $file)"
    $ok++
    if (Save-WebpFromJpeg -JpegPath $jpg -WebpPath $webp) {
      Write-Output "OK $name.webp $((Get-Item $webp).Length)"
      $ok++
    } elseif (Test-Path $webp) {
      # keep existing webp if conversion unavailable
    } else {
      Copy-Item $jpg $webp -Force
      Write-Output "WARN $name.webp copied from jpeg (no webp encoder)"
    }
  } catch {
    Write-Output "FAIL $name $($_.Exception.Message)"
    $fail += $name
  } finally {
    if (Test-Path $tmp) { Remove-Item $tmp -Force }
  }
}

Write-Output "downloaded=$ok failed=$($fail.Count)"
if ($fail.Count) { $fail | ForEach-Object { Write-Output "  $_" } }
