# Website management architecture

**Status:** Binding  
**Public rendering:** Foundry, on the shop host, from local publication rows plus approved operational fields.  
**Management UI:** Platform, when the shop has paid website management.  
**Records:** Core, on the shop host.

Platform does not connect to shop MySQL. Platform does not keep a second copy of website tables. Ordinary public page requests do not call Platform.

## Planes

| Plane | Owns | Does not own |
| --- | --- | --- |
| Platform | Editor, entitlements, draft and publish commands, revision presentation, conflict handling, optional templates and SEO tools | The public site, the website tables, shop MySQL |
| Core | Sites, drafts, revisions, publications, locks, revision checks, publication integrity, shop identity and operational facts, signed website API | The management UI, public page rendering |
| Foundry | The published site for that shop | Platform availability, a second website store |

A Platform shop id is not a Core shop id. The signed call is scoped to one installation. Core then resolves `public_host` in that installation's database.

## Subscription

ARK charges for website management, not for keeping a site online.

A shop may run Core and Foundry with no website-management subscription. The published site stays up. Content and publication history stay on Core. Paid management access stops. Public rendering has no subscription check.

A Platform outage must not take published sites offline.

## Self-managed publishing

Technically capable owners need a supported way to customize and publish without Platform. That path is templates, code customization, and a local publish workflow. It is not manual database editing, and it is not the old Core visual editor.

Switching between Platform-managed and self-managed authority must keep content, revisions, and publication history.

**Not implemented.** Do not mark this complete until that workflow exists.

## Operational data

The shop maintains name, address, phone, public email, hours, closures, public services, and booking entry in Core. Foundry may show only fields explicitly approved for the public site. Changing those facts must not require a website republish. Do not copy them into the website document.

| Field | Today |
| --- | --- |
| Regular hours | Foundry overlays `business_hours_label` from Core telephony hours after it reads the publication. |
| Shop name, address, phone, public email, holiday closures, public services, booking entry | Not a separate public-data contract yet. Do not invent a second source. |

Never send customer records, repair orders, internal settings, or credentials to Foundry or Platform as website content.

## Write commands

Not implemented in this phase. When built, Core remains the only database writer.

- `PATCH` saves a draft when `expected_revision` matches. A mismatch writes nothing.
- `POST` publishes the next version. The previous publication document stays as stored.
- The transaction locks the site and the draft.
- Core user foreign keys stay null. The Platform user id is stored in revision metadata.
- The same installation signature authorizes the call.
- Self-managed authority changes are an explicit command, not a side effect of a subscription check.

Do not remove the Core editor from a production image until this replacement is verified.

## Read command

`GET /webhooks/cloud/website/{publicHost}`

Same installation HMAC as Fabric events. The host is in the signed path. Unknown host is 404. Duplicate host or duplicate current publication is 409. The handler does not write website rows.
