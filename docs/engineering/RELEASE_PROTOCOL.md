# Release protocol

Cursor and Junie follow this file when the user says one of the three commands below. Do not invent a fourth path. Do not copy the product boundary into this file. The boundary stays [docs/PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md).

Passing checks do not mean a pull request is merged. A merged pull request does not mean a release is deployed.

## Release state

End every response about a feature with one state. The line is the last line of the response. It answers whether LugsNPlugs is running that feature. Do not advance to the next state unless the user asks for that stage.

| State | What is true | Running at LugsNPlugs |
| --- | --- | --- |
| IMPLEMENTED | The change is written. No pull request is open. | No |
| PR OPEN | A pull request is open. It is not merged. | No |
| READY FOR RELEASE | The approved commits are merged. No immutable artifact exists yet. | No |
| READY FOR DEPLOYMENT | The artifact and its rollback are recorded. The shop has not changed. | No |
| DEPLOYED AND HEALTHY | The named target is serving that artifact and the health checks passed. | Yes, that target only |
| ROLLED BACK | The cutover failed and the previous artifact is serving. | The previous release |
| BLOCKED BEFORE CUTOVER | The shop was not changed. | Unchanged |

Write it as `Release: PR OPEN`. Use the state that matches the repository, not the last command that was attempted.

## Architecture this protocol keeps

- ARK Core stays independently deployable. A shop does not need ARK Shop or Landlord on its request path.
- ARK Shop consumes one exact Core commit. That commit is the full SHA in `pin`. Moving the pin is a deliberate Shop change. Merging Core does not move it.
- ARK Landlord is the control plane. It publishes desired state and observes applied state. A shop request does not go through Landlord.
- The target is one shared application release on a serving node, one database per shop, and tenancy resolved from the domain.
- Do not make Docker-per-shop the standard.
- LugsNPlugs is transitional. The current production target is `lugsnplugs.arksms.com`. On 20 October 2026 that name was served by container `ark-lugs-standard` on `143.110.199.236`, image `sha256:36e9ede9cd7ab9c5770f86a98cbb7bec1e2bd53ab8126a8a5b2545f5ea97fbdb`, Core `d926813`, Shop `04f31a7`.
- `lnp.arksms.com` is an older separate deployment, container `ark-hosted-lnp`, Core `58f6f8a`. Do not update it when deploying the current shop. `lugsnplugs.com` and `app.lugsnplugs.com` are not this target.
- Production protection in Core still governs Vultr `149.28.249.13` and DigitalOcean `143.110.199.236`.
- Landlord `docs/node-agent.md` is a proposed procedure. It is not authorization to install or run the agent on ark-hosted-1.

## Where work goes

| Change | Repository |
| --- | --- |
| A shop can operate with no ARK Shop account | ARK Core |
| SMS, MMS, voice, Dragon, PartsTech, Square, credentials, subscriptions, or the Shop overlay | ARK Shop |
| Hosts, databases, storage, deployment, monitoring, or recovery | ARK Landlord |

If the repository is unclear, stop and ask. Use a feature branch. If the current checkout has unrelated uncommitted work, use a separate worktree. Do not stage that unrelated work.

## 1. Implement and PR this.

Never deploy. Never publish an image. Never change a live host, DNS, or tenant data.

1. Choose the repository from the table above and branch from its current line of work.
2. Implement only the requested change.
3. Run the tests that cover that change.
   - Core: `php bin/pest` for the affected tests. Do not use `vendor/bin/pest` when `vendor` is a symlink to another checkout. The overnight backlog is not a release gate.
   - Shop: the contract, package, and `php guard/self-test.php --core <ark-core>` checks that the change touches. Shop CI is `.github/workflows/boundary.yml`.
   - Landlord: the test files that cover the change. Do not point a test at a live shop.
4. Review the diff. Leave unrelated edits unstaged.
5. Commit and push the feature branch. Open one pull request for one change.
6. Report the pull request URL, the check status, and whether the pull request is still open. Say which required checks are absent. Do not merge it. End with `Release: PR OPEN`. If the pull request was not opened, end with `Release: IMPLEMENTED`.

## 2. Prepare this for release.

Stop at **READY FOR DEPLOYMENT**. Do not cut a shop over. Do not restart services, change DNS, or run a freeze script.

1. Name the commits. A green check is not proof they merged. Read the pull request state or the named SHA.
2. Confirm the required review and the checks that exist for that repository. Report missing checks. Do not treat a missing check as a pass.
3. List database migrations and configuration the release adds. Confirm a shop database cannot read another shop's rows. Confirm a shop still on the previous release keeps working.
4. Build the artifact for the product being released. Record the identity and the rollback target in the pull request.

Core, for a LugsNPlugs image:

- `scripts/assert-core-source-published.sh` allows a production image only when the commit is reachable from `origin/main`. If it is not, stop. The result is **BLOCKED BEFORE CUTOVER**, not READY FOR DEPLOYMENT.
- The only publish command is `SOURCE_COMMIT=<full-sha> ./infra/build-runner/mac/publish-ghcr-ark.sh`. It archives that commit, pushes `ghcr.io/edwardsoaresjr/ark-core` tagged by SHA, and refuses floating tags. It does not deploy Coolify and it does not touch LugsNPlugs.
- Record the image as `ghcr.io/edwardsoaresjr/ark-core@sha256:<digest>` from the imagetools output. `scripts/assert-lnp-core-image-ref.sh` accepts only that form. The previous digest is the rollback target.
- Self-hosted Compose (`docker compose up -d --build`) is not the LugsNPlugs artifact.

Shop:

- Set `pin` to the approved Core SHA. Do not pin a commit that has not been named for this release.
- The hosted image is `ark-hosted:<core pin>`, built from the published Core image and this overlay. The amd64 procedure is Landlord `docs/amd64-hosted-image.md`. A local arm64 image id is not the droplet artifact.
- `php bin/describe-hosted-release.php --core <ark-core> --saas <ark-saas>` in Landlord prints the Core pin and Shop revision. It does not register a release. `image_digest` stays empty until the image is inspected.
- Verification is `docker image inspect` through Landlord `bin/verify-hosted-image.php`. A file hash is not verification.

Landlord:

- A release row stores the Core revision, Shop revision, and artifact reference. Registration does not verify it and does not place a shop.
- The rollback target is the previously verified release, not a new image.

5. Write the release record in the prepare pull request: repository, full commit, artifact digest or registered release id, previous artifact, migrations, configuration, and rollback. Then stop. End with `Release: READY FOR DEPLOYMENT`. If the commits are merged and no artifact exists, the state is `Release: READY FOR RELEASE`.

## 3. Deploy the approved release.

Run this only when the same user message says **Deploy the approved release** and names both the artifact and the production target. The artifact is a `ghcr.io/edwardsoaresjr/ark-core@sha256:<digest>`, an `ark-hosted:<core pin>` image that has been inspected, or a Landlord release id whose digest was verified. The target is the host or shop being changed.

If either name is missing, stop. End with `Release: BLOCKED BEFORE CUTOVER`.

This command does not authorize a different digest, host, DNS change, database drop, `artisan down`, Horizon stop, or `freeze-vultr.sh`. A maintenance page or a stopped worker is not permission to reverse it. Read the current state first.

Follow the topology already approved for that target.

- A LugsNPlugs deploy names `lugsnplugs.arksms.com` unless the user names a different target. It does not change `lnp.arksms.com`. The publish script does not perform that cutover. The proposed node agent does not perform it. If the repository has no approved cutover for that digest, stop **BLOCKED BEFORE CUTOVER**. Do not invent SSH, Coolify, or a new container per shop.
- A new shop on the shared release uses a Landlord release that `docker image inspect` has verified. Placement still requires the explicit target. Do not install the node agent while `docs/node-agent.md` says the procedure is not authorized.
- Keep the existing shop database, storage, and DNS. Operational rollback returns the shop to the recorded previous artifact. It does not delete tenant data.

Before cutover, record the artifact in production and the rollback artifact. After cutover, check health for the named target: the public `/up` response and one authenticated application request that does not create a customer, lead, or verification code. Demo and any shop that was not the target still answer `/up`.

If the named target is unhealthy, restore the recorded previous artifact and check `/up` again. End with one line:

- `Release: DEPLOYED AND HEALTHY`
- `Release: ROLLED BACK`
- `Release: BLOCKED BEFORE CUTOVER`

## Checks and branch protection

Observed on ARK Core, not changed by this file:

| Line | Protection | Workflows |
| --- | --- | --- |
| `main` | Ruleset `main`: no deletion, no force-push, pull request required, zero approving reviews, `gitleaks` required | `secret-scan.yml`, `tests.yml` (`pest`), `knowledge-base.yml` |
| `v2/native-messaging` | No ruleset | `knowledge-base.yml` only |

`pest` runs on `main` and is not required. Shop rulesets could not be read with the current GitHub plan. Shop CI is the boundary workflow. No workflow deploys production. Do not add one.

Do not remove `gitleaks`, force-push protection, or the pull-request rule. Recommend these only as additions, after the suite is green: require `pest` on `main`, put `tests.yml` and `secret-scan.yml` on the branch that is actually released, and require one approving review before a production release merges. A required check that is already failing does not make a release safer.

## Release record

Every prepared release names:

- repository and full commit
- artifact, as a digest or a verified Landlord release id
- previous artifact, which is the rollback
- migrations and configuration
- production target, left blank until deploy

Deploy writes the same record again with the result: **DEPLOYED AND HEALTHY**, **ROLLED BACK**, or **BLOCKED BEFORE CUTOVER**.
