# AlertaraQC (CPSQC)

Community Policing and Safety Quality Control system for **Barangay San Agustin, Quezon City**.

AlertaraQC supports BPSO patrol scheduling and monitoring, neighborhood watch coordination, CCTV surveillance, community complaints, anonymous tips, awareness events, and partner-group API integrations.

**Repository:** [cpsqc04/cpsqc](https://github.com/cpsqc04/cpsqc)  
**Production:** [policy.alertaraqc.com](https://policy.alertaraqc.com)

---

## Features

| Module | Description |
|--------|-------------|
| **Admin dashboard** | Users, notifications, audit trails |
| **Neighborhood Watch** | Member applications, NW portal, incident reports |
| **CCTV** | Camera management, live surveillance, recordings, footage requests |
| **Complaints** | Submit and track community complaints |
| **Patrol** | BPSO personnel, attendance (time in/out), schedules, logs, event patrol requests |
| **Awareness events** | Event list and post-event reports |
| **Anonymous tips** | Tip review and forwarding to partners |
| **High-risk areas** | Crime Analytics alerts → assign extra patrol |

---

## Tech stack

- **Backend:** PHP (PDO / MySQL)
- **Frontend:** HTML, CSS, JavaScript
- **Local stack:** XAMPP (Apache + MySQL)
- **Email:** PHPMailer (Composer)
- **CCTV detection (optional):** Python (`detect.py`) on the on-site PC (same router LAN as the camera — no direct camera cable needed). Keep `start_detection_agent.bat` running. Use **Main stream** + `CCTV_FEED_QUALITY=lan` for clear live view. Camera Management **Scan LAN** finds camera IPs. Recordings stay in `recordings/` for 30 days.

---

## Local setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Place the project under:
   ```
   C:\xampp\htdocs\cpsqc-main
   ```
   Or clone:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/cpsqc04/cpsqc.git cpsqc-main
   ```
3. Create a `.env` file in the project root (do **not** commit it). Ask your lead for production values; for local use, set at least DB and mail settings. Example keys:
   ```env
   DB_HOST_LOCAL=localhost
   DB_NAME_LOCAL=LGU
   DB_USER_LOCAL=root
   DB_PASS_LOCAL=
   DB_PORT_LOCAL=3306

   MAIL_HOST=smtp.resend.com
   MAIL_PORT=465
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   MAIL_FROM_ADDRESS=no-reply@alertaraqc.com
   MAIL_FROM_NAME="AlerTaraQC"

   CRIME_ANALYTICS_API_KEY=test-crime-analytics-key
   PATROL_REQUEST_API_KEY=
   AWARENESS_EVENTS_API_KEY=
   INCIDENT_REPORTING_API_KEY=
   EMERGENCY_RESPONSE_API_KEY=
   ```
4. Install PHP dependencies (if `vendor/` is missing):
   ```bash
   composer install
   ```
5. Open in the browser:
   - Resident portal: `http://localhost/cpsqc-main/resident-portal.php`
   - Admin login: `http://localhost/cpsqc-main/login.php`
   - Partner API: `http://localhost/cpsqc-main/api/partner-api.php`

Tables are created/updated automatically by schema helpers when APIs are first used.

---

## Partner API integrations

Live Partner API catalog: **`/api/partner-api.php`**  
Full guide: **[`api/API_INTEGRATION.md`](api/API_INTEGRATION.md)**

| Direction | Partner | Endpoint / config |
|-----------|---------|-------------------|
| Inbound | Crime Analytics — high-risk alerts | `POST /api/crime_analytics_alerts_receive.php` (`CRIME_ANALYTICS_API_KEY`) |
| Inbound | Campaign / Disaster Preparedness — patrol requests | `POST /api/patrol_requests_receive.php` |
| Inbound | Campaign — awareness events | `POST /api/awareness_events_receive.php` |
| Inbound | CCTV footage requests | `POST /api/cctv_requests_receive.php` |
| Outbound | Incident Reporting — blotter / tips / CCTV evidence | `INCIDENT_REPORTING_API_URL`, `INCIDENT_REPORTING_TIP_API_URL`, `CCTV_EVIDENCE_API_URL` |
| Outbound | Emergency Response — police backup | `EMERGENCY_RESPONSE_API_URL` |

Auth for partner inbound APIs: header `X-API-Key` or `Authorization: Bearer {key}`.

### Crime Analytics (high-risk areas)

When Crime Analytics triggers a hotspot/surge rule, they POST JSON to AlertaraQC. Active alerts appear on **Patrol Schedule → High-Risk Areas**. Admins can click **Assign Patrol Here** to pre-fill route, location, and notes.

Required fields: `rule_name`, `location` (or `area_name`), `severity` (`CRITICAL` | `HIGH` | `MEDIUM` | `LOW`).

---

## Deployment (GitHub → server)

1. Commit and push to `main` on GitHub (`cpsqc04/cpsqc`).
2. On the production server (as instructed by your lead):
   ```bash
   cd /var/www/html/community_policing_alertaraqc
   git stash
   git pull
   git stash pop
   ```
3. Keep a separate production `.env` on the server (never overwrite it with local secrets).
4. Verify:
   - Site: `https://policy.alertaraqc.com`
   - Partner API: `https://policy.alertaraqc.com/api/partner-api.php`

---

## What not to commit

| Path | Why |
|------|-----|
| `.env` | Passwords and API keys |
| `recordings/` | Large CCTV video files |
| `__pycache__/` | Python cache |
| `vendor/` | Install via `composer install` (optional to commit) |
| `*.pt`, OpenH264 DLLs | Large / downloaded separately |

These are listed in `.gitignore`.

---

## Project structure (high level)

```
cpsqc-main/
├── api/                 # JSON APIs and schema helpers
├── includes/            # Shared PHP helpers (auth, forwards, nav)
├── css/, js/, images/   # Frontend assets
├── database/            # SQL reference (e.g. cpsqc.sql)
├── detect.py            # Optional CCTV detection (local)
├── login.php            # Admin login
├── patrol-attendance.php # Patrol attendance / BPSO portal
├── neighborhood-watcher-dashboard.php     # Neighborhood Watch portal
├── patrol-schedule.php  # Patrol assignments + high-risk areas
└── README.md
```

---

## Related docs

- [`api/API_INTEGRATION.md`](api/API_INTEGRATION.md) — partner API guide
- [`api/TIP_PARTNER_INTEGRATION.md`](api/TIP_PARTNER_INTEGRATION.md) — tip forwarding
- [`DEPLOYMENT_CHECKLIST.md`](DEPLOYMENT_CHECKLIST.md) — deployment notes
- [`RESTART_PHP_GUIDE.md`](RESTART_PHP_GUIDE.md) — PHP restart on server

---

## Team

Capstone CPSQC — AlertaraQC (Barangay San Agustin, Quezon City).  
For GitHub access, Hostinger/VPS credentials, and production `.env` values, contact your project lead.
