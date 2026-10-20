---
layout: home

hero:
  name: ARK Core
  text: Shop management you can install, read, and extend.
  tagline: The public knowledge base for the open-source automotive shop system. Install it without ARK Shop. Build on the extension points and HTTP surfaces that exist in this repository.
  actions:
    - theme: brand
      text: Install ARK Core
      link: /getting-started/
    - theme: alt
      text: Extending ARK Core
      link: /extending/
    - theme: alt
      text: Building integrations
      link: /integrations/

features:
  - title: One shop, on your server
    details: Customers, vehicles, repair orders, estimates, inspections, scheduling, documents, reporting, and a payment ledger. A stock install does not require ARK Shop.
    link: /getting-started/
    linkText: Getting started
  - title: Extend it as a Laravel application
    details: There is no plugin manager. Service providers, the container, events, routes, views, commands, and migrations are the mechanisms that exist today.
    link: /extending/
    linkText: Extension guide
  - title: Integrate with the client APIs
    details: Staff HTTP surfaces live under /api/mobile, /api/desk, /api/tech, and /api/station. They are real. They are not a versioned public platform contract.
    link: /integrations/
    linkText: Integration guide
  - title: Know the current limits
    details: Customer texting, hosted phones, in-app card capture, and the PartsTech shop catalog are ARK Shop. A screen in Core is not that service.
    link: /guide/communications-and-integrations
    linkText: Product boundary
---

## Who this is for

Shop owners and self-hosters use [Getting started](/getting-started/) and the [shop guide](/guide/). Developers who change or extend Core use [Architecture](/architecture/) and [Extending ARK Core](/extending/). People building mobile apps, integrations, or products around Core use [Building integrations](/integrations/) and [Mobile clients](/mobile/).

## How to read it

Every technical page should tell you what something is, why it exists, how it works, how to use or extend it, where it stops, and which source file to open. Pages distinguish three kinds of claims:

| Kind | Meaning |
| --- | --- |
| Implemented | The current source does this, and this site describes that behavior. |
| Partial | Some of the workflow exists. The missing piece is named. |
| Not in Core | The capability is ARK Shop, a separate app, or not implemented. |

Domain words are collected in the [glossary](/glossary).

## Repository layout for these docs

The site source is [`docs/knowledge-base`](https://github.com/EdwardSoaresJr/ark-core/tree/main/docs/knowledge-base) inside the public Core repository. That keeps a documentation change in the same pull request as the code it describes. The parent `docs/` directory remains internal engineering notes and is not published by this site.
