# AlertaraQC — Partner API Integration Guide

> **Live API catalog (JSON):** open [`/api/integration.php`](./integration.php) in your browser — check **Pretty-print** for readable formatting  
> Example: `http://localhost/cpsqc-main/api/integration.php`

This document describes all HTTP JSON APIs used for integration between **AlertaraQC** (BPSO / Barangay San Agustin) and partner groups.

Use this guide when implementing send or receive endpoints in Group 1, Group 3, Group 6, Group 8, or other partner systems.

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
| Authentication | `X-API-Key: {key}` **or** `Authorization: Bearer {key}` |
| HTTP methods | Partner inbound endpoints use **POST** only |

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
| 401 | Invalid or missing API key |
| 405 | Wrong HTTP method |
| 500 | Server / database error |
| 503 | API key not configured on server |

---

## Integration Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           AlertaraQC                                    │
├─────────────────────────────────────────────────────────────────────────┤
│  INBOUND (partners → AlertaraQC)                                        │
│    POST /api/patrol_requests_receive.php   ← Group 6, Group 8           │
│    POST /api/cctv_requests_receive.php     ← CCTV / partner agencies    │
│    POST /api/awareness_events_receive.php  ← Group 6 (events & reports) │
├─────────────────────────────────────────────────────────────────────────┤
│  OUTBOUND (AlertaraQC → partners)                                       │
│    POST {BLOTTER_API_URL}                  → Group 1 Digital Blotter    │
│    POST {TIP_BLOTTER_API_URL}              → Group 1 Tip Incident Log   │
│    POST {GROUP3_API_URL}                   → Group 3 Police Backup      │
└─────────────────────────────────────────────────────────────────────────┘
```

Reference receive endpoints (for local testing) are included in this repo under `/api/`.

---

## Environment Variables (.env)

| Variable | Used for |
|----------|----------|
| `PATROL_REQUEST_API_KEY` | Group 6 & 8 → patrol request inbound |
| `AWARENESS_EVENTS_API_KEY` | Group 6 → awareness events & post-event reports |
| `CCTV_REQUEST_API_KEY` | Partner → CCTV request inbound |
| `BLOTTER_API_KEY` | Shared key for Group 1 tip + complaint APIs |
| `BLOTTER_API_URL` | AlertaraQC → Group 1 complaint (Digital Blotter) |
| `TIP_BLOTTER_API_URL` | AlertaraQC → Group 1 tip incident (falls back to `BLOTTER_API_URL`) |
| `GROUP3_API_KEY` | Group 3 coordination / police backup |
| `GROUP3_API_URL` | AlertaraQC → Group 3 coordination portal |
| `BLOTTER_API_TIMEOUT` | Outbound timeout seconds (default 30) |
| `GROUP3_API_TIMEOUT` | Outbound timeout seconds (default 30) |

### Local testing example

```env
PATROL_REQUEST_API_KEY=test-patrol-key
AWARENESS_EVENTS_API_KEY=test-awareness-key
CCTV_REQUEST_API_KEY=test-cctv-key

BLOTTER_API_KEY=test-group1-key
BLOTTER_API_URL=http://localhost/cpsqc-main/api/blotter_receive.php
TIP_BLOTTER_API_URL=http://localhost/cpsqc-main/api/tip_incident_receive.php

GROUP3_API_KEY=test-group3-key
GROUP3_API_URL=http://localhost/cpsqc-main/api/coordination_receive.php
```

---

# Part A — Inbound APIs (Partners send TO AlertaraQC)

---

## A1. Patrol Request — Group 6 & Group 8

Submit an event patrol request from Awareness/Outreach (Group 6) or Community Events (Group 8).

| | |
|---|---|
| **Endpoint** | `POST /api/patrol_requests_receive.php` |
| **API key** | `PATROL_REQUEST_API_KEY` |
| **Allowed source groups** | `group_6`, `group_8` |
| **Generated ID format** | `PT-REQ-YYYY-###` (e.g. `PT-REQ-2026-001`) |

### Request headers

```
Content-Type: application/json
X-API-Key: {PATROL_REQUEST_API_KEY}
```

### Request body

```json
{
  "source": "partner_api",
  "source_group": "group_6",
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
| `source_group` | Yes | `group_6` or `group_8` (also accepts `group 6` / `group 8`) |
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
| **API key** | `PATROL_REQUEST_API_KEY` (header or `?api_key=` query for browser testing) |
| **Pretty print** | Compact JSON by default; use browser Pretty-print checkbox or `?pretty=1` |

**Query parameters (all optional):**

| Parameter | Description |
|-----------|-------------|
| `request_id` | Filter by request ID (e.g. `PT-REQ-2026-001`) |
| `status` | Filter by status (`Pending`, `Approved`, `Scheduled`, etc.) |
| `source_group` | Filter by `group_6` or `group_8` |
| `source_reference_id` | Filter by partner reference ID |
| `api_key` | API key for browser access (GET only) |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser example:**

```
http://localhost/cpsqc-main/api/patrol_requests.php?api_key=YOUR_KEY
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
      "source_group": "group_6",
      "source_group_label": "Group 6",
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
  -H "X-API-Key: test-patrol-key" \
  -d '{
    "source_group": "group_8",
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
| **API key** | `CCTV_REQUEST_API_KEY` |
| **Generated ID format** | `CCTV-REQ-YYYY-###` (e.g. `CCTV-REQ-2026-001`) |

### Request headers

```
Content-Type: application/json
X-API-Key: {CCTV_REQUEST_API_KEY}
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
| **API key** | `CCTV_REQUEST_API_KEY` (header or `?api_key=` query for browser testing) |
| **Pretty print** | Compact JSON by default; use browser Pretty-print checkbox or `?pretty=1` |

**Query parameters (all optional):**

| Parameter | Description |
|-----------|-------------|
| `request_id` | Filter by request ID (e.g. `CCTV-REQ-2026-001`) |
| `status` | Filter by status (`Pending`, `Approved`, `Fulfilled`, etc.) |
| `source_reference_id` | Filter by partner reference ID |
| `requesting_agency` | Partial match on agency name |
| `api_key` | API key for browser access (GET only) |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser example:**

```
http://localhost/cpsqc-main/api/cctv_requests.php?api_key=YOUR_KEY
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
  -H "X-API-Key: test-cctv-key" \
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

### Forward CCTV evidence to Group 1 (AlertaraQC → Group 1)

When a CCTV request is approved, BPSO admin can send matching recording segments to **Group 1 Incident Logging and Classification** for evidence storage and case linking.

| | |
|---|---|
| **Admin trigger** | `POST /api/send_cctv_to_group1.php` (admin session) |
| **Outbound URL** | `CCTV_EVIDENCE_API_URL` (falls back to `BLOTTER_API_URL`) |
| **API key** | `BLOTTER_API_KEY` |
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
    "segments": [
      {
        "filename": "recording_20260711_151655.mp4",
        "download_url": "http://localhost/cpsqc-main/api/cctv_evidence_download.php?request_id=CCTV-REQ-2026-001&file=recording_20260711_151655.mp4&api_key=YOUR_KEY"
      }
    ]
  }
}
```

**Expected Group 1 response:**

```json
{
  "success": true,
  "evidence_reference_id": "EVD-2026-A1B2C3",
  "message": "CCTV evidence received and logged for incident review."
}
```

On success, the request is marked **Fulfilled** and linked with `group1_evidence_reference_id`.

**Local `.env` example:**

```
BLOTTER_API_URL=http://localhost/cpsqc-main/api/cctv_evidence_receive.php
BLOTTER_API_KEY=your-shared-key
CCTV_EVIDENCE_API_URL=http://localhost/cpsqc-main/api/cctv_evidence_receive.php
```

---

## A3. Awareness Events & Reports — Group 6

Submit scheduled awareness/outreach events and post-event reports from Group 6 (Impact Monitoring and Evaluation / Awareness module). These appear in the BPSO Admin **Event List** and **Event Reports** pages.

| | |
|---|---|
| **Endpoint** | `POST /api/awareness_events_receive.php` |
| **API key** | `AWARENESS_EVENTS_API_KEY` |
| **Allowed source groups** | `group_6` |
| **Record types** | `event` (scheduled event) or `report` (post-event summary) |
| **Generated ID formats** | `EVT-YYYY-###` (events), `EVT-RPT-YYYY-###` (reports) |

### Request headers

```
Content-Type: application/json
X-API-Key: {AWARENESS_EVENTS_API_KEY}
```

### Submit scheduled event (`record_type: "event"`)

```json
{
  "record_type": "event",
  "source": "partner_api",
  "source_group": "group_6",
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
| `source_group` | Yes | `group_6` |
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
  "source_group": "group_6",
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
| `source_group` | Yes | `group_6` |
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
| **API key** | `AWARENESS_EVENTS_API_KEY` (header or `?api_key=` for browser testing) |
| **Admin session** | Logged-in BPSO admin can also browse without API key |

**Query parameters:**

| Parameter | Description |
|-----------|-------------|
| `record_type` | `event` (default) or `report` |
| `event_id` | Filter by `EVT-YYYY-###` |
| `report_id` | Filter reports by `EVT-RPT-YYYY-###` |
| `status` | Filter events by status |
| `event_type` | Filter events by type |
| `api_key` | API key for browser access (GET only) |
| `pretty` | `1` for server-side pretty-print (optional) |

**Browser examples:**

```
http://localhost/cpsqc-main/api/awareness_events.php?record_type=event&api_key=YOUR_KEY
http://localhost/cpsqc-main/api/awareness_events.php?record_type=report&api_key=YOUR_KEY
```

### cURL examples

```bash
# Submit scheduled event
curl -X POST "http://localhost/cpsqc-main/api/awareness_events_receive.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: test-awareness-key" \
  -d '{
    "record_type": "event",
    "source_group": "group_6",
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
  -H "X-API-Key: test-awareness-key" \
  -d '{
    "record_type": "report",
    "source_group": "group_6",
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

## B1. Group 1 — Tip Incident Logging

Forward a reviewed community tip to Group 1 Incident Logging and Classification.

| | |
|---|---|
| **AlertaraQC sends to** | `TIP_BLOTTER_API_URL` (or `BLOTTER_API_URL` if not set) |
| **API key** | `BLOTTER_API_KEY` |
| **Triggered from** | Admin → Review Tip → Forward to Group 1 |
| **Reference endpoint (local test)** | `POST /api/tip_incident_receive.php` |

### Payload AlertaraQC sends

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

### Required fields (partner must validate)

| Field | Required |
|-------|----------|
| `source_tip_id` | Yes |
| `incident.location` | Yes |
| `incident.description` | Yes |

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

## B2. Group 1 — Digital Blotter (Complaints)

Forward a complaint from AlertaraQC to Group 1 Digital Blotter System.

| | |
|---|---|
| **AlertaraQC sends to** | `BLOTTER_API_URL` |
| **API key** | `BLOTTER_API_KEY` |
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

## B3. Group 3 — Inter-agency Coordination (Police Backup)

Request police backup for a reviewed tip.

| | |
|---|---|
| **AlertaraQC sends to** | `GROUP3_API_URL` |
| **API key** | `GROUP3_API_KEY` |
| **Triggered from** | Admin → Review Tip → Request Police Backup |
| **Reference endpoint (local test)** | `POST /api/coordination_receive.php` |

### Payload AlertaraQC sends

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

### Required fields (partner must validate)

| Field | Required |
|-------|----------|
| `source_tip_id` | Yes |
| `incident.location` | Yes |
| `backup.reason` | Yes (falls back to tip description if not provided in UI) |

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
| Group 6 & 8 | Partner → AlertaraQC | `POST /api/patrol_requests_receive.php` | `PATROL_REQUEST_API_KEY` |
| Group 6 & 8 | Partner → AlertaraQC (list) | `GET /api/patrol_requests.php` | `PATROL_REQUEST_API_KEY` |
| Group 6 | Partner → AlertaraQC | `POST /api/awareness_events_receive.php` | `AWARENESS_EVENTS_API_KEY` |
| Group 6 | Partner → AlertaraQC (list) | `GET /api/awareness_events.php` | `AWARENESS_EVENTS_API_KEY` |
| CCTV partner | Partner → AlertaraQC | `POST /api/cctv_requests_receive.php` | `CCTV_REQUEST_API_KEY` |
| CCTV partner | Partner → AlertaraQC (list) | `GET /api/cctv_requests.php` | `CCTV_REQUEST_API_KEY` |
| Group 1 (tips) | AlertaraQC → Partner | Partner hosts URL (`TIP_BLOTTER_API_URL`) | `BLOTTER_API_KEY` |
| Group 1 (complaints) | AlertaraQC → Partner | Partner hosts URL (`BLOTTER_API_URL`) | `BLOTTER_API_KEY` |
| Group 1 (CCTV evidence) | AlertaraQC → Partner | Partner hosts URL (`CCTV_EVIDENCE_API_URL` or `BLOTTER_API_URL`) | `BLOTTER_API_KEY` |
| Group 1 (CCTV download) | Partner → AlertaraQC | `GET /api/cctv_evidence_download.php` | `BLOTTER_API_KEY` |
| Group 3 (backup) | AlertaraQC → Partner | Partner hosts URL (`GROUP3_API_URL`) | `GROUP3_API_KEY` |

---

# Part D — Internal Admin APIs (Not for Partners)

These endpoints require an **admin login session** (cookie-based). Do not share these as partner integration APIs.

| Endpoint | Module |
|----------|--------|
| `GET/POST /api/patrol_requests.php` | Patrol Request (admin manage) |
| `GET/POST /api/awareness_events.php` | Awareness Events & Reports |
| `GET/POST /api/cctv_requests.php` | CCTV Request (admin manage) |
| `GET/POST /api/complaints.php` | Complaints |
| `GET/POST /api/tips.php` | Anonymous Tips |
| `GET/POST /api/patrols.php` | BPSO Personnel |
| `GET/POST /api/patrol_schedules.php` | Patrol Schedule |
| `GET /api/patrol_logs.php` | Patrol Logs |
| `GET/POST /api/nw_incidents.php` | NW Incidents |
| `GET/POST /api/nw_members.php` | NW Applications / members |
| `GET/POST /api/volunteers.php` | Legacy alias for `nw_members.php` |
| `GET/POST /api/users.php` | User Management |
| `GET /api/dashboard.php` | Dashboard stats |
| `GET /api/notifications.php` | Admin notifications |
| `POST /api/send_to_group1.php` | Internal forward trigger (tips) |
| `POST /api/send_cctv_to_group1.php` | Internal forward trigger (CCTV evidence) |
| `POST /api/send_to_group3.php` | Internal forward trigger (backup) |

BPSO and NW portal APIs (`bpso_*`, `nw_*`) also require their respective portal sessions.

---

# Part E — Source Files in This Repository

| File | Purpose |
|------|---------|
| `api/patrol_requests_receive.php` | Inbound patrol requests |
| `api/awareness_events_receive.php` | Inbound Group 6 awareness events & reports |
| `api/awareness_events.php` | List/manage awareness events & reports |
| `api/cctv_requests_receive.php` | Inbound CCTV requests |
| `api/cctv_evidence_receive.php` | Reference Group 1 CCTV evidence receiver |
| `api/cctv_evidence_download.php` | Partner download for forwarded CCTV files |
| `includes/cctv_forward.php` | Outbound CCTV evidence payload builder |
| `api/tip_incident_receive.php` | Reference Group 1 tip receiver |
| `api/blotter_receive.php` | Reference Group 1 blotter receiver |
| `api/coordination_receive.php` | Reference Group 3 coordination receiver |
| `includes/tip_forward.php` | Outbound tip payload builder |
| `includes/blotter_forward.php` | Outbound complaint payload builder |
| `includes/group3_forward.php` | Outbound police backup payload builder |
| `api/TIP_PARTNER_INTEGRATION.md` | Legacy tip-only doc (see this file for full guide) |

---

*Last updated: July 2026 — AlertaraQC / CPSQC*
