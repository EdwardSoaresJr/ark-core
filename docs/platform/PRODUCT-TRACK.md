# Product Track

## The company

Not ARK. Not ARKv2. Not Companion. Not Cloud.

> **We help repair shops feel like this is my shop.**

Everything else is implementation.

Software that **belongs to** repair shops — not software *for* them.

## Every product is ownership

| Product | Feeling | Role |
| --- | --- | --- |
| **ARK Platform** | This is my shop. | Platform provisions it. |
| **ARKv2** | This is my work. | Workspace supports it. |
| **Companion** | My customers can reach my shop. | Communication supports it. |
| **Website** | The internet understands my shop. | Public presence supports it. |
| **Voice** | My phone answers like my shop. | Communication supports it. |
| **Stinson** | This is my transportation business. | Same pattern. Different domain. |

Products are not disconnected. They are expressions of ownership.

## Prioritization filter

Before every story:

> **Does this strengthen the feeling of ownership?**

| Story | Today? |
| --- | --- |
| Better onboarding | Yes |
| Better welcome screen | Yes |
| Faster first repair order | Yes |
| Automatic DNS failover | Not today |
| Kubernetes | Not today |
| Another architecture document | Definitely not today |

## What v1 actually is

> **A repair shop can become an ARK customer without talking to Edward.**

**The platform exists to make ownership feel immediate.** Infrastructure is the mechanism. Ownership is the outcome.

## Product board (seven cards)

### 1. Discover — “I found ARK.”

Marketing site · Pricing · Why switch? · CTA  

**Success:** I want to try this.

### 2. Trust — “These people understand repair shops.”

Features · Screenshots · Videos · Testimonials · Migration story  

**Success:** This is built for shops like mine.

### 3. Start — “I’m creating my shop.”

Signup · Shop name · Subdomain · Owner account  

**Success:** I’m committed.

### 4. Become — “My shop is coming online.”

```text
✓ Account
✓ Shop
✓ Workspace
⟳ Provisioning
```

Progress, not complexity. Platform stays invisible.  

**Success:** The system is working for me.

### 5. Arrive — “This is my shop.”

**Design this first.** Not the homepage. Not pricing. This screen.

The promise becomes real here.

```text
Welcome to ARK

Demo Auto Repair

✔ Your workspace is ready.

From this moment forward, this is your shop.

[ Open Workspace ]
```

Not marketing copy — the product delivering on its promise. Ownership happens here.

### 6. Work — ARKv2

Customer · Vehicle · RO. Real business. No demo theater.

### 7. Grow

Invite · Domain · Billing · SMS · Website · Voice · Reviews — after they’ve decided to stay.

---

Underneath (invisible): Shop · Deployment · ProvisioningRequest · ClusterAssignment · Coolify · Stancl · DNS.

## First customer: Demo Auto Repair

Ruthless standard. Walk the adoption journey yourself. Every awkward edge is one a future customer won’t hit.

## Funnel

```text
Visitor → Trial → Shop → Provisioning → First Login → First Vehicle → First RO
```

## Click through (now)

**Product host:** `https://autorepairkeeper.com` (www → apex)

Nav: Home · Features · Pricing · Resources · Login · Start Free Trial

Home → Trial → Provisioning timeline → Arrive → Cloud dashboard → Open Workspace.

Hard-coded. No billing. No Coolify. No Stancl. Wire one screen at a time later.

Production: `https://autorepairkeeper.com`. Local without `COMPANY_DOMAIN`: `/cloud` on the app host.

## Daily question

**Can we make someone feel “This is my shop” today?**

Yes → highest-leverage work in the company.  
No → probably not next.

## Product → Platform

```text
Product
  ↓
Platform
```
