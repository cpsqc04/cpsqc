# AlertaraQC — Partner API Integration Guide

> **Partner API catalog (share this):** [`/api/partner-api.php`](./partner-api.php)  
> **Five-module cheat sheet:** [`/api/PARTNER_MODULES.md`](./PARTNER_MODULES.md)  
> Example: `https://surveillance.alertaraqc.com/api/partner-api.php`

This document describes all HTTP JSON APIs used for integration between **AlertaraQC** (BPSO / Barangay San Agustin) and partner groups.

Use this guide when implementing send or receive endpoints in Incident Reporting, Emergency Response, Campaign, Disaster Preparedness, or other partner systems.

---

## Base URL

Replace with your deployed server:

```
https://your-domain.com/cpsqc-main
```

Local development example:

```
http://localhost/cpsqc-main
```

All endpoints below are relative to this base URL (e.g. `/api/patrol_requests_receive.php`).

---

## Common Conventions

| Item | Value |
|------|-------|
| Content-Type | `application/json` |
| Request body | JSON object |
| Response body | JSON object with `success` boolean |
| **Inbound auth** | **Public — no API key** |
| Outbound auth | AlertaraQC sends `X-API-Key` / `Bearer` when calling partner URLs |
| HTTP methods | Partner inbound endpoints use **POST** (lists use **GET**) |

### Standard error response

```json
{
  "success": false,
  "message": "Human-readable error description."
}
```

### HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Invalid or missing fields |
| 401 | Unauthorized (admin-only actions / outbound reference receivers) |
| 405 | Wrong HTTP method |
| 500 | Server / database error |
| 503 | Outbound partner URL/key not configured |

---

## Integration Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           AlertaraQC                                    │
├─────────────────────────────────────────────────────────────────────────┤
│  INBOUND (partners → AlertaraQC)                                        │
│    POST /api/patrol_requests_receive.php   ← Campaign, Disaster Preparedness           │
│    POST /api/cctv_requests_receive.php     ← Footage Request            │
│    POST /api/awareness_events_receive.php  ← Event List & Event Report  │
│    POST /api/complaints_status_receive.php ← Track Complaint status     │
│    POST /api/crime_analytics_alerts_receive.php     ← Crime Analytics alerts     │
├─────────────────────────────────────────────────────────────────────────┤
│  OUTBOUND (AlertaraQC → partners)                                       │
│    POST {INCIDENT_REPORTING_API_URL}       → Track Complaint (Incident Reporting) │
│    POST {INCIDENT_REPORTING_TIP_API_URL}   → Review Tip (Incident Reporting) │
│    POST {EMERGENCY_RESPONSE_API_URL}       → Emergency Response Backup  │
└─────────────────────────────────────────────────────────────────────────┘
```

Reference receive endpoints (for local testing) are included in this repo under `/api/`.

---

## Environment Variables (.env)

Inbound partner APIs on AlertaraQC are **public** (no keys). Configure outbound keys/URLs only:

| Variable | Used for |
|----------|----------|
| `INCIDENT_REPORTING_API_KEY` | Shared key when AlertaraQC calls Incident Reporting (legacy: `BLOTTER_API_KEY`) |
| `INCIDENT_REPORTING_API_URL` | AlertaraQC → Incident Reporting complaint (legacy: `BLOTTER_API_URL`) |
| `INCIDENT_REPORTING_TIP_API_URL` | AlertaraQC → Incident Reporting tip (legacy: `TIP_BLOTTER_API_URL`) |
| `CCTV_EVIDENCE_API_URL` | AlertaraQC → Incident Reporting CCTV evidence (no blotter-URL fallback) |
| `EMERGENCY_RESPONSE_API_KEY` | Emergency Response police backup (legacy: `GROUP3_API_KEY`) |
| `EMERGENCY_RESPONSE_API_URL` | AlertaraQC → Emergency Response (legacy: `GROUP3_API_URL`) |
| `INCIDENT_REPORTING_API_TIMEOUT` | Outbound timeout seconds (default 30; legacy: `BLOTTER_API_TIMEOUT`) |
| `EMERGENCY_RESPONSE_API_TIMEOUT` | Outbound timeout seconds (default 30; legacy: `GROUP3_API_TIMEOUT`) |

### Production Incident Reporting (`report.alertaraqc.com`)

| Flow | URL |
|------|-----|
| Catalog / dump | `GET https://report.alertaraqc.com/api/api.php?action=all` |
| Modules | `GET https://report.alertaraqc.com/api/api.php?action=modules` |
| Digital Blotter create | `POST https://report.alertaraqc.com/api/api.php?action=create_blotter` |
| Tips (interim) | Same `create_blotter` URL (`incident_type=Community Tip`) |
| CCTV evidence return | Not published yet — set `CCTV_EVIDENCE_API_URL` when IR provides it |
| CCTV request (inbound to us) | They call our `POST /api/cctv_requests_receive.php` |

`create_blotter` requires `complainant_name` and `incident_type`. Responses use `status: "success"` and often `blotter_no` (AlertaraQC also accepts `success: true` / `blotter_reference_id`).

### Local testing example

```env
INCIDENT_REPORTING_API_KEY=test-incident-reporting-key
INCIDENT_REPORTING_API_URL=http://localhost/cpsqc-main/api/blotter_receive.php
INCIDENT_REPORTING_TIP_API_URL=http://localhost/cpsqc-main/api/tip_incident_receive.php
CCTV_EVIDENCE_API_URL=http://localhost/cpsqc-main/api/cctv_evidence_receive.php

EMERGENCY_RESPONSE_API_KEY=test-emergency-response-key
EMERGENCY_RESPONSE_API_URL=http://localhost/cpsqc-main/api/coordination_receive.php
```

---

# Part A — Inbound APIs (Partners send TO AlertaraQC)

> All Part A endpoints are **public** — send JSON with `Content-Type: application/json` only.
---

## A1. Patrol Request — Campaign & Disaster Preparedness

Submit an event patrol request from Awareness/Outreach (Campaign) or Community Events (Disaster Preparedness).

| | |
|---|---|
| **Endpoint** | `POST /api/patrol_requests_receive.php` |
| **Auth** | Public (no API key) |
| **Allowed source groups** | `campaign`, `disaster-preparedness` |
| **Generated ID format** | `PT-REQ-YYYY-###` (e.g. `PT-REQ-2026-001`) |

### Request headers

```
Content-Type: application/json
```

### Request body

```json
{
  "source": "partner_api",
  "source_group": "campaign",
  "source_reference_id": "EVT-G6-2026-014",
  "requesting_unit": "Awareness and Outreach Event Tracking",
  "contact_person": "Maria Clara Santos",
  "contact_position": "Event Coordinator",
  "contact_number": "09171234567",
  "contact_email": "m.santos@barangay-sanagustin.gov.ph",
  "event_name": "Barangay Safety & Disaster Preparedness Seminar",
  "event_date": "2026-07-18",
  "event_start_time": "08:00",
  "event_end_time": "12:00",
  "event_location": "Barangay San Agustin Covered Court, Quezon City",
  "patrols_needed": 3,
  "event_description": "Half-day seminar for residents on fire safety and community watch.",
  "special_instructions": "Patrol needed at main entrance and parking area during registration."
}
```

### Field reference

| Field | Required | Notes |
|-------|----------|-------|
| `source_group` | Yes | `campaign` or `disaster-preparedness` (also accepts legacy `group_6` / `group_8`) |
| `requesting_unit` | Yes | Organization or unit name |
| `contact_person` | Yes | |
| `contact_number` | Yes | |
| `event_name` | Yes | |
| `event_date` | Yes | `YYYY-MM-DD` |
| `event_start_time` | Yes | `HH:MM` or `HH:MM:SS` |
| `event_location` | Yes | |
| `patrols_needed` | Yes | Integer ≥ 1 |
| `source` | No | Default: `partner_api` |
| `source_reference_id` | No | Partner's own event/reference ID |
| `contact_position` | No | |
| `contact_email` | No | |
| `event_end_time` | No | Must be after `event_start_time` if provided |
| `event_description` | No | |
| `special_instructions` | No | |

### Success response (HTTP 200)

```json
{
  "success": true,
  "message": "Patrol request received.",
  "data": {
    "request_id": "PT-REQ-2026-001"
  }
}
```

### GET — List patrol requests

Browse submitted patrol requests in the browser or via API client.

| | |
|---|---|
| **Endpoint** | `GET /api/patrol_requests.php` |
| **Auth** | Public (no API key) |
| **Pretty print** | Compact JSON by default; use browser Pretty-print checkbox or `?pretty=1` |

**Query parameters (all optional):**

| Parameter | Description |
|-----------|-------------|
| `request_id` | Filter by request ID (e.g. `PT-REQ-2026-001`) |
| `status` | Filter by status (`Pending`, `Approved`, `Scheduled`, etc.) |
| `source_group` | Filter by `campaign` or `disaster-preparedness` |
| `source_reference_id` | Filter by partner reference ID |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser example:**

```
http://localhost/cpsqc-main/api/patrol_requests.php
```

**Success response (HTTP 200):**

```json
{
  "success": true,
  "count": 2,
  "data": [
    {
      "id": 1,
      "request_id": "PT-REQ-2026-001",
      "source_group": "campaign",
      "source_group_label": "Campaign",
      "event_name": "Barangay Safety & Disaster Preparedness Seminar",
      "event_date": "2026-07-18",
      "event_start_time": "08:00:00",
      "event_location": "Barangay San Agustin Covered Court, Quezon City",
      "patrols_needed": 3,
      "patrols_assigned": 2,
      "status": "Under Review",
      "assigned_patrol_ids": [1, 5],
      "assigned_personnel": [
        {
          "id": 1,
          "bpso_personnel_id": "PLR-01",
          "personnel_name": "Maeren Marto",
          "status": "Available"
        }
      ],
      "submitted_at": "2026-07-08 09:15:00"
    }
  ]
}
```

### cURL example

```bash
curl -X POST "http://localhost/cpsqc-main/api/patrol_requests_receive.php" \
  -H "Content-Type: application/json" \
  -d '{
    "source_group": "disaster-preparedness",
    "requesting_unit": "Community Events Office",
    "contact_person": "Juan Miguel Reyes",
    "contact_number": "09181234567",
    "event_name": "Neighborhood Clean-Up & Tree Planting Drive",
    "event_date": "2026-07-20",
    "event_start_time": "06:00",
    "event_end_time": "10:00",
    "event_location": "San Agustin Street to Quezon Avenue",
    "patrols_needed": 2,
    "event_description": "Community clean-up and tree planting activity.",
    "special_instructions": "Early-morning shift; patrol at assembly point by 5:45 AM."
  }'
```

---

## A2. CCTV Footage Request — Partner Agencies

Submit a CCTV footage request from an external agency or partner system.

| | |
|---|---|
| **Endpoint** | `POST /api/cctv_requests_receive.php` |
| **Auth** | Public (no API key) |
| **Generated ID format** | `CCTV-REQ-YYYY-###` (e.g. `CCTV-REQ-2026-001`) |

### Request headers

```
Content-Type: application/json
```

### Request body

```json
{
  "source": "partner_api",
  "source_reference_id": "CASE-2026-045",
  "requesting_agency": "Barangay San Agustin Legal Office",
  "contact_person": "Atty. Rosa Dela Cruz",
  "contact_position": "Barangay Legal Officer",
  "contact_number": "09181234567",
  "contact_email": "legal@barangay-sanagustin.gov.ph",
  "office_unit": "Legal Affairs",
  "case_reference": "CASE-2026-045",
  "related_complaint_id": "COMP-2026-362",
  "purpose": "Investigation",
  "purpose_details": "Requesting footage related to a reported disturbance on Heavenly Drive.",
  "legal_basis": "Barangay ordinance / community safety investigation",
  "incident_location": "Heavenly Drive, Brgy. San Agustin, Quezon City",
  "camera_id": "CAM-01",
  "location_description": "Near Heavenly Drive corner",
  "incident_date": "2026-07-09",
  "footage_start_time": "18:00",
  "footage_end_time": "19:30",
  "incident_type": "Disturbance",
  "incident_description": "Reported youth riot near Heavenly Drive.",
  "delivery_method": "secure_download",
  "supporting_document": null
}
```

### Field reference

| Field | Required | Notes |
|-------|----------|-------|
| `requesting_agency` | Yes | |
| `contact_person` | Yes | |
| `contact_number` | Yes | |
| `purpose_details` | Yes | Reason for footage request |
| `legal_basis` | Yes | |
| `incident_location` | Yes | |
| `incident_date` | Yes | `YYYY-MM-DD` |
| `footage_start_time` | Yes | `HH:MM` or `HH:MM:SS` |
| `footage_end_time` | Yes | Must be after start time |
| `incident_description` | Yes | |
| `camera_id` **or** `location_description` | One required | At least one must be provided |
| `source` | No | Default: `partner_api` |
| `source_reference_id` | No | Partner case/reference ID |
| `contact_position` | No | |
| `contact_email` | No | |
| `office_unit` | No | |
| `case_reference` | No | |
| `related_complaint_id` | No | AlertaraQC complaint ID if linked |
| `purpose` | No | Default: `General request` |
| `incident_type` | No | |
| `delivery_method` | No | Default: `secure_download` |
| `supporting_document` | No | Base64 or URL if applicable |

### Success response (HTTP 200)

```json
{
  "success": true,
  "message": "CCTV footage request received.",
  "data": {
    "request_id": "CCTV-REQ-2026-001"
  }
}
```

### GET — List CCTV requests

| | |
|---|---|
| **Endpoint** | `GET /api/cctv_requests.php` |
| **Auth** | Public (no API key) |
| **Pretty print** | Compact JSON by default; use browser Pretty-print checkbox or `?pretty=1` |

**Query parameters (all optional):**

| Parameter | Description |
|-----------|-------------|
| `request_id` | Filter by request ID (e.g. `CCTV-REQ-2026-001`) |
| `status` | Filter by status (`Pending`, `Approved`, `Fulfilled`, etc.) |
| `source_reference_id` | Filter by partner reference ID |
| `requesting_agency` | Partial match on agency name |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser example:**

```
http://localhost/cpsqc-main/api/cctv_requests.php
```

**Success response (HTTP 200):**

```json
{
  "success": true,
  "count": 1,
  "data": [
    {
      "id": 1,
      "request_id": "CCTV-REQ-2026-001",
      "requesting_agency": "Barangay San Agustin Legal Office",
      "contact_person": "Atty. Rosa Dela Cruz",
      "incident_location": "Heavenly Drive, Brgy. San Agustin, Quezon City",
      "incident_date": "2026-07-09",
      "footage_start_time": "18:00:00",
      "footage_end_time": "19:30:00",
      "status": "Pending",
      "has_supporting_document": false,
      "submitted_at": "2026-07-09 14:30:00"
    }
  ]
}
```

### cURL example

```bash
curl -X POST "http://localhost/cpsqc-main/api/cctv_requests_receive.php" \
  -H "Content-Type: application/json" \
  -d '{
    "requesting_agency": "Barangay San Agustin Legal Office",
    "contact_person": "Atty. Rosa Dela Cruz",
    "contact_number": "09181234567",
    "purpose_details": "Investigation of reported disturbance.",
    "legal_basis": "Community safety investigation",
    "incident_location": "Heavenly Drive, Brgy. San Agustin, Quezon City",
    "camera_id": "CAM-01",
    "incident_date": "2026-07-09",
    "footage_start_time": "18:00",
    "footage_end_time": "19:30",
    "incident_description": "Reported youth riot near Heavenly Drive."
  }'
```

### Forward CCTV evidence to Incident Reporting (AlertaraQC → Incident Reporting)

When a CCTV request is approved, BPSO admin can send matching recording segments to **Incident Reporting** for evidence storage and case linking.

| | |
|---|---|
| **Admin trigger** | `POST /api/send_cctv_to_incident_reporting.php` (admin session) |
| **Outbound URL** | `CCTV_EVIDENCE_API_URL` (required; no blotter-URL fallback) |
| **API key** | `INCIDENT_REPORTING_API_KEY` |
| **Reference receiver (local test)** | `POST /api/cctv_evidence_receive.php` |
| **Partner download** | `GET /api/cctv_evidence_download.php?request_id=...&file=...&api_key=...` |

**Admin request:**

```json
{ "id": 1 }
```

or

```json
{ "request_id": "CCTV-REQ-2026-001" }
```

**Outbound payload (summary):**

```json
{
  "source": "alertaraqc",
  "record_type": "cctv_evidence",
  "source_request_id": "CCTV-REQ-2026-001",
  "cctv_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY",
  "video_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY",
  "request": {
    "requesting_agency": "Barangay San Agustin Legal Office",
    "case_reference": "CASE-2026-014",
    "incident_date": "2026-07-11",
    "camera_id": "CAM-001",
    "footage_window": {
      "requested_start": "15:40:00",
      "requested_end": "16:20:00",
      "actual_start": "15:40:00",
      "actual_end": "16:20:00"
    }
  },
  "footage": {
    "segment_count": 1,
    "total_size_bytes": 52428800,
    "cctv_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?...",
    "video_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?...",
    "segments": [
      {
        "filename": "recording_20260711_151655.mp4",
        "download_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY",
        "cctv_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY",
        "video_url": "https://policy.alertaraqc.com/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY"
      }
    ]
  }
}
```

**Expected Incident Reporting response:**

```json
{
  "success": true,
  "evidence_reference_id": "EVD-2026-A1B2C3",
  "message": "CCTV evidence received and logged for incident review."
}
```

On success, the request is marked **Fulfilled** and linked with `incident_reporting_evidence_reference_id`.

**Local `.env` example:**

```
INCIDENT_REPORTING_API_URL=http://localhost/cpsqc-main/api/cctv_evidence_receive.php
INCIDENT_REPORTING_API_KEY=your-shared-key
CCTV_EVIDENCE_API_URL=http://localhost/cpsqc-main/api/cctv_evidence_receive.php
```

---

## A3. Awareness Events & Reports — Campaign

Submit scheduled awareness/outreach events and post-event reports from Campaign (Impact Monitoring and Evaluation / Awareness module). These appear in the BPSO Admin **Event List** and **Event Reports** pages.

| | |
|---|---|
| **Endpoint** | `POST /api/awareness_events_receive.php` |
| **Auth** | Public (no API key) |
| **Allowed source groups** | `campaign` |
| **Record types** | `event` (scheduled event) or `report` (post-event summary) |
| **Generated ID formats** | `EVT-YYYY-###` (events), `EVT-RPT-YYYY-###` (reports) |

### Request headers

```
Content-Type: application/json
```

### Submit scheduled event (`record_type: "event"`)

```json
{
  "record_type": "event",
  "source": "partner_api",
  "source_group": "campaign",
  "source_reference_id": "G6-EVT-2026-014",
  "event_name": "Community Safety Awareness",
  "event_date": "2026-07-25",
  "event_time": "09:00",
  "organizer": "Maria Santos",
  "event_type": "Awareness",
  "venue": "Barangay San Agustin Hall",
  "status": "Scheduled",
  "description": "Half-day seminar on fire safety and community watch.",
  "contact_person": "Maria Santos",
  "contact_number": "09171234567",
  "contact_email": "m.santos@barangay-sanagustin.gov.ph"
}
```

### Event field reference

| Field | Required | Notes |
|-------|----------|-------|
| `record_type` | Yes | Must be `"event"` |
| `source_group` | Yes | `campaign` |
| `event_name` | Yes | |
| `event_date` | Yes | `YYYY-MM-DD` |
| `event_time` | Yes | `HH:MM` or `HH:MM:SS` |
| `organizer` | Yes | |
| `venue` | Yes | |
| `source` | No | Default: `partner_api` |
| `source_reference_id` | No | Partner's own reference ID |
| `event_id` | No | Auto-generated as `EVT-YYYY-###` if omitted |
| `event_type` | No | Default: `Awareness` (also `Meeting`, `Training`, etc.) |
| `status` | No | Default: `Pending` (e.g. `Scheduled`, `Completed`, `Cancelled`) |
| `description` | No | |
| `contact_person` | No | |
| `contact_number` | No | |
| `contact_email` | No | |

### Submit post-event report (`record_type: "report"`)

```json
{
  "record_type": "report",
  "source": "partner_api",
  "source_group": "campaign",
  "source_reference_id": "G6-RPT-2026-014",
  "event_id": "EVT-2026-001",
  "title": "Community Safety Awareness",
  "event_date": "2026-07-15",
  "attendance_count": 150,
  "organizer": "Maria Santos",
  "survey_result": "85% Positive",
  "location": "Barangay San Agustin Hall, Quezon City",
  "description": "Community safety awareness event conducted to educate residents about safety measures."
}
```

### Report field reference

| Field | Required | Notes |
|-------|----------|-------|
| `record_type` | Yes | Must be `"report"` |
| `source_group` | Yes | `campaign` |
| `event_id` | Yes | Links to `EVT-YYYY-###` |
| `title` | Yes | Event title |
| `event_date` | Yes | Date event was held (`YYYY-MM-DD`) |
| `attendance_count` | Yes | Integer ≥ 0 |
| `organizer` | Yes | |
| `source` | No | Default: `partner_api` |
| `source_reference_id` | No | Partner reference ID |
| `report_id` | No | Auto-generated as `EVT-RPT-YYYY-###` if omitted |
| `survey_result` | No | e.g. `85% Positive` |
| `location` | No | |
| `description` | No | |

### Success response — event (HTTP 200)

```json
{
  "success": true,
  "message": "Awareness event received.",
  "data": {
    "record_type": "event",
    "event_id": "EVT-2026-001",
    "id": 1
  }
}
```

### Success response — report (HTTP 200)

```json
{
  "success": true,
  "message": "Awareness event report received.",
  "data": {
    "record_type": "report",
    "report_id": "EVT-RPT-2026-001",
    "event_id": "EVT-2026-001",
    "id": 1
  }
}
```

### GET — List events or reports

| | |
|---|---|
| **Endpoint** | `GET /api/awareness_events.php` |
| **Auth** | Public (no API key) |

**Query parameters:**

| Parameter | Description |
|-----------|-------------|
| `record_type` | `event` (default) or `report` |
| `event_id` | Filter by `EVT-YYYY-###` |
| `report_id` | Filter reports by `EVT-RPT-YYYY-###` |
| `status` | Filter events by status |
| `event_type` | Filter events by type |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser examples:**

```
http://localhost/cpsqc-main/api/awareness_events.php?record_type=event
http://localhost/cpsqc-main/api/awareness_events.php?record_type=report
```

### cURL examples

```bash
# Submit scheduled event
curl -X POST "http://localhost/cpsqc-main/api/awareness_events_receive.php" \
  -H "Content-Type: application/json" \
  -d '{
    "record_type": "event",
    "source_group": "campaign",
    "event_name": "Community Safety Awareness",
    "event_date": "2026-07-25",
    "event_time": "09:00",
    "organizer": "Maria Santos",
    "event_type": "Awareness",
    "venue": "Barangay San Agustin Hall",
    "status": "Scheduled"
  }'

# Submit post-event report
curl -X POST "http://localhost/cpsqc-main/api/awareness_events_receive.php" \
  -H "Content-Type: application/json" \
  -d '{
    "record_type": "report",
    "source_group": "campaign",
    "event_id": "EVT-2026-001",
    "title": "Community Safety Awareness",
    "event_date": "2026-07-15",
    "attendance_count": 150,
    "organizer": "Maria Santos",
    "survey_result": "85% Positive",
    "location": "Barangay San Agustin Hall, Quezon City"
  }'
```

> **Note:** Patrol requests for event security are separate — use `POST /api/patrol_requests_receive.php` (Section A1), not this endpoint.

---

# Part B — Outbound APIs (AlertaraQC sends TO Partners)

AlertaraQC admin triggers these from the web UI. Partner groups must host equivalent receive endpoints.

---

## B1. Incident Reporting — Tip Incident Logging

Forward a reviewed community tip to Incident Reporting.

| | |
|---|---|
| **AlertaraQC sends to** | `INCIDENT_REPORTING_TIP_API_URL` (or `INCIDENT_REPORTING_API_URL` if not set) |
| **API key** | `INCIDENT_REPORTING_API_KEY` |
| **Triggered from** | Admin → Review Tip → Forward to Incident Reporting |
| **Reference endpoint (local test)** | `POST /api/tip_incident_receive.php` |

### Payload AlertaraQC sends

Tip photo evidence is included when the tip has an attached photo (compressed JPEG data URL when large).

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

### Required fields (partner must validate)

| Field | Required |
|-------|----------|
| `source_tip_id` | Yes |
| `incident.location` | Yes |
| `incident.description` | Yes |
| `has_photo` / `photo_data` / `photo_of_evidence` | No — present when tip has a photo |

### Expected partner response (HTTP 200)

```json
{
  "success": true,
  "blotter_reference_id": "INC-2026-A1B2C3",
  "message": "Tip received and logged in Incident Logging and Classification."
}
```

Accepted reference ID field names: `blotter_reference_id`, `incident_reference_id`, or `reference_id`.

---

## B2. Incident Reporting — Digital Blotter (Complaints)

Forward a complaint from AlertaraQC to Incident Reporting Digital Blotter System.

| | |
|---|---|
| **AlertaraQC sends to** | `INCIDENT_REPORTING_API_URL` |
| **API key** | `INCIDENT_REPORTING_API_KEY` |
| **Triggered from** | Admin → Track Complaint → Forward to Digital Blotter |
| **Reference endpoint (local test)** | `POST /api/blotter_receive.php` |

### Payload AlertaraQC sends

```json
{
  "source": "alertaraqc",
  "source_complaint_id": "COMP-2026-362",
  "complainant": {
    "name": "Juan Dela Cruz",
    "contact_number": "09171234567",
    "address": "Brgy. San Agustin, Quezon City"
  },
  "defendant": {
    "name": "Unknown",
    "address": "",
    "contact_number": ""
  },
  "incident": {
    "date": "2026-07-08",
    "time": "14:30",
    "location": "Heavenly Drive, Brgy. San Agustin",
    "type": "Disturbance",
    "type_other": null,
    "description": "Noise complaint and public disturbance."
  },
  "priority": "Medium",
  "notes": "Complaint submitted and awaiting review.",
  "submitted_at": "2026-07-08T10:00:00",
  "metadata": {
    "internal_id": 12,
    "forwarded_by": "alertaraqc_admin",
    "forwarded_at": "2026-07-10T00:00:00+08:00"
  }
}
```

### Required fields (partner must validate)

| Field | Required |
|-------|----------|
| `source_complaint_id` | Yes |
| `complainant.name` | Yes |
| `incident.description` | Yes |

### Expected partner response (HTTP 200)

```json
{
  "success": true,
  "blotter_reference_id": "DB-2026-A1B2C3",
  "message": "Complaint received by Digital Blotter System."
}
```

---

## B3. Emergency Response — Police Backup

Request police backup for a reviewed tip.

| | |
|---|---|
| **AlertaraQC sends to** | `EMERGENCY_RESPONSE_API_URL` |
| **API key** | `EMERGENCY_RESPONSE_API_KEY` |
| **Triggered from** | Admin → Review Tip → Request Police Backup |
| **Reference endpoint (local test)** | `POST /api/coordination_receive.php` |

### Payload AlertaraQC sends

Matches Emergency Response `anonymous_tip.php`-style fields. Tip photo is sent in `photo_of_evidence` (and aliases) when available.

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
  "contact": {
    "contact_number": null
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

### Required fields (partner must validate)

| Field | Required |
|-------|----------|
| `tip_id` or `source_tip_id` | Yes |
| `location` / `incident.location` | Yes |
| `backup.reason` | Yes (falls back to tip description if not provided in UI) |
| `photo_of_evidence` | No — included when tip has a photo |

### Expected partner response (HTTP 200)

```json
{
  "success": true,
  "coordination_reference_id": "COORD-2026-A1B2C3",
  "message": "Police backup request received by Inter-agency Coordination Portal."
}
```

---

# Part C — Quick Reference Table

| Group | Direction | Endpoint | API Key |
|-------|-----------|----------|---------|
| Campaign & Disaster Preparedness | Partner → AlertaraQC | `POST /api/patrol_requests_receive.php` | Public |
| Campaign & Disaster Preparedness | Partner → AlertaraQC (list) | `GET /api/patrol_requests.php` | Public |
| Campaign | Partner → AlertaraQC | `POST /api/awareness_events_receive.php` | Public |
| Campaign | Partner → AlertaraQC (list) | `GET /api/awareness_events.php` | Public |
| Campaign (recommendations) | AlertaraQC → Partner | Partner hosts URL (`CAMPAIGN_RECOMMENDATION_API_URL`) | `CAMPAIGN_API_KEY` (optional) |
| CCTV partner | Partner → AlertaraQC | `POST /api/cctv_requests_receive.php` | Public |
| CCTV partner | Partner → AlertaraQC (list) | `GET /api/cctv_requests.php` | Public |
| Incident Reporting (tips) | AlertaraQC → Partner | Partner hosts URL (`INCIDENT_REPORTING_TIP_API_URL`) | `INCIDENT_REPORTING_API_KEY` |
| Incident Reporting (complaints) | AlertaraQC → Partner | Partner hosts URL (`INCIDENT_REPORTING_API_URL`) | `INCIDENT_REPORTING_API_KEY` |
| Incident Reporting (CCTV evidence) | AlertaraQC → Partner | Partner hosts URL (`CCTV_EVIDENCE_API_URL`) | `INCIDENT_REPORTING_API_KEY` |
| Incident Reporting (CCTV download) | Partner → AlertaraQC | `GET /api/cctv_evidence_download.php` | `INCIDENT_REPORTING_API_KEY` |
| Emergency Response (backup) | AlertaraQC → Partner | Partner hosts URL (`EMERGENCY_RESPONSE_API_URL`) | `EMERGENCY_RESPONSE_API_KEY` |
| Crime Analytics (alerts) | Partner → AlertaraQC | `POST /api/crime_analytics_alerts_receive.php` | Public |
| Crime Analytics (list) | Partner → AlertaraQC | `GET /api/risk_alerts.php` | Public |
| Incident Reporting (status) | Partner → AlertaraQC | `POST /api/complaints_status_receive.php` | Public |

---

# Part D — Internal Admin APIs (Not for Partners)

These endpoints require an **admin login session** (cookie-based). Do not share these as partner integration APIs.

| Endpoint | Module |
|----------|--------|
| `GET/POST /api/patrol_requests.php` | Patrol Request (admin manage) |
| `GET/POST /api/awareness_events.php` | Awareness Events & Reports |
| `POST /api/send_to_campaign.php` | Forward youth patrol reports → Campaign recommendation |
| `GET/POST /api/cctv_requests.php` | CCTV Request (admin manage) |
| `GET/POST /api/complaints.php` | Complaints |
| `GET/POST /api/tips.php` | Anonymous Tips |
| `GET/POST /api/patrols.php` | BPSO Personnel |
| `GET/POST /api/patrol_schedules.php` | Patrol Schedule |
| `GET /api/patrol_logs.php` | Patrol Logs |
| `GET/POST /api/neighborhood-watcher-incidents.php` | NW Incidents |
| `GET/POST /api/neighborhood-watcher-members.php` | NW Applications / members |
| `GET/POST /api/volunteers.php` | Legacy alias for `neighborhood-watcher-members.php` |
| `GET/POST /api/users.php` | User Management |
| `GET /api/dashboard.php` | Dashboard stats |
| `GET /api/notifications.php` | Admin notifications |
| `POST /api/send_to_incident_reporting.php` | Internal forward trigger (tips) |
| `POST /api/send_cctv_to_incident_reporting.php` | Internal forward trigger (CCTV evidence) |
| `POST /api/send_to_emergency_response.php` | Internal forward trigger (backup) |

BPSO and NW portal APIs (`bpso_*`, `nw_*`) also require their respective portal sessions.

---

# Part E — Source Files in This Repository

| File | Purpose |
|------|---------|
| `api/patrol_requests_receive.php` | Inbound patrol requests |
| `api/awareness_events_receive.php` | Inbound Campaign awareness events & reports |
| `api/awareness_events.php` | List/manage awareness events & reports |
| `api/cctv_requests_receive.php` | Inbound CCTV requests |
| `api/cctv_evidence_receive.php` | Reference Incident Reporting CCTV evidence receiver |
| `api/cctv_evidence_download.php` | Partner download for forwarded CCTV files |
| `includes/cctv_forward.php` | Outbound CCTV evidence payload builder |
| `api/tip_incident_receive.php` | Reference Incident Reporting tip receiver |
| `api/blotter_receive.php` | Reference Incident Reporting blotter receiver |
| `api/coordination_receive.php` | Reference Emergency Response coordination receiver |
| `includes/tip_forward.php` | Outbound tip payload builder |
| `includes/blotter_forward.php` | Outbound complaint payload builder |
| `includes/emergency_response_forward.php` | Outbound police backup payload builder |
| `api/TIP_PARTNER_INTEGRATION.md` | Legacy tip-only doc (see this file for full guide) |

---

*Last updated: July 2026 — AlertaraQC / CPSQC*

## A1b. Patrol Request Lifecycle — Disaster Preparedness / Campaign

Partners call this when a linked simulation **starts** or **completes**.

| Item | Value |
|------|-------|
| **URL** | `POST /api/patrol_requests_lifecycle.php` |
| **Auth** | `X-API-Key` / Bearer = `PATROL_REQUEST_API_KEY` |

### Actions

| action | Effect |
|--------|--------|
| `start_simulation` | Assigned personnel → **On Patrol** |
| `complete_simulation` | Assigned personnel → **Available**; request → **Completed** |

### Example

```json
{
  "action": "complete_simulation",
  "source_reference_id": "simulation-event:4",
  "source_group": "disaster-preparedness"
}
```
