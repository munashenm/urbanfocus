# Auto-sync GitHub `main` to the live cPanel site

The live app is the cPanel Git checkout at `/home/urbanzdc/urbanfocus` on
`zacp122.webway.host` (account `urbanzdc`). Today a person has to click
**Update from Remote** and then **Deploy HEAD Commit**. This folder adds a
server-side cron that does the same thing after a merge to `main`.

Nothing here is live until this pull request is merged, the checkout is
updated once by hand, and the 10th regular cron is added in cPanel.
This document does not add that cron for you.

## Recommendation

**Use option A: a cron every 5 minutes that runs `ops/autodeploy/autodeploy.sh`.**

Do not use GitHub Actions against the cPanel UAPI on port 2083, and do not
add a public webhook, unless option A is later shown not to work.

### Why not GitHub Actions calling UAPI (option B)

The official method names are correct:

- `VersionControl::update` with `repository_root` and `branch` — this is
  "Update from Remote". The branch argument is required or cPanel will not
  pull. Docs:
  https://api.docs.cpanel.net/specifications/cpanel.openapi/repository-management/versioncontrol-update
- `VersionControlDeployment::create` with `repository_root` — this is
  "Deploy HEAD Commit". It runs `.cpanel.yml`, requires a clean working
  tree, and fast-forwards only. Docs:
  https://api.docs.cpanel.net/specifications/cpanel.openapi/deployment-settings/versioncontroldeployment-create

A GitHub Action would call those over HTTPS as:

`https://zacp122.webway.host:2083/execute/VersionControl/update`
`https://zacp122.webway.host:2083/execute/VersionControlDeployment/create`

with `Authorization: cpanel urbanzdc:<API token>` and the token stored as
a GitHub secret (you would create the token in cPanel → Security → Manage
API Tokens and add the secret yourself).

From this environment, TCP port 2083 on `zacp122.webway.host` accepts a
connection, but the TLS handshake fails with `unexpected eof while reading`.
The hostname resolved here to `198.18.0.1`, so this is not a clean test of
what GitHub-hosted runners would see. It is enough to say remote UAPI is
**not clearly viable**. A token also grants broad cPanel access, and a
failed Action would leave the site stale with no server-side retry until
the next push.

### Why not a webhook endpoint (option C)

Stock cPanel Git Version Control does not expose a built-in GitHub webhook
that replaces the two buttons. A custom PHP endpoint would have to call
the same UAPI methods or run `git pull`. That adds a public URL and a
shared secret, may be blocked if `exec` is disabled, and still depends on
UAPI or a writable working tree. It is more attack surface for no gain
over a cron that already runs on the account.

### Why the cron script is the better fit

- It runs on the same account as the site, so it does not need a cPanel
  API token or inbound access to port 2083.
- Official cPanel docs already describe a deployment cron, using
  `uapi VersionControlDeployment create`, at
  https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-set-up-deployment-cron-jobs/
- The script only deploys when `origin/main` is ahead of `HEAD`. A bare
  official cron that only called `VersionControlDeployment::create` would
  rebuild caches (and run the other `.cpanel.yml` tasks) every 5 minutes
  even when nothing changed.
- If the working tree is dirty, it aborts and logs, the same way cPanel
  disables Deploy when the tree is not clean.
- After a successful fast-forward it prefers on-server
  `uapi VersionControlDeployment create` (the same path as the Deploy
  button). If `uapi` is missing, it runs `deploy/cpanel-deploy.sh`, which
  is what `.cpanel.yml` already execs.

## What the script does

1. Refuses to run unless it is the live checkout
   `/home/urbanzdc/urbanfocus` for user `urbanzdc`.
2. Takes a lock at `~/autodeploy.lock` so two runs cannot overlap.
3. Appends a log to `~/autodeploy.log`. It never reads, writes, or prints
   `.env`. Remote URLs in git errors are redacted.
4. Aborts if `.env` is missing, if the branch is not `main`, or if
   `git status --porcelain` is not empty.
5. `git fetch origin main`. If `origin/main` equals `HEAD`, it exits
   without deploying.
6. If `HEAD` is not an ancestor of `origin/main` (diverged history), it
   aborts. It does **not** `reset --hard`.
7. If the tree is clean and a fast-forward is possible:
   `git merge --ff-only origin/main`.
8. Runs the same deploy as "Deploy HEAD Commit":
   `uapi VersionControlDeployment create repository_root=/home/urbanzdc/urbanfocus`
   when `uapi` exists, otherwise `deploy/cpanel-deploy.sh`.

Current `.cpanel.yml` / `deploy/cpanel-deploy.sh` steps (this can change
on `main`):

- `artisan optimize:clear`
- `artisan migrate --force` (pending migrations only)
- `artisan black-friday:apply`
- `artisan config:cache`, `route:cache`, `view:cache`
- sync selected public assets into `public_html` (not `index.php`)

It does **not** run `composer`. New PHP dependencies will not appear until
`vendor/` is updated on the server separately.

## Cron line (10th regular cron)

Add this only after the pull request is merged and one manual
**Update from Remote** + **Deploy HEAD Commit** has placed the script on
the server. Do not add it before that, or cron will fail looking for a
missing file.

In cPanel → Advanced → Cron Jobs:

```
*/5 * * * * /bin/bash /home/urbanzdc/urbanfocus/ops/autodeploy/autodeploy.sh >/dev/null 2>&1
```

The script already appends to `/home/urbanzdc/autodeploy.log`, so the
cron does not need its own mail or redirect for output. Keep the existing
nine regular crons unchanged. This would be the tenth.

Optional hardening: copy the script once to
`/home/urbanzdc/bin/uf-autodeploy.sh` and point cron at that copy so a
pull cannot rewrite the running file mid-run. Then, after a later update
to the repo script, replace the copy by hand. The repo path above is
enough to start.

To confirm it is idle when `main` is already deployed:

```
/bin/bash /home/urbanzdc/urbanfocus/ops/autodeploy/autodeploy.sh
tail -n 50 /home/urbanzdc/autodeploy.log
```

## What you need to do

1. Review this pull request. Do not merge it until you are happy with
   automatic deploys of `main` (see risks below).
2. After merge, in cPanel Git Version Control, click **Update from Remote**
   and **Deploy HEAD Commit** once, so this script exists on the server.
3. Confirm the live working tree is clean (cPanel will also refuse Deploy
   if it is dirty). Leftover untracked files, including old `.bak` or lock
   files that are not gitignored, will make every autodeploy run abort.
4. Add the cron line above. Do not store API tokens or change `.env` for
   this setup.
5. Merge a small, already-reviewed change (or wait for the next normal
   merge) and check `~/autodeploy.log` plus the site within 5 minutes.
6. If a run aborts because the tree is dirty, fix or move the listed files
   and leave the cron in place; it will try again on the next tick.

You do not need a cPanel API token or a GitHub secret for option A.

## Risks

- **Broken `main` goes live within 5 minutes.** There is no staging or
  health check. Only merge reviewed pull requests.
- **Pending migrations run automatically** on each new deploy, via the
  existing hook. A bad migration can break the site. The hook does not
  drop or refresh data, but `--force` is still used.
- **`black-friday:apply` runs on every deploy** that reaches the hook.
  Prices in `config/black_friday.php` can be applied as soon as that
  commit lands on `main`.
- **Composer is not run.** A merge that needs new PHP packages will deploy
  code against the existing `vendor/` and may fatal until vendor is
  updated on the server.
- **A dirty working tree blocks all future auto-syncs** until it is
  cleaned. The log will show `git status --porcelain`. This is
  intentional, and is the same class of failure that previously blocked
  "Deploy HEAD Commit" (for example an untracked stamp file, now
  gitignored).
- **Diverged history is not overwritten.** If someone commits on the
  server, the script logs an error and waits for a manual fix.
- **If `uapi` queues deploy and the hook later fails,** Git already
  moved `HEAD`. The site may be on the new commit with old caches. Check
  `~/autodeploy.log`, `~/urbanfocus/storage/logs/cpanel-deploy.log`, and
  `~/.cpanel/logs/vc_*_git_deploy.log`.
- **Code and caches update; the database and `.env` do not come from
  Git.** Product prices and other database edits still need their own
  process.

To pause auto-deploy, delete or disable the 10th cron. The repo files can
stay.
