#!/bin/bash
# Auto-sync GitHub origin/main into the live cPanel checkout, then run the
# same deploy steps as .cpanel.yml ("Deploy HEAD Commit").
#
# Intended to run from a cPanel cron on urbanzdc@zacp122.webway.host.
# Safe defaults:
#   - refuses to run except on the live checkout
#   - never reads, prints, or writes .env
#   - never runs composer
#   - aborts if the working tree is dirty
#   - only fast-forwards to origin/main (no merge commits; no hard reset
#     if the histories have diverged)
#   - single-flight via a lock file in $HOME
#
# This script does not add the cron job by itself.

set -u

REPO="${UF_REPO:-/home/urbanzdc/urbanfocus}"
BRANCH="${UF_BRANCH:-main}"
REMOTE="${UF_REMOTE:-origin}"
EXPECTED_USER="${UF_EXPECTED_USER:-urbanzdc}"
LIVE_HOME="/home/urbanzdc"

# Do not hang cron waiting for a password. Do not override an existing
# credential helper; cPanel already has whatever "Update from Remote" uses.
export GIT_TERMINAL_PROMPT=0

timestamp() {
  date '+%Y-%m-%d %H:%M:%S %Z'
}

# Avoid printing tokens if a remote URL ever includes one.
redact() {
  sed -E \
    -e 's#https://[^@/]+@#https://<redacted>@#g' \
    -e 's#git://[^@/]+@#git://<redacted>@#g' \
    -e 's#(Authorization:[[:space:]]*cpanel[[:space:]]+[^:]+:)[^[:space:]]+#\1<redacted>#gI'
}

log() {
  printf '%s %s\n' "$(timestamp)" "$*" | redact | tee -a "$LOG"
}

die() {
  log "ERROR: $*"
  exit 1
}

# Refuse to run on a laptop, the shared box, or any clone that is not live.
on_live_checkout() {
  local user_now repo_abs
  user_now="${USER:-${LOGNAME:-}}"
  repo_abs="$(CDPATH= cd -- "$REPO" 2>/dev/null && pwd)" || return 1
  [ "$repo_abs" = "/home/urbanzdc/urbanfocus" ] || return 1
  [ -f "$repo_abs/artisan" ] && [ -d "$repo_abs/.git" ] || return 1
  if [ -n "$user_now" ] && [ "$user_now" != "$EXPECTED_USER" ]; then
    return 1
  fi
  return 0
}

if ! on_live_checkout; then
  printf '%s ERROR: refusing to run outside the live checkout /home/urbanzdc/urbanfocus (user %s). This is a no-op on other machines.\n' \
    "$(timestamp)" "${USER:-unknown}" >&2
  exit 1
fi

# Only create lock and log once we know we are on the live account.
HOME_DIR="${HOME:-$LIVE_HOME}"
LOG="${UF_AUTODEPLOY_LOG:-${HOME_DIR}/autodeploy.log}"
LOCK="${UF_AUTODEPLOY_LOCK:-${HOME_DIR}/autodeploy.lock}"

# Cron often has a short PATH. Prefer cPanel's git if present.
PATH="/usr/local/cpanel/3rdparty/bin:/usr/local/cpanel/bin:/usr/local/bin:/usr/bin:/bin:${PATH:-}"
export PATH

resolve_git() {
  local candidate
  for candidate in /usr/local/cpanel/3rdparty/bin/git /usr/bin/git /usr/local/bin/git; do
    if [ -x "$candidate" ]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  if command -v git >/dev/null 2>&1; then
    command -v git
    return 0
  fi
  return 1
}

resolve_uapi() {
  local candidate
  for candidate in /usr/bin/uapi /usr/local/cpanel/bin/uapi; do
    if [ -x "$candidate" ]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done
  return 1
}

tree_dirty() {
  local out
  out="$("$GIT" -C "$REPO" status --porcelain --untracked-files=normal 2>&1)" || return 0
  [ -n "$out" ]
}

touch "$LOG" 2>/dev/null || {
  printf '%s ERROR: cannot write log %s\n' "$(timestamp)" "$LOG" >&2
  exit 1
}

# Single-flight: if a previous run is still pulling or deploying, exit quietly.
exec 9>"$LOCK" || die "cannot open lock $LOCK"
if command -v flock >/dev/null 2>&1; then
  if ! flock -n 9; then
    log "SKIP: another autodeploy run holds $LOCK"
    exit 0
  fi
else
  # Fallback lock if flock is missing. mkdir is atomic.
  if ! mkdir "${LOCK}.dir" 2>/dev/null; then
    log "SKIP: another autodeploy run holds ${LOCK}.dir"
    exit 0
  fi
  trap 'rmdir "${LOCK}.dir" 2>/dev/null || true' EXIT
fi

GIT="$(resolve_git)" || die "git not found"
cd "$REPO" || die "cannot cd to $REPO"

# Never touch .env. Fail closed if it is missing, so a deploy cannot cache
# an empty config by accident. The file is not read or printed.
if [ ! -f "$REPO/.env" ]; then
  die ".env is missing in $REPO; aborting before any git or artisan step"
fi

current_branch="$("$GIT" rev-parse --abbrev-ref HEAD 2>/dev/null || true)"
if [ "$current_branch" != "$BRANCH" ]; then
  die "checkout is on '$current_branch', expected '$BRANCH'. Leaving the tree unchanged."
fi

if tree_dirty; then
  log "ABORT: working tree is dirty. Not fetching or pulling. git status --porcelain:"
  "$GIT" -C "$REPO" status --porcelain --untracked-files=normal 2>&1 | redact | tee -a "$LOG"
  exit 1
fi

log "fetch $REMOTE $BRANCH"
fetch_err="$(mktemp "${HOME_DIR}/autodeploy.fetch.XXXXXX")"
if ! "$GIT" fetch --prune --quiet "$REMOTE" "$BRANCH" 2>"$fetch_err"; then
  redact < "$fetch_err" | tee -a "$LOG"
  rm -f "$fetch_err"
  die "git fetch $REMOTE $BRANCH failed"
fi
rm -f "$fetch_err"

HEAD_SHA="$("$GIT" rev-parse HEAD)"
REMOTE_SHA="$("$GIT" rev-parse "$REMOTE/$BRANCH")"
log "HEAD=$HEAD_SHA ${REMOTE}/${BRANCH}=$REMOTE_SHA"

if [ "$HEAD_SHA" = "$REMOTE_SHA" ]; then
  log "OK: already up to date with ${REMOTE}/${BRANCH}. No deploy."
  exit 0
fi

if ! "$GIT" merge-base --is-ancestor HEAD "$REMOTE_SHA"; then
  die "local HEAD is not an ancestor of ${REMOTE}/${BRANCH} ($REMOTE_SHA). Refusing to reset or merge. Resolve this in cPanel Git Version Control."
fi

log "fast-forward $HEAD_SHA -> $REMOTE_SHA"
merge_err="$(mktemp "${HOME_DIR}/autodeploy.merge.XXXXXX")"
if ! "$GIT" merge --ff-only "$REMOTE_SHA" 2>"$merge_err"; then
  redact < "$merge_err" | tee -a "$LOG"
  rm -f "$merge_err"
  die "git merge --ff-only failed; tree should be unchanged if git aborted cleanly"
fi
rm -f "$merge_err"

NEW_SHA="$("$GIT" rev-parse HEAD)"
if [ "$NEW_SHA" != "$REMOTE_SHA" ]; then
  die "after ff-only, HEAD is $NEW_SHA, expected $REMOTE_SHA"
fi
log "pull complete: now at $NEW_SHA"

# Prefer cPanel's own Deploy HEAD Commit (runs .cpanel.yml) when UAPI exists
# on this account. Otherwise run the same hook .cpanel.yml would exec.
UAPI="$(resolve_uapi || true)"
if [ -n "${UAPI:-}" ]; then
  log "deploy via $UAPI VersionControlDeployment::create (same as Deploy HEAD Commit)"
  uapi_out="$(mktemp "${HOME_DIR}/autodeploy.uapi.XXXXXX")"
  if ! "$UAPI" --output=json VersionControlDeployment create repository_root="$REPO" >"$uapi_out" 2>&1; then
    redact < "$uapi_out" | tee -a "$LOG"
    rm -f "$uapi_out"
    die "uapi VersionControlDeployment create failed. Checkout is at $NEW_SHA but .cpanel.yml may not have run. Check ~/.cpanel/logs/ and $REPO/storage/logs/cpanel-deploy.log"
  fi
  redact < "$uapi_out" | tee -a "$LOG"
  rm -f "$uapi_out"
  log "uapi deploy task queued or finished. See ~/.cpanel/logs/vc_*_git_deploy.log and $REPO/storage/logs/cpanel-deploy.log"
  exit 0
fi

DEPLOY_SH="$REPO/deploy/cpanel-deploy.sh"
if [ ! -f "$DEPLOY_SH" ]; then
  die "deploy/cpanel-deploy.sh not found after pull. Checkout is at $NEW_SHA but caches were not rebuilt."
fi

log "uapi not found; running $DEPLOY_SH (same tasks as .cpanel.yml)"
if ! /bin/bash "$DEPLOY_SH"; then
  die "cpanel-deploy.sh failed. Checkout is at $NEW_SHA. Read $REPO/storage/logs/cpanel-deploy.log"
fi

log "deploy finished successfully at $NEW_SHA"
exit 0
