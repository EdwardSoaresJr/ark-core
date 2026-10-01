# ARKademy training gates

**Status:** Doctrine v1 - global shop gate **retired** (2026-09-06)

## Availability

ARKademy / Learn training is available by default to authorized users (normal auth + permissions).

Learning content itself is **not** globally gated.

## Retired model

The shop-wide setting `learn_training_gate_enabled` plus middleware `EnsureLearnArkTrainingCurrent` previously blocked the workboard until staff finished “required” Learn guides (or snoozed).

That product model is wrong: training became a wall everyone climbed, not a tool to qualify a particular person for a particular responsibility.

`LearnArkTrainingGate::isActiveFor()` now always returns `false`. The middleware is not registered. The owner “pause/turn on gate” control is removed. Progress and completion tables remain.

## Future model (not implemented)

Administrators may later assign an **explicit** requirement:

```text
Employee
  × Learning module (ARKademy)
  × Operational gate / capability
```

Example: Landon + Brake Inspection Procedure + must complete before signing off Brake Inspection.

Another employee may not have that requirement.

### Authority boundary

| Owner | Owns |
| --- | --- |
| **ARKademy** | Content, modules, lessons, completion/progress evidence |
| **Core employee / authorization / Process Engine** | Who is required to complete what; which capability is gated; whether the requirement is satisfied |

Do not tightly couple ARKademy content itself to one hard-coded workflow gate.

### Recommended seam

Keep Learn completion evidence (`learn_completions`, checkpoints) as the read-side proof that a module was completed.

Future assignment tables (names illustrative only - do not invent schema now) would live near Core authorization/process:

```text
employee_id + learning_module_key + gated_capability_key + required_at + satisfied_at
```

Process Engine (parked) can later consume that relationship. Do not reintroduce a shop-wide Learn toggle as the gate.
