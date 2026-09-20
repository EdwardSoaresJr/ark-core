# Deployment Flow v1

**Status:** Provisioning v1 **frozen** — truth only  
**Companions:** [adapter-rule-v1.md](adapter-rule-v1.md) · [provisioning-request-authority-v1.md](provisioning-request-authority-v1.md) · [cluster-assignment-authority-v1.md](cluster-assignment-authority-v1.md) · [cluster-authority-v1.md](cluster-authority-v1.md)

## Provisioning v1 — frozen

```text
Create Shop
      │
      ▼
Create Deployment
      │
      ▼
ClusterAssignmentPolicy
      │
      ▼
Create ClusterAssignment
      │
      ▼
STOP
```

**Nothing else.**

No jobs. No Docker. No Stancl. No Coolify. No DNS. No admin user. No tenant.

Just establish platform truth: Shop + Deployment + where it lives.

---

## Layered architecture

```text
Shop
  ↓
Deployment
  ↓
ClusterAssignment
  ↓
ProvisioningRequest     ← next authority (scaffolded; not in v1 path)
  ↓
Coolify → Docker → Stancl   ← infrastructure adapters (later)
```

---

## What exists today

| Step | Status |
| --- | --- |
| Shop / Deployment / Cluster | ✅ |
| ClusterAssignment + Policy | ✅ |
| **Provisioning v1 path** | ✅ **Frozen** (assign → stop) |
| ProvisioningRequest model | ✅ Scaffolding |
| Create ProvisioningRequest in flow | ❌ After v1 |
| Coolify / Stancl / DNS / first user | ❌ Adapters |

---

## Provisioning v2 (not started)

```text
Create Shop
  → Create Deployment
  → ClusterAssignment
  → Create ProvisioningRequest (Pending)
  → Orchestrator dispatches adapters
  → Orchestrator marks Completed / Failed
```

Adapters never mark Completed. Retries re-enter via ProvisioningRequest.  
See [adapter-rule-v1.md](adapter-rule-v1.md) · [provisioning-request-authority-v1.md](provisioning-request-authority-v1.md).
