# ARK Message Actions v1

**Status:** Shipping — first platform slice  
**Not:** A canned-SMS product

> Advisors send **intent**. ARK delivers **copy**, over the customer's current channel, and may handle expected replies. Conversation remains authority for what was said.

**Companions:** [ark-conversations-v1.md](ark-conversations-v1.md) · telephony roadmap (Phase 2 conversation actions) · Attention Queue doctrine

---

## Vocabulary

| Term | Meaning |
| --- | --- |
| **Message Action** | Operator intent — Send Address, Send Reminder, Send Tow Info |
| **Delivery** | Channel projection (SMS today; portal / app later) |
| **ConversationMessage** | Always what was said (append-only) |
| **Expected replies** (optional) | Interpretable inbound (`1` / `3` / `CALL`) → structured outcome |
| **Attention** | Projection only when a human must act — never `AttentionItem` authority |

Do **not** center the product on a template library. Templates are implementation inside an action.

---

## Shipped

| Action | Surface | Notes |
| --- | --- | --- |
| Send Estimate / Pay / Inspection | Quick Reply | Existing Phase 2 grammar |
| **Send Address** | Quick Reply | Shop Settings → SMS + Maps |
| **Send Pickup Info** | Quick Reply | Address + hours + after-hours notes |
| **Send Hours** | Quick Reply | Communications hours |
| **Send Tow Info** | Quick Reply | When tow phone configured |
| **Send Wi-Fi** | Quick Reply | When SSID configured |
| **Appointment confirmation / reminder** | Scheduler + appointment SMS | Reply menu stamped as contract |
| **Reply interpreter** | Twilio inbound webhook | After consent keywords |

### Expected replies (appointment contracts)

| Reply | Outcome |
| --- | --- |
| `1` / Confirm / YES | Appointment → Confirmed · auto ack · no Attention |
| `2` / Reschedule | Attention: Customer wants to reschedule |
| `3` / Directions | Auto-send shop address · no Attention |
| `4` / Call / Callback | Attention: Customer requested callback |

Contract lives on outbound `ConversationMessage.metadata` (`message_action`, `expected_replies`, `appointment_id`, `contract_expires_at`). Consumption is inferred from a later inbound with `message_action_reply` — messages stay append-only.

---

## Sequence (earned next)

```text
✓ Send Address + Pickup / Hours / Tow / Wi-Fi
✓ Appointment Reminder / Confirmation as Actions
✓ One reply interpreter
4. Composable reply packs (optional toggles per send)
5. Cross-channel delivery (portal / app)
6. Voice callback queue (after Attention + Call earns it)
```

---

## Settings

**Settings → Communications → General → Message Actions**

- Tow company / phone / notes
- Waiting room Wi-Fi SSID / password
- After-hours pickup notes

Address and hours come from existing shop identity / telephony hours.

---

## Explicit non-goals

- Canned response CRM
- SMS-thread / inbox authority
- `AttentionItem` rows
- Updating `ConversationMessage` after create
