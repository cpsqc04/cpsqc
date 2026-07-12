# Tip Partner API Integration

> **See also:** [`API_INTEGRATION.md`](./API_INTEGRATION.md) for the complete partner API guide including Patrol Request (Group 6/8), CCTV Request, Tips, Digital Blotter, and Group 3 coordination.

AlertaraQC forwards BPSO-reviewed community tips to partner systems via HTTP JSON APIs.

## Outbound (AlertaraQC → Partner)

Configured in `.env`:

| Variable | Group | Purpose |
|----------|-------|---------|
| `TIP_BLOTTER_API_URL` | Group 1 | Tip incident logging endpoint (falls back to `BLOTTER_API_URL`) |
| `BLOTTER_API_KEY` | Group 1 | Shared API key |
| `GROUP3_API_URL` | Group 3 | Police backup / coordination endpoint |
| `GROUP3_API_KEY` | Group 3 | Shared API key |

Admin triggers from **Review Tip → Action → Execute Actions**.

### Local testing

```env
BLOTTER_API_KEY=test-group1-key
TIP_BLOTTER_API_URL=http://localhost/cpsqc-main/api/tip_incident_receive.php

GROUP3_API_KEY=test-group3-key
GROUP3_API_URL=http://localhost/cpsqc-main/api/coordination_receive.php
```

---

## Group 1 — Incident Logging and Classification

**Reference receive endpoint:** `POST /api/tip_incident_receive.php`

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: {BLOTTER_API_KEY}` or `Authorization: Bearer {BLOTTER_API_KEY}`

**Request body:**

```json
{
  "source": "alertaraqc",
  "record_type": "tip",
  "source_tip_id": "TIP-2026-002",
  "incident": {
    "location": "Heavenly Drive Brgy. San Agustin QC",
    "description": "nagririot mga kabataan",
    "submitted_at": "2026-07-09T20:59:59+08:00",
    "classification": "community_tip"
  },
  "reporter": {
    "contact_number": null,
    "anonymous": true
  },
  "has_photo": true,
  "metadata": {
    "internal_id": 1,
    "forwarded_by": "alertaraqc_bpso_admin",
    "forwarded_at": "2026-07-10T00:00:00+08:00"
  }
}
```

**Success response (HTTP 200):**

```json
{
  "success": true,
  "blotter_reference_id": "INC-2026-A1B2C3",
  "message": "Tip received and logged."
}
```

---

## Group 3 — Inter-agency Coordination Portal (Police Backup)

**Reference receive endpoint:** `POST /api/coordination_receive.php`

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: {GROUP3_API_KEY}` or `Authorization: Bearer {GROUP3_API_KEY}`

**Request body:**

```json
{
  "source": "alertaraqc",
  "request_type": "police_backup",
  "source_tip_id": "TIP-2026-002",
  "requesting_agency": "BPSO - Quezon City",
  "incident": {
    "location": "Heavenly Drive Brgy. San Agustin QC",
    "description": "nagririot mga kabataan",
    "submitted_at": "2026-07-09T20:59:59+08:00"
  },
  "backup": {
    "reason": "Youth riot reported; immediate police backup needed.",
    "priority": "high",
    "units_requested": "patrol"
  },
  "contact": {
    "contact_number": null
  },
  "has_photo": true,
  "metadata": {
    "internal_id": 1,
    "forwarded_by": "alertaraqc_bpso_admin",
    "forwarded_at": "2026-07-10T00:00:00+08:00"
  }
}
```

**Success response (HTTP 200):**

```json
{
  "success": true,
  "coordination_reference_id": "COORD-2026-A1B2C3",
  "message": "Police backup request received."
}
```

---

## AlertaraQC admin endpoints (internal)

| Endpoint | Method | Body |
|----------|--------|------|
| `/api/send_to_group1.php` | POST | `{ "id": 1 }` or `{ "tip_id": "TIP-2026-002" }` |
| `/api/send_to_group3.php` | POST | `{ "id": 1, "police_backup_reason": "..." }` |

Requires admin session. Returns reference IDs and updates the `tips` table.

---

## Related complaint API (Group 1)

Complaints use a separate payload via `includes/blotter_forward.php` and reference endpoint `POST /api/blotter_receive.php`.
