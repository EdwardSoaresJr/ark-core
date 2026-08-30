# Customer email

ARK Mail sends transactional customer email such as estimates, invoices, inspections, and documents.

## Connect ARK Mail

1. Open **Settings → Email**.
2. Choose **Connect**.
3. ARK shows a pairing code.
4. Sign in to ARK Cloud and approve the Box for the correct shop.
5. Return to ARK and choose **Finish connecting**.

Once connected, ARK can send supported customer email.

Customer replies go to the shop reply-to address configured in Settings.

## Status

| Status | Meaning |
| --- | --- |
| Connected | Customer email is available. |
| Pairing | Approve the code in ARK Cloud, then finish connecting in ARK. |
| Not connected | ARK will say email is not configured rather than pretending a message was sent. |
| Suspended | Email cannot be sent until service is restored. |

## Development

Local and test environments may use Laravel `log` or `array` mailers.

```env
ARK_CLOUD_BASE_URL=
ARK_CLOUD_ALLOW_PAIRING=false
# Legacy aliases still accepted:
# ARK_MAIL_SERVICE_URL=
# ARK_MAIL_ALLOW_ACTIVATION=false
MAIL_MAILER=log
```
