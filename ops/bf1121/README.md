# Black Friday 21 Nov one-off job

Ends the six group 2 specials immediately and applies the four approved reprices only if each still makes at least 10% profit against live supplier cost in the database. Group 3 is left unchanged.

This is a one-off. Do not add these files to `.cpanel.yml` or the deploy hook.

## After this branch is merged and deployed

1. In cPanel Git Version Control: Update from Remote, then Deploy HEAD Commit.
2. Add this one-off every-minute cron (leave the 9 regular crons in place):

```
* * * * * [ -e ~/bf1121.lock ] || (touch ~/bf1121.lock; md5sum /home/urbanzdc/urbanfocus/ops/bf1121/bf1121_apply.sh | grep -q '^c7991dd9e5f0fa9b260eab087c44da37 ' && bash /home/urbanzdc/urbanfocus/ops/bf1121/bf1121_apply.sh > ~/bf1121_apply.log 2>&1)
```

3. Watch `~/bf1121_apply.log` for a final `END` line.
4. Delete this one-off cron only. Keep the 9 regular crons. If you need to retry a failed run, remove `~/bf1121.lock` first, then add the cron again.
5. Confirm `git status` in `~/urbanfocus` is clean of tracked changes.
6. Verify `/black-friday`, the four product pages, group 2 back at regular prices, and `merchant:feed-check`.

The script reads only the files in this folder. Logs, the lock, stamped working CSVs, and skip notes are written under the home directory, outside the git tree. `sale:apply` still writes its usual backups under `storage/app` (gitignored).
