# ARK Texting (Core)

Customer texts go through **ARK Texting** on ARK Platform. Core never holds Twilio credentials.

## Connect

1. Pair the Box with ARK Platform.
2. Enable ARK Texting for the installation in Platform.
3. Assign a business number on Platform (`texting:assign-number`).
4. Advisors text from Conversations as usual.

## Ownership

| Layer | Owns |
| --- | --- |
| Core | Customer, Conversation, advisor UX, local history |
| Platform | Provider, number, entitlement, STOP/suppression, send audit |

## Fabric inbound

Platform verifies the provider webhook, resolves Shop from the assigned number, then pushes `sms.incoming.received` over Fabric. Core writes `ConversationMessage` and broadcasts the interrupt.
