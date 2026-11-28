# Constrained hosting — queue and scheduler

## Drivers

| Concern | Constrained default | Notes |
| --- | --- | --- |
| Session | `file` or `database` | No Redis required |
| Cache | `file` or `database` | |
| Queue | `database` (preferred) or `sync` | `sync` runs work inline |
| Broadcast | `log` / `null` | No Reverb required |

## Minimum cron

```
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
* * * * * php /path/to/artisan ark:queue-work-bounded >> /dev/null 2>&1
```

`ark:queue-work-bounded` drains a time/job-bounded batch (`--max-time=55`, `--max-jobs=25`) so appointment reminders, mail jobs, and retries progress without Horizon.

Failed jobs remain in `failed_jobs` for inspection; do not delete historical rows as part of unrelated releases.

## Without cron

Essential interactive workflows still function on `QUEUE_CONNECTION=sync`, but deferred reminders and background mail will not run until a scheduler/worker is available. The installer reports this as an infrastructure note — it does not silently disable shop features.
