# ARK Core

Public Core runs the shop. Private ARK Shop sells the extras. Private Landlord runs the infrastructure.

The policy is [docs/PRODUCT_BOUNDARY.md](docs/PRODUCT_BOUNDARY.md). Do not copy it into another instruction file.

A change belongs here when a shop needs it to operate with no ARK Shop account. If that is unclear, stop and ask the project owner. Do not add Twilio, voice, Square, PartsTech, provider credential stores, or the legacy Platform texting client.

## Release

Say one of these. The procedure is [docs/engineering/RELEASE_PROTOCOL.md](docs/engineering/RELEASE_PROTOCOL.md).

- Implement and PR this.
- Prepare this for release.
- Deploy the approved release.

The first never deploys. The second stops at READY FOR DEPLOYMENT. The third needs the artifact and the production target in that same message.

End every feature response with its release state: `Release: IMPLEMENTED`, `PR OPEN`, `READY FOR RELEASE`, `READY FOR DEPLOYMENT`, `DEPLOYED AND HEALTHY`, `ROLLED BACK`, or `BLOCKED BEFORE CUTOVER`. Only `DEPLOYED AND HEALTHY` means `lugsnplugs.arksms.com` is running it. `lnp.arksms.com` is an older deployment.
