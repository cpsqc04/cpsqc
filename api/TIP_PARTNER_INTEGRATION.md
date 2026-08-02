# Tip Partner API Integration

> **See also:** [`API_INTEGRATION.md`](./API_INTEGRATION.md) for the complete partner API guide including Patrol Request (Campaign / Disaster Preparedness), CCTV Request, Tips, Digital Blotter, and Emergency Response coordination.

AlertaraQC forwards BPSO-reviewed community tips to partner systems via HTTP JSON APIs.

## Outbound (AlertaraQC → Partner)

Configured in `.env`:

| Variable | Group | Purpose |
|----------|-------|---------|
| `INCIDENT_REPORTING_TIP_API_URL` | Incident Reporting | Tip incident logging endpoint (falls back to `INCIDENT_REPORTING_API_URL`) |
| `INCIDENT_REPORTING_API_KEY` | Incident Reporting | Shared API key |
| `EMERGENCY_RESPONSE_API_URL` | Emergency Response | Police backup / coordination endpoint |
| `EMERGENCY_RESPONSE_API_KEY` | Emergency Response | Shared API key |

Admin triggers from **Review Tip → Action → Execute Actions**.

### Local testing

```env
INCIDENT_REPORTING_API_KEY=test-incident-reporting-key
INCIDENT_REPORTING_TIP_API_URL=http://localhost/cpsqc-main/api/tip_incident_receive.php

EMERGENCY_RESPONSE_API_KEY=test-emergency-response-key
EMERGENCY_RESPONSE_API_URL=http://localhost/cpsqc-main/api/coordination_receive.php
```

---

## Incident Reporting

**Reference receive endpoint:** `POST /api/tip_incident_receive.php`

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: {INCIDENT_REPORTING_API_KEY}` or `Authorization: Bearer {INCIDENT_REPORTING_API_KEY}`

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

## Emergency Response (Police Backup)

**Reference receive endpoint:** `POST /api/coordination_receive.php`

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: {EMERGENCY_RESPONSE_API_KEY}` or `Authorization: Bearer {EMERGENCY_RESPONSE_API_KEY}`

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
| `/api/send_to_incident_reporting.php` | POST | `{ "id": 1 }` or `{ "tip_id": "TIP-2026-002" }` |
| `/api/send_to_emergency_response.php` | POST | `{ "id": 1, "police_backup_reason": "..." }` |

Requires admin session. Returns reference IDs and updates the `tips` table.

---

## Related complaint API (Incident Reporting)

Complaints use a separate payload via `includes/blotter_forward.php` and reference endpoint `POST /api/blotter_receive.php`.
