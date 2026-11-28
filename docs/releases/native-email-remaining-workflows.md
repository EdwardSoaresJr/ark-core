# Native email — remaining standalone workflows

Restores appointment, website lead / appointment-request confirmation, booking identity codes, and staff email verification onto the existing installation SMTP / `CustomerOutboundMail` architecture from Core #290.

Public positioning: restoration of native standalone functionality following commercial infrastructure separation. Hosted SaaS remains on managed ARK Shop delivery with no installation SMTP fallback.

## Staff verification

On standalone installs, staff verification uses the same Settings / env installation transport when Platform is unavailable (so administrators are not forced to edit env files after configuring Email in Settings). Hosted SaaS continues to use Platform / process `MAIL_*` and never installation SMTP.
