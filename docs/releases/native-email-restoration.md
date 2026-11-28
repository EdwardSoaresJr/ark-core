# Restored Native Email Functionality

Following the separation of commercial communications services from ARK Core, native email functionality has been restored using installation-controlled mail infrastructure. Self-hosted installations can now send customer communications independently, without an ARK account or commercial subscription.

This continues our commitment to a complete, independent, open-source automotive shop management system.

## In this release

- Settings → Email for Disabled / Installation Settings / Environment Managed modes
- SMTP and sendmail with encrypted credentials, masked passwords, and configuration audit history
- Test email against the selected installation transport
- Estimate, invoice, inspection, document, and statement email with attachments and portal links
- Honest delivery statuses (transport accepted is not confirmed recipient delivery)
- Hosted SaaS remains on managed delivery only — no installation SMTP fallback

## Not in this release

- Appointment notification email
- Lead confirmation email
- Staff email verification beyond existing staff auth mail
- SMS, MMS, voice, or other commercial provider integrations

## Next focused increment

Appointment email, lead confirmations, and staff email verification.
