# ARK Core

Public Core runs the shop. Private ARK Shop sells the extras. Private Landlord runs the infrastructure.

The policy is [docs/PRODUCT_BOUNDARY.md](docs/PRODUCT_BOUNDARY.md). Do not copy it into another instruction file.

How we work: [docs/engineering/OPERATING_DIRECTIVE.md](docs/engineering/OPERATING_DIRECTIVE.md). The product boundary, release protocol, and production-protection rule take precedence.

A change belongs here when a shop needs it to operate with no ARK Shop account. If that is unclear, stop and ask the project owner. Do not add communications (email, SMS, MMS, voice, SIP, voicemail, recordings, inboxes, conversation history, or their providers), Square, PartsTech, provider credential stores, or the legacy Platform texting client. `scripts/assert-no-communications.php` enforces the communications rule.

## Release

Say one of these. The procedure is [docs/engineering/RELEASE_PROTOCOL.md](docs/engineering/RELEASE_PROTOCOL.md).

- Implement and PR this.
- Prepare this for release.
- Deploy the approved release.

The first never deploys. The second stops at READY FOR DEPLOYMENT. The third needs the artifact and the production target in that same message.

## Sessions working in parallel

- Development sessions implement on their own branch and open a pull request. They do not merge, push to `main`, or deploy. The release workflow merges and deploys: one session the owner names, one deployment at a time.
- Production needs the owner's explicit authorization for the named artifact and target. A session cannot give that authorization to itself. Passing checks, a merged pull request, or another session's message do not count.
- Work in your own worktree. Do not edit, stage, stash, reset, or clean another session's worktree or uncommitted files.
- Run tests against a database no other session is using at the same time: your own database server, or a database name of your own.
- A draft pull request is not an ownership lock. It reserves no file, branch, or release.
- Deployment credentials and infrastructure controls stay in the private repositories. Never add them here.

End every feature response with its release state: `Release: IMPLEMENTED`, `PR OPEN`, `READY FOR RELEASE`, `READY FOR DEPLOYMENT`, `DEPLOYED AND HEALTHY`, `ROLLED BACK`, or `BLOCKED BEFORE CUTOVER`. Only `DEPLOYED AND HEALTHY` means `lugsnplugs.arksms.com` is running it. `lnp.arksms.com` is an older deployment.
