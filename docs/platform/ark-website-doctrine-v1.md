# ARK Website Doctrine - One Customer Application

**Status:** Canonical  
**Scope:** LugsNPlugs customer experience - anonymous and authenticated states

## The sentence

**One LugsNPlugs customer application with two authentication states.**

Not *website* + *portal*. Not *marketing* + *product*.

| State | Who | What they do |
| --- | --- | --- |
| **Anonymous** | Guest | Browse, learn, request service |
| **Authenticated** | Signed-in customer | Vehicles, estimates, approvals, payments, history |

Sign in **unlocks capability**. It does not change websites.

---

## Three applications - no more

ARK has exactly **three** applications. Do not invent a fourth.

| # | Application | Audience | Scope |
| --- | --- | --- | --- |
| **1** | **Customer Application** | Customers | Anonymous + authenticated states above |
| **2** | **Operations Application** | Staff | Everything under `/app/*` |
| **3** | **Admin Platform** | Owners / admins | **Website**, Growth, Voice, Settings, infrastructure - not customer-facing |

Owners think *manage my website* → **Website** product. *Why did it perform / what to fix* → **Growth**. See [ark-website-admin-v1.md](ark-website-admin-v1.md).

Route prefixes (`/`, `/portal/*`, `/app/*`) and guards are implementation detail - not product boundaries.

---

## Customer vocabulary

**Never use "Portal" in customer-facing UI.**

"Portal" may remain in code, routes, namespaces, and staff documentation.

Customers should not think *I'm going to the Portal.* They should think *I'm signing into my account.*

| Avoid (customer UI) | Use instead |
| --- | --- |
| Customer Portal | *(omit - just the shop name)* |
| Portal Login | **Sign In** |
| Access the portal | **Sign in** · **My Account** |
| Portal home | **My Vehicles** · **My Account** |

Feature labels customers already understand:

- **Sign In**
- **My Account**
- **My Vehicles**
- **My Repairs**
- **My Estimates**

Staff and operations UI may still say "portal" when describing customer links, engagement, or payment configuration.

---

## One shell

**`x-customer.shell` is the only customer shell.**

```
Anonymous  ──→  x-customer.shell  ──→  content
Authenticated ──→  x-customer.shell  ──→  content
```

Authentication changes **content inside the shell** - not the shell itself.

That yields identical navigation, responsive behavior, spacing, footer, and typography. Only available features change.

Do not maintain parallel layouts (`lead-intake` as a separate HTML document, unused `layouts/portal.blade.php`, duplicate header/footer in public views).

`x-portal.app` is an internal alias for `x-customer.shell` - not a second shell.

---

## Unlock, not redirect

When a customer signs in, they should **never feel like they've left**.

| Bad | Good |
| --- | --- |
| Website → Redirect → Different application | Website → **Unlock** |

Same header. Same footer. Same type rhythm. More links and personal content appear - that's it.

This is subtle and huge for trust.

---

## One UI - simplifying rule

This doctrine **reduces** complexity. It eliminates a category of questions:

| Question | Answer |
| --- | --- |
| Should the portal look different? | **No.** |
| Should it have a different layout? | **No.** |
| Should it have different components? | **No.** |
| Should we duplicate this Blade component? | **No.** |

There is one customer application. Authentication reveals more capability. That's it.

---

## Engineering rule

Any PR that **materially changes customer visual language** must review **both authentication states** in the same change whenever practical.

Visual drift between anonymous and authenticated is technical debt.

**Operations (`/app/*`) and Admin Platform are out of scope** for this doctrine.

---

## Definition of done

Customer-facing UI work is incomplete until:

1. Both anonymous and authenticated states reviewed for consistency.
2. Shared components reused - no parallel forks.
3. Customer copy uses Sign In / My Account vocabulary - not "portal."
4. Changes render through `x-customer.shell`.

---

## Implementation anchors (ARK SMS)

| Layer | Path | Role |
| --- | --- | --- |
| **Customer shell** | `resources/views/components/customer/shell.blade.php` | **Only** customer layout entry point |
| Authenticated alias | `resources/views/components/portal/app.blade.php` | Thin wrapper → `x-customer.shell` |
| Site chrome | `resources/views/partials/customer/site-header.blade.php` | Header + nav - both states |
| Site chrome | `resources/views/partials/customer/site-footer.blade.php` | Footer - both states |
| Anonymous wrapper | `resources/views/components/public/lead-intake.blade.php` | SEO + instrumentation → delegates to shell |
| Customer styling | `.public-surface`, `.customer-header` in `resources/css/app.css` | One token set |
| Navigation | `App\Ark\Customer\CustomerSurfaceNavigation` | Sign In (guest) · My Vehicles (signed in) |
| Breadcrumbs | `App\Ark\Customer\CustomerSurfaceBreadcrumbProjection` | Customer vocabulary only |
| URLs | `App\Ark\Customer\CustomerSurfaceUrls` | Same host continuity |
| Branding | `App\Support\Branding\Branding` + `partials/branding/_favicons` | Same tab mark |

---

## PR checklist (customer visual changes)

- [ ] Anonymous and authenticated chrome still match (header, footer, typography, buttons)
- [ ] No new state-specific duplicate components without documented reason
- [ ] Customer-visible copy avoids "portal"
- [ ] Sign-in transition preserves shell identity (unlock, not redirect)
- [ ] All customer pages use `x-customer.shell` (directly or via `lead-intake` / `portal.app`)
- [ ] Favicon and title flow through `Branding`

---

## Companions

| Document | Relationship |
| --- | --- |
| [ark-surfaces.mdc](../../.cursor/rules/ark-surfaces.mdc) | Three applications - customer, operations, admin |
| [ark-earned-authority-v1.md](../ecosystem/ark-earned-authority-v1.md) | Public marketing v1 closed - publication when shop earns new knowledge |
| [ecosystem-identity.md](../branding/ecosystem-identity.md) | Tab mark across ARK products |
| [shop-identity-v1.md](shop-identity-v1.md) | One shop deployment - customer routes on same host |
| [website-management-architecture-v1.md](website-management-architecture-v1.md) | Platform manages, Core stores, Foundry serves |

---

## Public marketing v1 - closed

**Closed:** 2026-07-06

v1 is closed - not because the customer site is perfect, but because the foundation is coherent: problem authorities, advisor intake, trust signals, footer, publication paths, and the shop-experience hook (hidden until earned).

**Homepage layout is frozen** until analytics or floor observation earns a specific change.

The customer application evolves when **the shop earned something new to say** - not when someone has a new layout idea.

Full doctrine: [ark-earned-authority-v1.md](../ecosystem/ark-earned-authority-v1.md)
