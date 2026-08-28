# Conversations H0.1 — Failure Inventory

**Status:** Inventory complete — **no fixes in this pass**  
**Doctrine:** [ark-conversations-v1.md](ark-conversations-v1.md)  
**Rule:** Do not touch Blade, Flutter, CSS, or layouts until H0 exit criteria pass.

> Walk every path that creates or projects a Thread. Answer only. Do not repair yet.

---

## How to read this inventory

For each path: **Thread?** · **Turn?** · **Reason?** · **Next Action?** · **Duplicate Thread risk?**

**Thread** here means the product Thread (relationship projection), not a single `conversations` row.

---

## Shared plumbing

| Piece | Behavior |
| --- | --- |
| Resolver | `ConversationResolver::forContactKey` — unique `(contact_surface, contact_address)` |
| Posture / Turn store | `ConversationPosture` via `ConversationRecorder::applyPosture` — **SMS/Messenger only** |
| Story compose | `UnifiedOperationalTimeline` — relationship scopes merge messages + CallSessions + events |
| Triage dedupe | `CommunicationsAttentionDedupe` — by `customer_id` then phone |
| RO next action | `RepairOrder::communicationNextAction()` — driven by **CommunicationEvent**, not Conversation posture |

**Structural duplicate risk (all paths):** one customer may own Phone + Email + Messenger + Website Conversations. Projection dedupe often collapses triage; authority rows remain multiple.

---

## Path matrix

### SMS inbound

| Question | Answer |
| --- | --- |
| Entry | `MessagingWebhookController` → `TwilioSmsIngress::ingest` → `ConversationRecorder::recordInboundSms` |
| Thread | Resolves Phone + normalized number. Links customer when matched. |
| Turn | Yes — inbound → `waiting_on=shop` |
| Reason | Inventable from last inbound + `ConversationTurnReason` |
| Next Action | Attention lane (shop turn). RO next action **not** updated unless a CommunicationEvent exists. |
| Duplicate | Low for same phone. Parallel email/Messenger threads still possible. |

### SMS outbound (advisor)

| Question | Answer |
| --- | --- |
| Entry | `SendOutboundMessageAction` → `recordOutboundSms` / `recordOutboundSmsToConversation` |
| Thread | Explicit Conversation or `forCustomer` (phone preferred) |
| Turn | Yes — outbound + actor → `waiting_on=customer` |
| Reason | Inventable from last outbound |
| Next Action | Attention → waiting customer. RO next action only if paired CommunicationEvent (estimate SMS yes; appointment/payment/inspection SMS often **no**) |
| Duplicate | Low if phone key used. Wrong Conversation arg can mis-link. |

### Voice — answered / missed / completed

| Question | Answer |
| --- | --- |
| Entry | `ProcessIncomingCallAction` / `ProcessCallStatusAction` |
| Thread | **Creates CallSession only — no Conversation.** Relationship Story includes call only when `customer_id` set or phone Conversation exists for merge. |
| Turn | **No** Conversation posture update. Queue uses `worked_at` / status. |
| Reason | Inventable from CallSession status (Missed call, etc.) — **not** stored as Thread Reason |
| Next Action | Calls Waiting / Attention call row — separate from Conversation turn |
| Duplicate | Call + SMS for same customer collapsed by Attention dedupe when customer/phone align. Call-only unmatched → call-keyed row. |

### Voicemail / recording

| Question | Answer |
| --- | --- |
| Entry | `ProcessCallRecordingAction` (voicemail flag / recording) |
| Thread | Updates CallSession media only |
| Turn | No |
| Reason | Inventable (Voicemail) when session on Story |
| Next Action | Same CallSession triage if unworked |
| Duplicate | None new |

### Portal estimate viewed

| Question | Answer |
| --- | --- |
| Entry | `RecordPortalEstimateViewAction` → `recordPortalEstimateView` |
| Thread | `forCustomer` (phone preferred) + ConversationMessage |
| Turn | **No** — posture skipped for `portal_estimate_view` |
| Reason | Strong via CommunicationEvent `EstimateViewed` + observations |
| Next Action | Yes for RO when WaitingApproval (“Follow up viewed estimate”) |
| Duplicate | May land on phone thread while estimate email lived on email thread |

### Portal estimate approved

| Question | Answer |
| --- | --- |
| Entry | `PortalEstimateAuthorizeController` |
| Thread | **Does not write ConversationMessage** |
| Turn | No |
| Reason | Via `ApprovalEvent` + CommunicationEvent `ApprovalFollowUp` |
| Next Action | Yes — CommunicationEvent |
| Duplicate | N/A (no Conversation write) — **Story gap:** approval may be event-only on timeline |

### Inspection viewed (portal)

| Question | Answer |
| --- | --- |
| Entry | Portal inspection show |
| Thread | **No Conversation write found** |
| Turn / Reason / Next | Not on Thread path today |
| Duplicate | N/A |

### Estimate sent (SMS)

| Question | Answer |
| --- | --- |
| Entry | `SendEstimateLinkAction` → outbound SMS + CommunicationEvent `EstimateSent` |
| Thread | Phone Conversation via outbound SMS |
| Turn | Yes (outbound) |
| Reason | Message + EstimateSent event |
| Next Action | Yes — RO wait for customer |
| Duplicate | Same as outbound SMS |

### Estimate sent (email)

| Question | Answer |
| --- | --- |
| Entry | `EstimateDocumentEmailDelivery` → `forEmail` |
| Thread | **Email Conversation** (separate from phone) |
| Turn | **No** (email skips posture) |
| Reason | CommunicationEvent |
| Next Action | Yes via event |
| Duplicate | **High** vs SMS phone thread for same customer |

### Payment request SMS / email

| Question | Answer |
| --- | --- |
| Entry | `SendPaymentLinkAction` / email delivery |
| Thread | SMS → phone; email → email Conversation |
| Turn | SMS yes; email no |
| Reason | SMS inventable from body only (often **no** CommunicationEvent); email has InvoiceSent |
| Next Action | SMS: posture only. Email: CommunicationEvent |
| Duplicate | Email vs phone split |

### Payment received

| Question | Answer |
| --- | --- |
| Entry | `EmitPaymentReceivedEvent` |
| Thread | **No ConversationMessage** — OperationalEvent only |
| Turn | No |
| Reason | Inventable from OperationalEvent on Story |
| Next Action | Not Conversation posture |
| Duplicate | N/A |

### Manual internal note

| Question | Answer |
| --- | --- |
| Entry | `StoreConversationInternalNoteController` → `recordInternalNote` |
| Thread | Existing Conversation |
| Turn | **No** (Internal channel) |
| Reason | Inventable from note metadata |
| Next Action | No |
| Duplicate | Uses existing Thread |

### Call note

| Question | Answer |
| --- | --- |
| Entry | `StoreCallSessionNoteController` → `forContactKey(Phone)` + `recordCallNote` |
| Thread | **Creates phone Conversation if missing** — first link CallSession → Conversation |
| Turn | No |
| Reason | Inventable (`call_note`) |
| Next Action | May mark CallSession handled |
| Duplicate | Phone vs email parallel Threads |

### Appointment confirmation / reminder SMS

| Question | Answer |
| --- | --- |
| Entry | `SendAppointmentConfirmationSmsAction` / `SendAppointmentReminderSmsAction` (parked product; code may exist locally) |
| Thread | Outbound SMS phone path |
| Turn | Yes if advisor actor |
| Reason | Body copy only — no CommunicationEvent |
| Next Action | Posture only |
| Duplicate | Same as outbound SMS |

### Attention / Inbox / mobile projections

| Surface | Thread identity | Duplicate triage? |
| --- | --- | --- |
| `CommunicationsQueueResolver` | Merges call + unread + shop-turn; dedupes by customer/phone | First wins after sort — OK when IDs align |
| `ConversationAttentionCandidateBuilder` | Per `conversation_id`; observations from **messages only** (CallSessions excluded) | Email + phone → two candidates before list dedupe |
| Inbox list | Prefers conversation over call when phone Conversation exists | Call-only until SMS/note creates Conversation |
| Hub / `forCustomerRelationship` | Customer-scoped Story | Misses CallSession with no `customer_id` and no phone Conversation |
| Mobile attention | Same queue; **same call can appear in two sections** | Presentation duplicate |
| Mobile conversations list | Conversation rows only — **call-only drop out** | Call-only invisible until Conversation exists |

---

## Known H0 failure themes (inventory — not fixed)

1. ~~**Calls do not update Turn**~~ — **Resolved H0.2.1** via `ConversationTurnPrecedence` (newest unresolved inbound → Waiting on Shop).
2. **Portal approve is not a Story message** — event-only; Thread Story may omit “Customer approved” as a first-class beat depending on mappers.
3. **Phone vs email Conversations** — estimate/payment email creates a second authority Thread for one relationship.
4. **Payment received / inspection portal view** — weak or absent Thread writes.
5. **Attention candidate observations ignore CallSessions** — Reason/Story pressure incomplete for call-only moments.
6. **Call without customer_id** — Story gap on Hub until match or call note.
7. **Next Action split brain** — Conversation posture vs RO `communicationNextAction` vs Calls Waiting.

---

## H0.1 exit

Inventory complete. **No production behavior changed.**

Next: **H0.2 Sarah saga tests** — assert The Six Ones after every step. Any fail ⇒ H0 fails. Do not begin H1.
