# Front Counter Station Continuity

**Mission:** Make the Front Counter feel alive — ambient awareness, not a dashboard.

The Front Counter is a **station**. The VVX is one renderer today. Build **station continuity**; do not embed VVX-specific business logic in projections.

## Scope hierarchy (projection only)

```
Observation Stream
├── Shop Continuity (future wallboard / shop pulse)
├── Station Continuity ← Front Counter VVX consumes this
└── Operator Continuity ← Portable station / ARK Staff
```

## Question

**What must never be missed at the Front Counter?**

Filtered observation types live in `StationContinuityObservationScope`. Not every observation belongs here — filter by operational responsibility at the station.

## Screen ownership

| State | Owner |
|-------|--------|
| Idle | ARK — baseline readiness + ambient announcements |
| Ringing | Poly — native UI |
| On call | Poly |
| After call | ARK — resumes on idle screensaver |

## Ambient announcements

Idle is the baseline. When a Front Counter observation occurs:

1. Replace idle with a **temporary station announcement** (one glance, &lt;1 second).
2. Auto-expire after **90 seconds** — return to idle without dismissal.
3. Appliance **informs**; it does not require interaction.

Examples:

```
CUSTOMER REPLIED
Sarah Johnson
Waiting 2 minutes
```

## Implementation

| Piece | Role |
|-------|------|
| `StationContinuityObservationScope` | Front Counter observation filter |
| `StationContinuityProjection` | Idle baseline + active announcement |
| `device-appliance.blade.php` | Tiny SSR renderer (VVX today) |

## Explicitly not built here

Notification center · dashboard · inbox · timeline · miniature ARK · parallel authority · VVX-specific rules in PHP

## Acceptance

Walk in. Look at the Front Counter phone. Within one second:

- Counter is quiet → station readiness
- Something changed → brief announcement, then back to readiness

Rich action stays in **ARK Staff**.

## Companions

- [vvx350-idle-appliance-v1.md](./vvx350-idle-appliance-v1.md)
- [ark-operator-continuity-doctrine.md](../mobile/ark-operator-continuity-doctrine.md)
