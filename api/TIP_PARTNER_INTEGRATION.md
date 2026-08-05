# Tip Partner API Integration

> **See also:** [`API_INTEGRATION.md`](./API_INTEGRATION.md) for the complete partner API guide including Patrol Request (Campaign / Disaster Preparedness), CCTV Request, Tips, Digital Blotter, and Emergency Response coordination.

AlertaraQC forwards BPSO-reviewed community tips to partner systems via HTTP JSON APIs. **Tip photo evidence is included when available.**

## Outbound (AlertaraQC → Partner)

Configured in `.env`:

| Variable | Group | Purpose |
|----------|-------|---------|
| `INCIDENT_REPORTING_TIP_API_URL` | Incident Reporting | Tip incident logging endpoint (falls back to `INCIDENT_REPORTING_API_URL`) |
| `INCIDENT_REPORTING_API_KEY` | Incident Reporting | Shared API key |
| `EMERGENCY_RESPONSE_API_URL` | Emergency Response | Police backup / Inter-Agency tip endpoint |
| `EMERGENCY_RESPONSE_API_KEY` | Emergency Response | Shared API key |

Admin triggers from **Review Tip**: **Send to Incident Logging**, **Send to Inter-Agency**, or **Export**. Assign patrol separately via **Assign Patrol**.

Live catalog: `https://surveillance.alertaraqc.com/api/partner-api.php`

### Photo fields (both partners)

| Field | Description |
|-------|-------------|
| `has_photo` | `true` when the tip has an attached photo |
| `photo_data` | `data:image/jpeg;base64,...` (may be compressed for size) |
| `photo_of_evidence` | Same image — primary field for Emergency Response |
| `attached_evidence.photo_data` | Nested copy of the tip photo |

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
  "tip_id": "TIP-2026-002",
  "date_time": "2026-07-09T20:59:59+08:00",
  "location": "Heavenly Drive Brgy. San Agustin QC",
  "tip_description": "nagririot mga kabataan",
  "status": "Assigned",
  "outcome": "No Outcome Yet",
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
  "photo_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
  "photo_of_evidence": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
  "attached_evidence": {
    "type": "photo",
    "photo_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
    "available": true
  },
  "metadata": {
    "internal_id": 1,
    "forwarded_by": "alertaraqc_bpso_admin",
    "forwarded_at": "2026-07-10T00:00:00+08:00",
    "has_photo": true
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

## Emergency Response (Police Backup / Inter-Agency)

**Reference receive endpoint:** `POST /api/coordination_receive.php`  
Production partner URL typically: `anonymous_tip.php` (`EMERGENCY_RESPONSE_API_URL`)

**Headers:**
- `Content-Type: application/json`
- `X-API-Key: {EMERGENCY_RESPONSE_API_KEY}` or `Authorization: Bearer {EMERGENCY_RESPONSE_API_KEY}`

**Request body:**

```json
{
  "tip_id": "TIP-2026-002",
  "tip_datetime": "2026-07-09 20:59:59",
  "location": "Heavenly Drive Brgy. San Agustin QC",
  "tip_description": "nagririot mga kabataan\n\n[Police backup] Youth riot reported; immediate police backup needed.",
  "photo_of_evidence": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
  "photo_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
  "status": "Assigned",
  "outcome": "No Outcome Yet",
  "source_system": "alertaraqc",
  "source": "alertaraqc",
  "request_type": "police_backup",
  "source_tip_id": "TIP-2026-002",
  "requesting_agency": "BPSO - Quezon City",
  "incident": {
    "location": "Heavenly Drive Brgy. San Agustin QC",
    "description": "nagririot mga kabataan\n\n[Police backup] Youth riot reported; immediate police backup needed.",
    "submitted_at": "2026-07-09T20:59:59+08:00"
  },
  "backup": {
    "reason": "Youth riot reported; immediate police backup needed.",
    "priority": "high",
    "units_requested": "patrol"
  },
  "has_photo": true,
  "attached_evidence": {
    "type": "photo",
    "photo_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...",
    "available": true
  },
  "metadata": {
    "internal_id": 1,
    "forwarded_by": "alertaraqc_bpso_admin",
    "forwarded_at": "2026-07-10T00:00:00+08:00",
    "police_backup": true,
    "has_photo": true
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
