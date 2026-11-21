#!/bin/bash
# One-off Urban Focus job: end the Black Friday sale on P220S256G25 and RT-AX57-GO now.
# Reads committed files from ops/bf1121b/. Writes the lock (via cron), log, and working
# copies under $HOME only. Does not modify .env, .cpanel.yml, or any file inside the
# git tree. sale:apply may write its usual gitignored backups under storage/app.
set -euo pipefail
export TZ=Africa/Johannesburg
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
ROOT=/home/urbanzdc/urbanfocus
JOB_DIR="$ROOT/ops/bf1121b"
END_SRC="$JOB_DIR/sale_end_bf2026_p220_ax57_now.csv"
WORK="$HOME/bf1121b_work"
END_CSV="$WORK/sale_end_bf2026_p220_ax57_now.csv"

echo "START $(date '+%Y-%m-%d %H:%M:%S %Z')"
cd "$ROOT" || { echo "FAIL: cannot cd to $ROOT"; exit 1; }
mkdir -p "$WORK"

# --- md5 gate on the committed source (also checked by the cron line) ---
END_MD5='473454f75f543ca6e0bf2ec004b04fe4'
got_end=$(md5sum "$END_SRC" | awk '{print $1}')
if [ "$got_end" != "$END_MD5" ]; then
  echo "FAIL: md5 mismatch"
  echo "end    got=$got_end expected=$END_MD5"
  exit 1
fi

cp -f "$END_SRC" "$END_CSV"

NOW=$($PHP -r 'echo date("Y-m-d H:i:s");')
echo "NOW_SAST=$NOW"
echo "WORK=$WORK"

echo "== LIVE WINDOW CHECK (no costs) =="
$PHP artisan tinker --execute='
$skus = ["P220S256G25","RT-AX57-GO"];
$now = now()->timezone("Africa/Johannesburg");
foreach ($skus as $sku) {
  $p = \App\Models\Product::query()->whereRaw("LOWER(sku) = ?", [mb_strtolower($sku)])->first();
  if (!$p) { echo $sku." NOT_FOUND\n"; continue; }
  $start = $p->sale_starts_at; $end = $p->sale_ends_at;
  $active = $p->sale_price !== null
    && ($start === null || $start->lte($now))
    && ($end === null || $end->gte($now));
  echo sprintf(
    "%s price=%s sale=%s start=%s end=%s label=%s active=%s\n",
    $sku,
    $p->price,
    $p->sale_price,
    $start ? $start->timezone("Africa/Johannesburg")->format("Y-m-d H:i:s") : "-",
    $end ? $end->timezone("Africa/Johannesburg")->format("Y-m-d H:i:s") : "-",
    $p->promo_label ?: "-",
    $active ? "yes" : "no"
  );
}
'

# Build the working end CSV from live sale_price / starts / label, and stamp sale_ends_at = now.
# Do not write back to the repo. Keep the current sale amount; only end the window.
$PHP -r '
require "/home/urbanzdc/urbanfocus/vendor/autoload.php";
$app = require "/home/urbanzdc/urbanfocus/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$now = $argv[1];
$src = $argv[2];
$dest = $argv[3];

$h = fopen($src, "r");
if ($h === false) { fwrite(STDERR, "cannot read $src\n"); exit(1); }
$header = fgetcsv($h);
$keys = array_map(fn ($c) => strtolower(trim((string) $c)), $header ?: []);
$rows = [];
while (($line = fgetcsv($h)) !== false) {
  if ($line === [null] || $line === []) { continue; }
  $row = [];
  foreach ($keys as $i => $key) {
    $row[$key] = trim((string) ($line[$i] ?? ""));
  }
  $rows[] = $row;
}
fclose($h);

$out = fopen($dest, "w");
fputcsv($out, ["SKU","sale_price","sale_starts_at","sale_ends_at","promo_label"]);
foreach ($rows as $row) {
  $sku = $row["sku"] ?? "";
  $p = App\Models\Product::query()->whereRaw("LOWER(sku) = ?", [mb_strtolower($sku)])->first();
  if (!$p) { fwrite(STDERR, "FAIL: $sku not found\n"); exit(1); }
  if ($p->sale_price === null || (float) $p->sale_price <= 0) {
    fwrite(STDERR, "FAIL: $sku has no sale_price to end\n");
    exit(1);
  }
  $start = $p->sale_starts_at
    ? $p->sale_starts_at->timezone("Africa/Johannesburg")->format("Y-m-d H:i:s")
    : "";
  $label = $p->promo_label ?: ($row["promo_label"] ?? "Black Friday");
  fputcsv($out, [$p->sku, (string) (0 + $p->sale_price), $start, $now, $label]);
  echo "working row $sku sale=".(0 + $p->sale_price)." start=$start ends_at=$now\n";
}
fclose($out);
echo "stamped end csv sale_ends_at=$now rows=".count($rows)."\n";
' "$NOW" "$END_SRC" "$END_CSV"

echo "== DRY RUN end P220S256G25 and RT-AX57-GO =="
$PHP artisan sale:apply "$END_CSV" --dry-run

echo "== LIVE end P220S256G25 and RT-AX57-GO =="
$PHP artisan sale:apply "$END_CSV"

echo "== cache + feed check =="
$PHP artisan optimize:clear
$PHP -d memory_limit=512M artisan merchant:feed-check --rebuild

echo "== POST-APPLY WINDOW CHECK (expect active=no) =="
$PHP artisan tinker --execute='
$skus = ["P220S256G25","RT-AX57-GO"];
$now = now()->timezone("Africa/Johannesburg");
$failed = false;
foreach ($skus as $sku) {
  $p = \App\Models\Product::query()->whereRaw("LOWER(sku) = ?", [mb_strtolower($sku)])->first();
  if (!$p) { echo $sku." NOT_FOUND\n"; $failed = true; continue; }
  $start = $p->sale_starts_at; $end = $p->sale_ends_at;
  $active = $p->sale_price !== null
    && ($start === null || $start->lte($now))
    && ($end === null || $end->gte($now));
  echo sprintf(
    "%s price=%s sale=%s start=%s end=%s label=%s active=%s\n",
    $sku,
    $p->price,
    $p->sale_price,
    $start ? $start->timezone("Africa/Johannesburg")->format("Y-m-d H:i:s") : "-",
    $end ? $end->timezone("Africa/Johannesburg")->format("Y-m-d H:i:s") : "-",
    $p->promo_label ?: "-",
    $active ? "yes" : "no"
  );
  if ($active) { $failed = true; }
}
if ($failed) { fwrite(STDERR, "FAIL: expected both SKUs active=no\n"); exit(1); }
echo "POST_APPLY_OK both active=no\n";
'

got_end2=$(md5sum "$END_SRC" | awk '{print $1}')
if [ "$got_end2" != "$END_MD5" ]; then
  echo "FAIL: committed job files in the repo were modified"
  echo "end    got=$got_end2 expected=$END_MD5"
  exit 1
fi

echo "== git status (must stay clean of tracked changes) =="
git status --short | head -30
echo "== HEAD =="
git log --oneline -1

echo "END $(date '+%Y-%m-%d %H:%M:%S %Z')"
