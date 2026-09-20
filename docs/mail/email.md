# Customer email

ARK Email sends transactional customer email such as estimates, invoices, inspections, and documents.

## Connect ARK Email

1. Open **Settings → Email**.
2. Choose **Connect**.
3. ARK shows a pairing code.
4. Sign in to ARK Platform and approve the Box for the correct shop.
5. Return to ARK and choose **Finish connecting**.

Once connected, ARK can send supported customer email.

Customer replies go to the shop reply-to address configured in Settings.

## Status

| Status | Meaning |
| --- | --- |
| Connected | Customer email is available. |
| Pairing | Approve the code in ARK Platform, then finish connecting in ARK. |
| Not connected | ARK will say email is not configured rather than pretending a message was sent. |
| Suspended | Email cannot be sent until service is restored. |

## Development

Local and test environments may use Laravel `log` or `array` mailers.

```env
ARK_CLOUD_BASE_URL=
# Legacy alias still accepted:
# ARK_MAIL_SERVICE_URL=
MAIL_MAILER=log
```
