# ARK on a Vultr cloud server

You do **not** need to know Laravel, MySQL, Redis, or how ARK works inside.

Docker runs the stack. The browser installer configures your shop.

```text
Your Vultr server
│
├── Caddy (HTTPS)          ← optional but recommended with a domain
│       ↓
├── ARK app
│   ├── Web (nginx + PHP)
│   ├── Horizon (queues)
│   ├── Reverb (realtime / call popups)
│   └── Scheduler
│
├── MySQL
├── Redis
│
└── Persistent volumes
    ├── repair orders
    ├── inspections
    ├── photos
    └── attachments
```

## How much does the server need to cost?

ARK’s full stack is heavier than a brochure website. It runs MySQL, Redis, PHP-FPM, Horizon, Reverb, and the scheduler together.

| RAM | Use |
| --- | --- |
| **1 GB** | Supported starter/minimum for a small ARK shop. Add swap on small hosts. |
| **2 GB** | Recommended when you want extra headroom for updates, imports, photos, queues, and heavier use. |

Typical Vultr Cloud Compute pricing at the time of writing is about **$5/mo for 1 GB** and **$10/mo for 2 GB**. Use swap on 1 GB (see below).

---

## 1. Create the server

1. Sign in at [Vultr](https://www.vultr.com/).
2. **Products → Compute → Deploy Server**.
3. Choose:
   - **Server type:** Cloud Compute / Shared CPU  
   - **OS:** Ubuntu (24.04 LTS or newest LTS)  
   - **Plan:** **1 GB RAM** is enough to get a small shop running (use swap). **2 GB RAM** if you want more headroom.  
   - **Location:** closest region to your shop  
4. Add an **SSH key** if you have one (recommended).
5. Deploy. Copy the **public IPv4** address.

### Firewall (Vultr)

Allow:

| Port | Why |
| --- | --- |
| **22** | SSH |
| **80** | HTTP (Let’s Encrypt + redirect) |
| **443** | HTTPS |

If you skip the domain/HTTPS path and only use the IP for a quick look, also allow **8088** temporarily. Do **not** leave shop data on plain HTTP long-term.

---

## 2. Point a domain at the server (recommended)

In your DNS host, create:

```text
ark.yourshop.com   →   A record   →   YOUR_SERVER_IP
```

Wait until it resolves (often a few minutes):

```bash
dig +short ark.yourshop.com
```

You should see your Vultr IP.

---

## 3. Connect to the server

Mac, Linux, or Windows PowerShell:

```bash
ssh root@YOUR_SERVER_IP
```

---

## 4. Add swap (important on small plans)

This gives the server breathing room during `docker compose build`:

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -h
```

---

## 5. Install Docker

One block (Ubuntu). Paste the whole thing:

```bash
set -euo pipefail
apt-get update
apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
. /etc/os-release
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
docker --version
docker compose version
```

Both commands should print a version.

---

## 6. Download ARK

```bash
git clone https://github.com/EdwardSoaresJr/ark.git
cd ark
```

---

## 7. Start ARK (HTTPS with your domain)

```bash
export ARK_DOMAIN=ark.yourshop.com
export ARK_ADMIN_EMAIL=you@yourshop.com   # Let’s Encrypt contact

docker compose -f docker-compose.yml -f docker-compose.vultr.yml up -d --build
```

First build can take several minutes. Coffee is allowed.

### What this starts

- MySQL  
- Redis  
- ARK app (nginx, PHP-FPM, Horizon, Reverb, scheduler)  
- Caddy (automatic HTTPS via Let’s Encrypt)  
- Persistent volumes for database + shop files  

No manual MySQL install. No Composer. No Laravel `.env` homework for a normal shop.

**Public ports:** only **80** and **443** (Caddy). MySQL, Redis, and the app container stay on the private Compose network — they are not published on the VPS.

**APP_KEY and database passwords:** generated automatically on first boot and stored on durable volumes. You do **not** run `php artisan key:generate`, and you do **not** invent MySQL passwords for a normal Compose install.

---

## 8. Check it

```bash
docker compose -f docker-compose.yml -f docker-compose.vultr.yml ps
```

You want `mysql`, `redis`, `app`, and `caddy` **running** (or healthy).

If something looks wrong:

```bash
docker compose -f docker-compose.yml -f docker-compose.vultr.yml logs -f app
```

(`Ctrl+C` stops following logs; it does not stop ARK.)

---

## 9. Open the installer

Visit:

```text
https://ark.yourshop.com/setup
```

Complete:

Welcome → System → Database → Shop → Admin → Optional integrations → Install

**Database step:** for a normal Compose install, ARK should show **Database · Connected**. You should not need to type Docker database credentials.

---

## 10. Day-2 basics

**Update ARK later:**

```bash
cd ~/ark   # or wherever you cloned
git pull
docker compose -f docker-compose.yml -f docker-compose.vultr.yml up -d --build
```

Shop data stays in Docker volumes. The app image is replaceable.

**Backups (minimum):**

- Snapshot the Vultr instance periodically, **and**
- Copy volumes / `mysqldump` off-box on a schedule you trust

**Never** put Twilio, Square, OpenAI, or customer data into GitHub.

---

## Quick IP-only test (not for real shops)

If you only want to see `/setup` before DNS is ready:

```bash
cd ark
docker compose up -d --build
```

Open `http://YOUR_SERVER_IP:8088/setup` (open firewall port **8088**).

Move to the **HTTPS + domain** path before entering real customers.

---

## Troubleshooting

| Symptom | Try |
| --- | --- |
| Build killed / “no space” / random failures | Confirm swap is on (`free -h`); 1 GB hosts need the 2 GB swapfile above; `df -h`; prune old images `docker system prune -af` only if you know you can rebuild |
| HTTPS fails | DNS A record must point at this server; ports 80/443 open; wait for propagation |
| `/setup` loops or 500 | `docker compose ... logs -f app` |
| `/setup` returns 503 about APP_KEY | Rebuild/recreate after pulling a release that bootstraps APP_KEY in the Coolify entrypoint; do not hand-edit keys on a beginner install |
| Site up but popups/queues feel dead | Confirm `app` container is the production image (Horizon + Reverb in `ps aux` inside the container) |

More installer notes: [README.md](./README.md) · [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
