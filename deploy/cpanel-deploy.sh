#!/bin/bash
# cPanel "Deploy HEAD Commit" for the in-place checkout at ~/urbanfocus.
# Applies pending migrations only. Does not drop data, run composer, or
# replace public_html/index.php.
set -u

log() {
  printf '%s\n' "$*"
  if [ -n "${LOG_FILE:-}" ]; then
    printf '%s\n' "$*" >> "$LOG_FILE" 2>/dev/null || true
  fi
}

run() {
  log "+ $*"
  if [ -n "${LOG_FILE:-}" ]; then
    "$@" 2>&1 | tee -a "$LOG_FILE"
    return "${PIPESTATUS[0]}"
  fi
  "$@"
}

php_ok() {
  local bin="$1"
  [ -n "$bin" ] && [ -x "$bin" ] && "$bin" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' >/dev/null 2>&1
}

resolve_repo() {
  local source dir
  source="${BASH_SOURCE[0]}"
  dir="$(CDPATH= cd -- "$(dirname -- "$source")" && pwd)" || return 1
  if [ -f "$dir/../artisan" ]; then
    (CDPATH= cd -- "$dir/.." && pwd)
    return $?
  fi
  if [ -n "${HOME:-}" ] && [ -f "${HOME}/urbanfocus/artisan" ]; then
    printf '%s\n' "${HOME}/urbanfocus"
    return 0
  fi
  if [ -f /home/urbanzdc/urbanfocus/artisan ]; then
    printf '%s\n' /home/urbanzdc/urbanfocus
    return 0
  fi
  if [ -f "${PWD}/artisan" ]; then
    printf '%s\n' "${PWD}"
    return 0
  fi
  return 1
}

resolve_php() {
  local ht handler ver candidate seen=" "
  local -a candidates=()

  for ht in \
    "${HOME:-}/public_html/.htaccess" \
    /home/urbanzdc/public_html/.htaccess \
    "${REPO}/../public_html/.htaccess"
  do
    [ -f "$ht" ] || continue
    handler="$(grep -oE 'ea-php[0-9]+' "$ht" 2>/dev/null | head -n 1 || true)"
    if [ -n "$handler" ]; then
      candidates+=("/opt/cpanel/${handler}/root/usr/bin/php")
    fi
    handler="$(grep -oE 'alt-php[0-9]+' "$ht" 2>/dev/null | head -n 1 || true)"
    if [ -n "$handler" ]; then
      ver="${handler#alt-php}"
      candidates+=("/opt/alt/php${ver}/usr/bin/php")
    fi
  done

  candidates+=(
    /usr/local/bin/php
    /usr/bin/php
    /opt/cpanel/ea-php83/root/usr/bin/php
    /opt/cpanel/ea-php84/root/usr/bin/php
    /opt/cpanel/ea-php82/root/usr/bin/php
    /usr/local/bin/ea-php83
    /usr/local/bin/ea-php84
    /usr/local/bin/ea-php82
    /opt/alt/php83/usr/bin/php
    /opt/alt/php84/usr/bin/php
    /opt/alt/php82/usr/bin/php
  )

  for candidate in "${candidates[@]}"; do
    case "$seen" in
      *" $candidate "*) continue ;;
    esac
    seen="$seen$candidate "
    if php_ok "$candidate"; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  return 1
}

resolve_public_html() {
  local dir
  for dir in "${HOME:-}/public_html" /home/urbanzdc/public_html "${REPO}/../public_html"; do
    if [ -d "$dir" ]; then
      (CDPATH= cd -- "$dir" && pwd)
      return $?
    fi
  done
  return 1
}

REPO="$(resolve_repo)" || {
  printf '%s\n' "ERROR: Laravel root not found. Expected ~/urbanfocus next to public_html."
  exit 1
}
cd "$REPO" || exit 1

LOG_FILE=""
if mkdir -p "$REPO/storage/logs" 2>/dev/null; then
  LOG_FILE="$REPO/storage/logs/cpanel-deploy.log"
fi

log "----- $(date -u '+%Y-%m-%dT%H:%M:%SZ') deploy start -----"
log "Repo: $REPO"

PHP_BIN="$(resolve_php)" || {
  log "ERROR: no PHP 8.2+ binary found. Checked the public_html PHP handler, /usr/local/bin/php, /usr/bin/php, ea-php 8.2-8.4, and alt-php 8.2-8.4."
  exit 1
}
log "PHP: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# A stale config cache would hide .env from migrate. Remove it even if
# optimize:clear cannot boot.
rm -f "$REPO/bootstrap/cache/config.php" "$REPO/bootstrap/cache/routes-v7.php"

log "Clearing cached config, routes, and views."
if ! run "$PHP_BIN" artisan optimize:clear --no-interaction; then
  log "WARNING: optimize:clear failed. Continuing to migrate."
fi

log "Applying pending migrations only. This does not drop or refresh data."
cd "$REPO" || exit 1
if ! run "$PHP_BIN" artisan migrate --force --no-interaction; then
  log "ERROR: migrate failed. Chat tables may still be missing. Read the lines above, then click Deploy HEAD Commit again after the cause is fixed."
  exit 1
fi
log "Migrations finished."

log "Applying Black Friday specials. Pending prices stay unchanged until config/black_friday.php has a sale price."
if ! run "$PHP_BIN" artisan black-friday:apply --no-interaction; then
  log "ERROR: black-friday:apply failed."
  exit 1
fi

fail=0

# PUBLIC_PATH is read from config/app.php, so caching config is safe.
# Route actions are controller methods. Group closures are not serialized.
if ! run "$PHP_BIN" artisan config:cache --no-interaction; then
  log "ERROR: config:cache failed."
  fail=1
fi

if ! run "$PHP_BIN" artisan route:cache --no-interaction; then
  log "ERROR: route:cache failed."
  fail=1
fi

if ! run "$PHP_BIN" artisan view:cache --no-interaction; then
  log "ERROR: view:cache failed."
  fail=1
fi

PUBLIC_HTML="$(resolve_public_html)" || PUBLIC_HTML=""
if [ -z "$PUBLIC_HTML" ]; then
  log "ERROR: public_html not found. Asset sync skipped. index.php was not touched."
  fail=1
else
  log "Syncing css, js, images, favicons, and .htaccess to $PUBLIC_HTML. index.php is not copied."
  cd "$REPO" || exit 1
  export UF_REPO="$REPO"
  export UF_PUBLIC_HTML="$PUBLIC_HTML"
  if ! run "$PHP_BIN" -r 'require "deploy/cpanel-asset-sync.php"; cpanel_sync_public_assets(getenv("UF_REPO"), getenv("UF_PUBLIC_HTML"));'; then
    log "ERROR: asset sync failed."
    fail=1
  fi
fi

if [ "$fail" -ne 0 ]; then
  log "Deploy finished with errors after migrations. Pending migrations were applied. Fix the errors above and Deploy HEAD Commit again."
  exit 1
fi

log "Deploy finished successfully."
exit 0
