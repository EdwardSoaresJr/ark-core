# Installer shipping debt — web /setup migrate timeout

**Status:** Resolved  
**Was:** Small VPS (`demo.autorepairkeeper.com`, ~1 GB) — HTTP Install could die while migrations ran; CLI still completed.

## Fix

1. `POST /setup/install` writes `PendingInstallPayload`, marks `queued`, starts `ark:install-finalize` via detached CLI `nohup`, redirects to `/setup/progress`.
2. Browser polls `/setup/progress/status` against file-backed `InstallationState` until `installed` or terminal failure.
3. During migrate, finalize pauses Horizon / Reverb / scheduler (supervisorctl) so small boxes have RAM, then resumes them.
4. Dotenv temp/backup writes under install storage (Docker `/app` is often not writable for new siblings).

Strangers never need CLI `CompleteInstallationAction`.

## Certified

Fresh Demo browser `/setup` → Install ARK → progress (migrate ~1 min) → Ready, with no SSH artisan finalize. LugsNPlugs production untouched.
