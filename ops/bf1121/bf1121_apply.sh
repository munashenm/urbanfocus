#!/bin/bash
# One-off Urban Focus Black Friday 21 Nov job.
# Ends group 2 only; applies 4 reprices if each still clears 10% profit vs live supplier cost.
# Reads committed files from ops/bf1121/. Writes the lock (via cron), log, and working
# copies under $HOME only. Does not modify .env, .cpanel.yml, or any file inside the
# git tree. sale:apply may write its usual gitignored backups under storage/app.
set -euo pipefail
export TZ=Africa/Johannesburg
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
ROOT=/home/urbanzdc/urbanfocus
JOB_DIR="$ROOT/ops/bf1121"
END_SRC="$JOB_DIR/sale_end_bf2026_g2_now.csv"
APPLY_SRC="$JOB_DIR/sale_apply_bf2026_2130_now.csv"
CHECK_PHP="$JOB_DIR/bf1121_profitcheck.php"
WORK="$HOME/bf1121_work"
END_CSV="$WORK/sale_end_bf2026_g2_now.csv"
APPLY_STAMPED="$WORK/sale_apply_bf2026_2130_now.csv"
APPLY_RUN="$WORK/sale_apply_bf2026_2130_now_passed.csv"
SKIP_FILE="$WORK/sale_apply_bf2026_2130_skips.txt"

echo "START $(date '+%Y-%m-%d %H:%M:%S %Z')"
cd "$ROOT" || { echo "FAIL: cannot cd to $ROOT"; exit 1; }
mkdir -p "$WORK"

# --- md5 gates on the committed sources (also checked by the cron line) ---
END_MD5='78a53ebb884a4279c353dae992185653'
APPLY_MD5='2132a1a756442ee4bd18dbbf7ac0f700'
CHECK_MD5='cabf5bc31425c7b5d63d1e4cefd496a7'
got_end=$(md5sum "$END_SRC" | awk '{print $1}')
got_apply=$(md5sum "$APPLY_SRC" | awk '{print $1}')
got_check=$(md5sum "$CHECK_PHP" | awk '{print $1}')
if [ "$got_end" != "$END_MD5" ] || [ "$got_apply" != "$APPLY_MD5" ] || [ "$got_check" != "$CHECK_MD5" ]; then
  echo "FAIL: md5 mismatch"
  echo "end    got=$got_end expected=$END_MD5"
  echo "apply  got=$got_apply expected=$APPLY_MD5"
  echo "check  got=$got_check expected=$CHECK_MD5"
  exit 1
fi

cp -f "$END_SRC" "$END_CSV"
cp -f "$APPLY_SRC" "$APPLY_STAMPED"

NOW=$($PHP -r 'echo date("Y-m-d H:i:s");')
echo "NOW_SAST=$NOW"
echo "WORK=$WORK"

# Stamp working end CSV ends_at = now (starts stay 16 Nov 15:00). Do not write back to the repo.
$PHP -r '
$now = $argv[1];
$p = $argv[2];
$h = fopen($p, "r");
$out = [];
while (($row = fgetcsv($h)) !== false) { $out[] = $row; }
fclose($h);
$hdr = array_map("strtolower", $out[0]);
$ei = array_search("sale_ends_at", $hdr);
if ($ei === false) { fwrite(STDERR, "no sale_ends_at\n"); exit(1); }
for ($i = 1; $i < count($out); $i++) { $out[$i][$ei] = $now; }
$h = fopen($p, "w");
foreach ($out as $row) { fputcsv($h, $row); }
fclose($h);
echo "stamped end csv sale_ends_at=$now rows=".(count($out)-1)."\n";
' "$NOW" "$END_CSV"

# Stamp working apply CSV starts_at = now, ends_at = 30 Nov 23:59:59.
$PHP -r '
$now = $argv[1];
$p = $argv[2];
$h = fopen($p, "r");
$out = [];
while (($row = fgetcsv($h)) !== false) { $out[] = $row; }
fclose($h);
$hdr = array_map("strtolower", $out[0]);
$si = array_search("sale_starts_at", $hdr);
$ei = array_search("sale_ends_at", $hdr);
if ($si === false || $ei === false) { fwrite(STDERR, "bad apply header\n"); exit(1); }
for ($i = 1; $i < count($out); $i++) {
  $out[$i][$si] = $now;
  $out[$i][$ei] = "2026-11-30 23:59:59";
}
$h = fopen($p, "w");
foreach ($out as $row) { fputcsv($h, $row); }
fclose($h);
echo "stamped apply csv sale_starts_at=$now rows=".(count($out)-1)."\n";
' "$NOW" "$APPLY_STAMPED"

echo "== LIVE WINDOW CHECK (no costs) =="
$PHP artisan tinker --execute='
$skus = [
  "PTPL1TBPEC","K8X-H1","R-CH370-BKAGM1-E-1","WX-HS113","RD-K516RGB-PRO","PEF512GRLB32U","PA27JCV","PA348CGV",
  "DUAL-RTX5060TI-O16G","PRIME-RTX5060-O8G","RX9070XTTAICHI16GOC","TUF-RX9070XT-O16G-GAMING","XG27AQDPG","PG27AQDP",
  "ESD-A1A-BLK-G","GWCCU65W","P220S256G25","RT-AX57-GO",
];
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

echo "== PROFIT CHECK (margins only, no costs) =="
$PHP "$CHECK_PHP" "$APPLY_STAMPED" "$APPLY_RUN" "$SKIP_FILE"
echo "---- skips ----"
cat "$SKIP_FILE"
echo "---- passed csv ----"
cat "$APPLY_RUN"

echo "== DRY RUN end group 2 =="
$PHP artisan sale:apply "$END_CSV" --dry-run
echo "== DRY RUN reprices (passed only) =="
if [ -s "$APPLY_RUN" ] && [ "$(wc -l < "$APPLY_RUN")" -gt 1 ]; then
  $PHP artisan sale:apply "$APPLY_RUN" --dry-run
else
  echo "No reprice SKUs passed the 10% test; skip apply."
fi

echo "== LIVE end group 2 =="
$PHP artisan sale:apply "$END_CSV"
echo "== LIVE reprices (passed only) =="
if [ -s "$APPLY_RUN" ] && [ "$(wc -l < "$APPLY_RUN")" -gt 1 ]; then
  $PHP artisan sale:apply "$APPLY_RUN"
else
  echo "No reprice SKUs applied."
fi

echo "== cache + feed check =="
$PHP artisan optimize:clear
$PHP -d memory_limit=512M artisan merchant:feed-check --rebuild

got_end2=$(md5sum "$END_SRC" | awk '{print $1}')
got_apply2=$(md5sum "$APPLY_SRC" | awk '{print $1}')
got_check2=$(md5sum "$CHECK_PHP" | awk '{print $1}')
if [ "$got_end2" != "$END_MD5" ] || [ "$got_apply2" != "$APPLY_MD5" ] || [ "$got_check2" != "$CHECK_MD5" ]; then
  echo "FAIL: committed job files in the repo were modified"
  echo "end    got=$got_end2 expected=$END_MD5"
  echo "apply  got=$got_apply2 expected=$APPLY_MD5"
  echo "check  got=$got_check2 expected=$CHECK_MD5"
  exit 1
fi

echo "== git status (must stay clean of tracked changes) =="
git status --short | head -30
echo "== HEAD =="
git log --oneline -1

echo "END $(date '+%Y-%m-%d %H:%M:%S %Z')"
