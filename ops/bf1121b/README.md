# Black Friday 21 Nov follow-up (P220S256G25 and RT-AX57-GO)

Ends the active Black Friday sale on P220S256G25 and RT-AX57-GO immediately. No other SKUs are changed.

This is a one-off. Do not add these files to `.cpanel.yml` or the deploy hook.

## After this branch is merged and deployed

1. In cPanel Git Version Control: Update from Remote, then Deploy HEAD Commit.
2. Add this one-off every-minute cron (leave the 9 regular crons in place):

```
* * * * * [ -e ~/bf1121b.lock ] || (touch ~/bf1121b.lock; md5sum /home/urbanzdc/urbanfocus/ops/bf1121b/bf1121b_apply.sh | grep -q '^a5d4f89cf8396bca92ca4d7d065b4809 ' && bash /home/urbanzdc/urbanfocus/ops/bf1121b/bf1121b_apply.sh > ~/bf1121b_apply.log 2>&1)
```

3. Watch `~/bf1121b_apply.log` for a final `END` line and `POST_APPLY_OK both active=no`.
4. Delete this one-off cron only. Keep the 9 regular crons. If you need to retry a failed run, remove `~/bf1121b.lock` first, then add the cron again.
5. Confirm `git status` in `~/urbanfocus` is clean of tracked changes.
6. Verify the two product pages are back at regular prices, `/black-friday` no longer lists them, and `merchant:feed-check`.

The script reads only the files in this folder. It copies live sale amounts and start times into a working CSV under the home directory, then stamps `sale_ends_at` to now (SAST). Logs and the lock also stay under the home directory. `sale:apply` still writes its usual backups under `storage/app` (gitignored).
