# Security

Report vulnerabilities privately. Do not file a public issue that includes secrets, customer data, or live shop host details.

## How to report

Use GitHub Security Advisories on [EdwardSoaresJr/ark-core](https://github.com/EdwardSoaresJr/ark-core), or contact the copyright holder through GitHub.

Include:

- Affected version or commit
- What an attacker can do
- Reproduction steps that do not use real customer data

## What this project will not treat as a Core bug

- Missing customer email, texting, hosted phone service, in-app card capture, or PartsTech shop catalog access on a stock install that is not using ARK SaaS
- A model assistant that has no provider configured (`DRAGON_PROVIDER` defaults to `none`)

## Secrets and environment files

Inject secrets through the process environment, a secret store, or a local `.env` file that is not committed. `.env.example` documents the variable names only.

Do not commit `.env`, API tokens, database passwords, private keys, or shop data. A local `.env` is the right place for development secrets. Git is not.

Hosted ARK credentials stay with the hosted service. A stock Core install leaves `ARK_PLATFORM_BASE_URL` and `ARK_PLATFORM_OFFLINE_RECOVERY_PUBLIC_KEY` empty unless that shop has opted into the hosted service. Rotating a live credential is an operations task.
