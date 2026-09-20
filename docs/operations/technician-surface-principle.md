# Technician Surface Principle

**Status:** Active — capability enforcement shipped; scope narrowing follows [Technician Scope Doctrine v1](technician-scope-doctrine-v1.md)  
**Companion:** [Technician Scope Doctrine v1](technician-scope-doctrine-v1.md) (north star) · [Inspection Workflow Principle (1.5)](../inspection/inspection-workflow-principle-1.5.md)

Role ownership and surface ownership are separate concerns. **Surface Principle** covers what shipped in permissions; **Scope Doctrine v1** covers what the technician experience should become (assigned work only).

| Layer | Question |
|-------|----------|
| **Role authority** | What may this user do? (`production.access`, `repair_orders.*`, inspection write) |
| **Surface ownership** | Whose primary workflow does this surface serve? |

Technicians inherited **Work** and **Communications** because `operations.access` was too broad. Capability split now matches doctrine.

---

## Capability model

| Capability | Meaning | Technician | Advisor |
|------------|---------|------------|---------|
| **`production.access`** | Production shell — workboard, repair orders, ARKademy | Yes | Yes |
| **`operations.access`** | Advisor work surface — Work, Communications, telephony interrupt | No | Yes |
| **`repair_orders.view`** | Open RO workspace | Yes | Yes |
| **`repair_orders.lifecycle`** | Move assigned work, record inspection | Yes | Yes |

Technicians land on **Operations** (workboard), not **Work**.

---

## Guiding principle

**Technicians are production users.**

Their primary workflow is:

```
Operations → Repair Order → Inspection / Worksheet / Work Performed
```

Customer communication, follow-up pressure, and decision recovery are **advisor workflows**.

Technicians may participate in communication on a repair order, but they **do not own communication queues**.

---

## Surface ownership

| Surface | Question | Primary user |
|---------|----------|--------------|
| **Work** | What needs attention? | Advisor |
| **Communications** | What needs response? | Advisor |
| **Operations** | What work is in the building? | Technician |

Technician rail (enforced): **Operations**, **Repair Orders**, **ARKademy**.

Advisor rail adds: **Work**, **Communications**, **Intake**, **Customers**, **Vehicles**, and related recovery surfaces.

---

## Comms Attention Gate

Advisor pressure must not block production paths. The gate applies only to users with **`operations.access`**.

---

## Observation week

Stronger signals than “where do you go first?”:

1. What pages does the technician use all day?
2. Do they ever **voluntarily** open **Work**? (They should not have access.)
3. Do they ever **voluntarily** open **Communications**? (They should not have access.)

If Operations → RO → Inspection dominates, the capability model is working. **Further narrowing** (My Work, assigned ROs only) follows Scope Doctrine v1 after observation — not design opinion.

---

## ARK sequence

```
Doctrine → Authority → Observation → Workflow → Projection
```

Do not guess workflow from another role's surfaces (MPI-first for inspection; advisor-first for technicians).
