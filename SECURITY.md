# Security

Report vulnerabilities privately. Do not file a public issue that includes secrets, customer data, or live shop host details.

## How to report

Use GitHub Security Advisories on [EdwardSoaresJr/ark-core](https://github.com/EdwardSoaresJr/ark-core), or contact the copyright holder through GitHub.

Include:

- Affected version or commit
- What an attacker can do
- Reproduction steps that do not use real customer data

## What this project will not treat as a Core bug

- Missing SMS, email, or in-app card capture on a stock install that is not connected to ARK Platform
- A model assistant that has no provider configured (`DRAGON_PROVIDER` defaults to `none`)

Provider API tokens do not belong in Core Settings or `.env` for a public install. Rotating a live credential is an operations task.
