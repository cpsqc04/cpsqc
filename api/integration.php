<?php

/**
 * AlertaraQC — Full API catalog (browsable JSON).
 *
 * GET /api/integration.php
 * Prefer sharing /api/partner-api.php with partners (pretty JSON catalog).
 * Returns a JSON catalog of every API endpoint in this system.
 */

require_once __DIR__ . '/../includes/json_response.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed. Use GET.'], 405, false);
}

function integrationBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api'));
    $projectRoot = dirname($scriptDir);

    return rtrim($scheme . '://' . $host . ($projectRoot === '/' ? '' : $projectRoot), '/');
}

function endpoint(string $path, array $meta): array
{
    return array_merge(['path' => $path, 'url' => integrationBaseUrl() . $path], $meta);
}

function getIntegrationCatalog(): array
{
    $authHeader = 'X-API-Key: {key} or Authorization: Bearer {key}';

    return [
        'success' => true,
        'system' => 'AlertaraQC',
        'description' => 'Community Policing and Safety Quality Control — Barangay San Agustin, Quezon City',
        'generated_at' => date('c'),
        'base_url' => integrationBaseUrl(),
        'documentation' => [
            'partner_api' => integrationBaseUrl() . '/api/partner-api.php',
            'markdown' => integrationBaseUrl() . '/api/API_INTEGRATION.md',
            'modules_cheat_sheet' => integrationBaseUrl() . '/api/PARTNER_MODULES.md',
            'catalog' => integrationBaseUrl() . '/api/partner-api.php',
        ],
        'conventions' => [
            'content_type' => 'application/json',
            'pretty_print' => 'Compact JSON by default (like production APIs). Use browser Pretty-print checkbox, or pass ?pretty=1 for server-side formatting',
            'partner_api_key_query' => 'Inbound partner APIs are public; outbound to partners still use API keys from .env',
            'auth_types' => [
                'api_key' => $authHeader . ' (outbound / reference receivers only)',
                'admin_session' => 'PHP session cookie after admin login',
                'bpso_session' => 'PHP session cookie after BPSO personnel login',
                'nw_session' => 'PHP session cookie after NW member login',
                'public' => 'No authentication required',
            ],
        ],
        'environment_variables' => [
            'INCIDENT_REPORTING_API_KEY' => 'Outbound to Incident Reporting (legacy: BLOTTER_API_KEY)',
            'INCIDENT_REPORTING_API_URL' => 'AlertaraQC → Incident Reporting complaint forward URL (legacy: BLOTTER_API_URL)',
            'INCIDENT_REPORTING_TIP_API_URL' => 'AlertaraQC → Incident Reporting tip forward URL (legacy: TIP_BLOTTER_API_URL)',
            'EMERGENCY_RESPONSE_API_KEY' => 'Emergency Response police backup (legacy: GROUP3_API_KEY)',
            'EMERGENCY_RESPONSE_API_URL' => 'AlertaraQC → Emergency Response forward URL (legacy: GROUP3_API_URL)',
        ],
        'partner_apis' => [
            'inbound_to_alertaraqc' => [
                'description' => 'Partner groups send data INTO AlertaraQC (public — no API key)',
                'endpoints' => [
                    endpoint('/api/patrol_requests_receive.php', [
                        'name' => 'Submit Patrol Request',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'groups' => ['campaign', 'disaster-preparedness'],
                        'description' => 'Submit event patrol request from Campaign or Disaster Preparedness',
                        'sample_request' => [
                            'source_group' => 'campaign',
                            'requesting_unit' => 'Community Events Office',
                            'contact_person' => 'Juan Reyes',
                            'contact_number' => '09171234567',
                            'event_name' => 'Youth Leadership Workshop',
                            'event_date' => '2026-07-20',
                            'event_start_time' => '09:00',
                            'event_location' => 'Barangay Hall',
                            'patrols_needed' => 2,
                        ],
                        'sample_response' => [
                            'success' => true,
                            'message' => 'Patrol request received.',
                            'data' => ['request_id' => 'PT-REQ-2026-001'],
                        ],
                    ]),
                    endpoint('/api/patrol_requests.php', [
                        'name' => 'List Patrol Requests',
                        'methods' => ['GET'],
                        'auth' => 'public',
                        'description' => 'List patrol requests',
                        'query_params' => [
                            'request_id' => 'Filter by PT-REQ-YYYY-###',
                            'status' => 'Pending | Under Review | Approved | Scheduled | Rejected | Cancelled',
                            'source_group' => 'campaign | disaster-preparedness',
                            'source_reference_id' => 'Partner reference ID',
                            'pretty' => '1 for server-side pretty-print (optional)',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'count' => 1,
                            'data' => [['request_id' => 'PT-REQ-2026-001', 'status' => 'Pending']],
                        ],
                    ]),
                    endpoint('/api/cctv_requests_receive.php', [
                        'name' => 'Submit Footage Request',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'module' => 'Footage Request',
                        'direction' => 'inbound',
                        'description' => 'Submit CCTV footage request from partner agency',
                        'sample_request' => [
                            'agency' => 'Barangay Legal Office',
                            'contact_number' => '09181234567',
                            'email' => 'legal@example.com',
                            'case_reference' => 'CASE-2026-014',
                            'purpose' => 'Investigation of reported disturbance',
                            'legal_basis' => 'Community safety investigation',
                            'incident_location' => 'Heavenly Drive, Brgy. San Agustin',
                            'camera' => 'CAM-001',
                            'footage_window' => [
                                'date' => '2026-07-09',
                                'start' => '18:00',
                                'end' => '19:30',
                            ],
                            'incident_description' => 'Reported disturbance',
                            'delivery_method' => 'secure_download',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'message' => 'Footage request received.',
                            'data' => ['request_id' => 'CCTV-REQ-2026-001', 'status' => 'Pending'],
                        ],
                    ]),
                    endpoint('/api/cctv_requests.php', [
                        'name' => 'List CCTV Requests',
                        'methods' => ['GET'],
                        'auth' => 'public',
                        'description' => 'List CCTV requests',
                        'query_params' => [
                            'request_id' => 'Filter by CCTV-REQ-YYYY-###',
                            'status' => 'Pending | Under Review | Approved | Fulfilled | Rejected | Cancelled',
                            'source_reference_id' => 'Partner reference ID',
                            'requesting_agency' => 'Partial agency name match',
                            'pretty' => '1 for server-side pretty-print (optional)',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'count' => 1,
                            'data' => [['request_id' => 'CCTV-REQ-2026-001', 'status' => 'Pending']],
                        ],
                    ]),
                    endpoint('/api/awareness_events_receive.php', [
                        'name' => 'Submit Awareness Event or Report',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'groups' => ['campaign'],
                        'description' => 'Submit scheduled awareness event or post-event report from Campaign',
                        'sample_request_event' => [
                            'record_type' => 'event',
                            'source_group' => 'campaign',
                            'source_reference_id' => 'G6-EVT-2026-014',
                            'event_name' => 'Community Safety Awareness',
                            'event_date' => '2026-07-25',
                            'event_time' => '09:00',
                            'organizer' => 'Maria Santos',
                            'event_type' => 'Awareness',
                            'venue' => 'Barangay San Agustin Hall',
                            'status' => 'Scheduled',
                        ],
                        'sample_request_report' => [
                            'record_type' => 'report',
                            'source_group' => 'campaign',
                            'event_id' => 'EVT-2026-001',
                            'title' => 'Community Safety Awareness',
                            'event_date' => '2026-07-15',
                            'attendance_count' => 150,
                            'organizer' => 'Maria Santos',
                            'survey_results' => '85% Positive',
                            'evaluation_score' => '4.6',
                            'event_outcome' => 'Successful community turnout',
                            'location' => 'Barangay San Agustin Hall, Quezon City',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'message' => 'Awareness event received.',
                            'data' => ['record_type' => 'event', 'event_id' => 'EVT-2026-001'],
                        ],
                    ]),
                    endpoint('/api/crime_analytics_alerts_receive.php', [
                        'name' => 'Submit Risk Alert / Hotspot',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'groups' => ['crime_analytics'],
                        'description' => 'Submit triggered alert from Crime Analytics when a high-risk area is detected',
                        'sample_request' => [
                            'source_group' => 'crime_analytics',
                            'alert_id' => 'ALT-2026-001',
                            'rule_name' => 'Hotspot Detection',
                            'rule_type' => 'Hotspot',
                            'severity' => 'CRITICAL',
                            'condition' => 'Crime density > 8 in area',
                            'area_name' => 'Heavenly Drive',
                            'location' => 'Heavenly Drive Brgy. San Agustin QC',
                            'route' => 'Heavenly Drive, Barangay San Agustin',
                            'incident_count' => 12,
                            'time_window' => 'last 24 hours',
                            'triggered_at' => '2026-07-12T08:00:00+08:00',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'message' => 'Risk alert received.',
                            'data' => ['alert_id' => 'ALT-2026-001', 'status' => 'active'],
                        ],
                    ]),
                    endpoint('/api/risk_alerts.php', [
                        'name' => 'List High-Risk Alerts',
                        'methods' => ['GET'],
                        'auth' => 'public',
                        'groups' => ['crime_analytics'],
                        'description' => 'List active high-risk alerts for patrol scheduling',
                        'query_params' => [
                            'status' => 'active (default) | all | resolved | inactive',
                            'severity' => 'CRITICAL | HIGH | MEDIUM | LOW',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'count' => 1,
                            'data' => [[
                                'alert_id' => 'ALT-2026-001',
                                'rule_name' => 'Hotspot Detection',
                                'severity' => 'CRITICAL',
                                'location' => 'Heavenly Drive Brgy. San Agustin QC',
                                'status' => 'active',
                            ]],
                        ],
                    ]),
                    endpoint('/api/awareness_events.php', [
                        'name' => 'List Awareness Events or Reports',
                        'methods' => ['GET'],
                        'auth' => 'public',
                        'description' => 'List awareness events (Event List) or post-event reports (Event Reports)',
                        'query_params' => [
                            'record_type' => 'event | report (default: event)',
                            'event_id' => 'Filter by EVT-YYYY-###',
                            'report_id' => 'Filter by EVT-RPT-YYYY-### (reports only)',
                            'status' => 'Filter events by status (Scheduled, Pending, etc.)',
                            'event_type' => 'Filter events by type (Awareness, Meeting, Training)',
                            'pretty' => '1 for server-side pretty-print (optional)',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'record_type' => 'event',
                            'count' => 1,
                            'data' => [['event_id' => 'EVT-2026-001', 'event_name' => 'Community Safety Awareness', 'status' => 'Scheduled']],
                        ],
                    ]),
                ],
            ],
            'outbound_from_alertaraqc' => [
                'description' => 'AlertaraQC sends data TO partner groups (partners must host receive endpoints)',
                'endpoints' => [
                    endpoint('/api/tip_incident_receive.php', [
                        'name' => 'Receive Tip Incident (reference)',
                        'methods' => ['POST'],
                        'auth' => 'api_key',
                        'module' => 'Review Tip',
                        'direction' => 'outbound_reference_receiver',
                        'groups' => ['incident-reporting'],
                        'env_key' => 'INCIDENT_REPORTING_API_KEY',
                        'alertaraqc_env_url' => 'INCIDENT_REPORTING_TIP_API_URL or INCIDENT_REPORTING_API_URL',
                        'triggered_by' => 'Admin Review Tip → Send to Incident Logging',
                        'description' => 'Reference endpoint Incident Reporting should implement for tip incident logging. Includes tip photo evidence when available.',
                        'photo_fields' => [
                            'has_photo' => 'boolean — true when tip has attached photo',
                            'photo_data' => 'data:image/...;base64,... JPEG/PNG data URL (may be compressed)',
                            'photo_of_evidence' => 'same photo as photo_data (partner alias)',
                            'attached_evidence.photo_data' => 'nested copy of the tip photo',
                        ],
                        'sample_request' => [
                            'source' => 'alertaraqc',
                            'record_type' => 'tip',
                            'source_tip_id' => 'TIP-2026-002',
                            'tip_id' => 'TIP-2026-002',
                            'date_time' => '2026-07-09T20:59:59+08:00',
                            'location' => 'Heavenly Drive Brgy. San Agustin QC',
                            'tip_description' => 'Community tip description',
                            'status' => 'Assigned',
                            'outcome' => 'No Outcome Yet',
                            'incident' => [
                                'location' => 'Heavenly Drive Brgy. San Agustin QC',
                                'description' => 'Community tip description',
                                'submitted_at' => '2026-07-09T20:59:59+08:00',
                                'classification' => 'community_tip',
                            ],
                            'reporter' => [
                                'contact_number' => null,
                                'anonymous' => true,
                            ],
                            'review' => [
                                'status' => 'Assigned',
                                'outcome' => 'No Outcome Yet',
                                'assigned_to' => null,
                            ],
                            'has_photo' => true,
                            'photo_data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                            'photo_of_evidence' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                            'attached_evidence' => [
                                'type' => 'photo',
                                'photo_data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                                'available' => true,
                            ],
                            'metadata' => [
                                'internal_id' => 1,
                                'forwarded_by' => 'alertaraqc_bpso_admin',
                                'forwarded_at' => '2026-07-10T00:00:00+08:00',
                                'has_photo' => true,
                            ],
                        ],
                        'sample_response' => [
                            'success' => true,
                            'blotter_reference_id' => 'INC-2026-A1B2C3',
                            'message' => 'Tip received and logged.',
                        ],
                    ]),
                    endpoint('/api/blotter_receive.php', [
                        'name' => 'Receive Complaint / Digital Blotter (reference)',
                        'methods' => ['POST'],
                        'auth' => 'api_key',
                        'env_key' => 'INCIDENT_REPORTING_API_KEY',
                        'alertaraqc_env_url' => 'INCIDENT_REPORTING_API_URL',
                        'module' => 'Track Complaint',
                        'direction' => 'outbound_reference_receiver',
                        'triggered_by' => 'Admin Track Complaint → Forward to Digital Blotter',
                        'description' => 'Reference endpoint Incident Reporting should implement for complaint blotter',
                        'sample_request' => [
                            'source' => 'alertaraqc',
                            'source_complaint_id' => 'COMP-2026-362',
                            'complainant_name' => 'Juan Dela Cruz',
                            'complainant_address' => '123 Sample St',
                            'complainant_contact_number' => '09171234567',
                            'date_time' => '2026-07-09 14:30',
                            'defendant_name' => 'Pedro Santos',
                            'complaint_type' => 'Noise Complaint',
                            'status_description' => 'Loud karaoke after curfew',
                            'notes' => 'Resident request for mediation',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'blotter_reference_id' => 'DB-2026-A1B2C3',
                            'message' => 'Complaint received by Digital Blotter System.',
                        ],
                    ]),
                    endpoint('/api/complaints_status_receive.php', [
                        'name' => 'Update Complaint Status (inbound)',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'module' => 'Track Complaint',
                        'direction' => 'inbound',
                        'description' => 'Incident Reporting pushes complaint Status updates back to AlertaraQC',
                        'sample_request' => [
                            'complaint_id' => 'COMP-2026-362',
                            'status' => 'Resolved',
                            'notes' => 'Closed in Digital Blotter',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'message' => 'Complaint status updated.',
                            'data' => ['complaint_id' => 'COMP-2026-362', 'status' => 'Resolved'],
                        ],
                    ]),
                    endpoint('/api/coordination_receive.php', [
                        'name' => 'Receive Police Backup / Inter-Agency Tip (reference)',
                        'methods' => ['POST'],
                        'auth' => 'api_key',
                        'module' => 'Review Tip',
                        'direction' => 'outbound_reference_receiver',
                        'groups' => ['emergency-response'],
                        'env_key' => 'EMERGENCY_RESPONSE_API_KEY',
                        'alertaraqc_env_url' => 'EMERGENCY_RESPONSE_API_URL',
                        'triggered_by' => 'Admin Review Tip → Send to Inter-Agency',
                        'description' => 'Reference / contract for Emergency Response anonymous_tip.php-style APIs. Includes tip photo evidence when available.',
                        'photo_fields' => [
                            'has_photo' => 'boolean — true when tip has attached photo',
                            'photo_of_evidence' => 'primary field for Emergency Response (data URL)',
                            'photo_data' => 'same photo (partner alias)',
                            'attached_evidence.photo_data' => 'nested copy of the tip photo',
                        ],
                        'sample_request' => [
                            'tip_id' => 'TIP-2026-002',
                            'tip_datetime' => '2026-07-09 20:59:59',
                            'location' => 'Heavenly Drive Brgy. San Agustin QC',
                            'tip_description' => "nagririot mga kabataan\n\n[Police backup] Immediate backup needed",
                            'photo_of_evidence' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                            'photo_data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                            'status' => 'Assigned',
                            'outcome' => 'No Outcome Yet',
                            'source_system' => 'alertaraqc',
                            'source' => 'alertaraqc',
                            'record_type' => 'tip',
                            'request_type' => 'police_backup',
                            'source_tip_id' => 'TIP-2026-002',
                            'requesting_agency' => 'BPSO - Quezon City',
                            'incident' => [
                                'location' => 'Heavenly Drive Brgy. San Agustin QC',
                                'description' => "nagririot mga kabataan\n\n[Police backup] Immediate backup needed",
                                'submitted_at' => '2026-07-09T20:59:59+08:00',
                            ],
                            'backup' => [
                                'reason' => 'Immediate backup needed',
                                'priority' => 'high',
                                'units_requested' => 'patrol',
                            ],
                            'has_photo' => true,
                            'attached_evidence' => [
                                'type' => 'photo',
                                'photo_data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD...',
                                'available' => true,
                            ],
                            'metadata' => [
                                'internal_id' => 1,
                                'forwarded_by' => 'alertaraqc_bpso_admin',
                                'forwarded_at' => '2026-07-10T00:00:00+08:00',
                                'police_backup' => true,
                                'has_photo' => true,
                            ],
                        ],
                        'sample_response' => [
                            'success' => true,
                            'coordination_reference_id' => 'COORD-2026-A1B2C3',
                            'message' => 'Police backup request received.',
                        ],
                    ]),
                    endpoint('/api/tip_backup_status_receive.php', [
                        'name' => 'Receive Inter-Agency Status Update',
                        'methods' => ['POST'],
                        'auth' => 'api_key',
                        'module' => 'Review Tip',
                        'direction' => 'inbound',
                        'groups' => ['emergency-response'],
                        'env_key' => 'EMERGENCY_RESPONSE_API_KEY',
                        'description' => 'Inter-Agency / Emergency Response callbacks for assistance progress (UI: Inter-Agency Status). Separate from tanod tip Outcome.',
                        'accepted_statuses' => ['Requested', 'Dispatched', 'Completed', 'Declined'],
                        'sample_request' => [
                            'source_tip_id' => 'TIP-2026-002',
                            'backup_status' => 'Dispatched',
                            'notes' => 'Unit 12 en route',
                            'emergency_response_reference_id' => 'COORD-2026-A1B2C3',
                        ],
                        'sample_response' => [
                            'success' => true,
                            'source_tip_id' => 'TIP-2026-002',
                            'backup_status' => 'Dispatched',
                            'message' => 'Inter-Agency status updated to Dispatched.',
                        ],
                    ]),
                ],
            ],
        ],
        'internal_apis' => [
            'admin' => [
                'description' => 'Require admin login session — not for external partner integration',
                'endpoints' => [
                    endpoint('/api/dashboard.php', ['name' => 'Dashboard Stats', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/users.php', ['name' => 'User Management', 'methods' => ['GET', 'POST', 'PUT'], 'auth' => 'admin_session']),
                    endpoint('/api/login-history.php', ['name' => 'Audit Trails / Login History', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/notifications.php', [
                        'name' => 'Admin Notifications',
                        'methods' => ['GET'],
                        'auth' => 'admin_session',
                        'actions' => ['list', 'sync', 'mark_read'],
                    ]),
                    endpoint('/api/complaints.php', [
                        'name' => 'Complaints',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session',
                        'actions' => ['create', 'assign', 'forward', 'update', 'manage', 'delete'],
                    ]),
                    endpoint('/api/tips.php', [
                        'name' => 'Anonymous Tips',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session (create is public)',
                        'actions' => ['create', 'update', 'delete'],
                    ]),
                    endpoint('/api/neighborhood-watcher-members.php', [
                        'name' => 'Neighborhood Watch Members / Applications',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session',
                        'actions' => ['create', 'update', 'delete'],
                    ]),
                    endpoint('/api/volunteers.php', [
                        'name' => 'NW Volunteer Applications (legacy alias → neighborhood-watcher-members.php)',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session',
                        'actions' => ['create', 'update', 'delete'],
                    ]),
                    endpoint('/api/neighborhood-watcher-incidents.php', [
                        'name' => 'NW Incident Reports (admin review)',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session',
                        'actions' => ['create', 'assign', 'update'],
                    ]),
                    endpoint('/api/patrols.php', [
                        'name' => 'BPSO Personnel',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session',
                        'actions' => ['create', 'update', 'delete'],
                    ]),
                    endpoint('/api/patrol_schedules.php', [
                        'name' => 'Patrol Schedules',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session or bpso_session (GET own)',
                        'actions' => ['create', 'update_status'],
                    ]),
                    endpoint('/api/patrol_logs.php', ['name' => 'Patrol Logs', 'methods' => ['GET', 'POST'], 'auth' => 'admin_session or bpso_session']),
                    endpoint('/api/bpso_attendance.php', [
                        'name' => 'BPSO Attendance',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session or bpso_session',
                        'query_params' => ['view' => 'at_hall | all | my_status', 'date' => 'YYYY-MM-DD'],
                        'actions' => ['time_in', 'time_out'],
                    ]),
                    endpoint('/api/patrol_requests.php', [
                        'name' => 'Patrol Requests (admin manage)',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session (manage) or public (GET/create)',
                        'actions' => ['create', 'manage'],
                    ]),
                    endpoint('/api/cctv_requests.php', [
                        'name' => 'CCTV Requests (admin manage)',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session (manage) or public (GET/create)',
                        'actions' => ['create', 'manage', 'get_document'],
                    ]),
                    endpoint('/api/awareness_events.php', [
                        'name' => 'Awareness Events (admin manage)',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'admin_session (manage) or public (GET/create)',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/cameras.php', ['name' => 'Camera Management', 'methods' => ['GET', 'POST'], 'auth' => 'admin_session']),
                    endpoint('/api/send_to_incident_reporting.php', [
                        'name' => 'Forward Tip to Incident Reporting',
                        'methods' => ['POST'],
                        'auth' => 'admin_session',
                        'sample_request' => ['id' => 1, 'tip_id' => 'TIP-2026-002'],
                    ]),
                    endpoint('/api/send_cctv_to_incident_reporting.php', [
                        'name' => 'Forward CCTV Evidence to Incident Reporting',
                        'methods' => ['POST'],
                        'auth' => 'admin_session',
                        'sample_request' => ['id' => 1, 'request_id' => 'CCTV-REQ-2026-001'],
                    ]),
                    endpoint('/api/send_to_emergency_response.php', [
                        'name' => 'Forward Tip to Emergency Response (Police Backup)',
                        'methods' => ['POST'],
                        'auth' => 'admin_session',
                        'sample_request' => ['id' => 1, 'police_backup_reason' => 'Reason for backup'],
                    ]),
                    endpoint('/api/admins.php', ['name' => 'Admin Accounts', 'methods' => ['GET', 'POST'], 'auth' => 'admin_session']),
                ],
            ],
            'bpso_portal' => [
                'description' => 'Require BPSO personnel login session',
                'endpoints' => [
                    endpoint('/api/bpso_profile.php', ['name' => 'BPSO Profile', 'methods' => ['GET'], 'auth' => 'bpso_session']),
                    endpoint('/api/bpso_attendance.php', ['name' => 'Time In / Out', 'methods' => ['GET', 'POST'], 'auth' => 'bpso_session']),
                    endpoint('/api/bpso_complaints.php', [
                        'name' => 'Assigned Complaints',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'bpso_session',
                        'actions' => ['submit_resolution'],
                    ]),
                    endpoint('/api/bpso-neighborhood-watcher-incidents.php', [
                        'name' => 'Assigned NW Incidents',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'bpso_session',
                        'actions' => ['submit_resolution'],
                    ]),
                    endpoint('/api/bpso_notifications.php', [
                        'name' => 'BPSO Notifications',
                        'methods' => ['GET'],
                        'auth' => 'bpso_session',
                        'actions' => ['list', 'sync', 'mark_read'],
                    ]),
                    endpoint('/api/patrol_schedules.php', ['name' => 'My Schedule', 'methods' => ['GET', 'POST'], 'auth' => 'bpso_session']),
                    endpoint('/api/patrol_logs.php', ['name' => 'My Patrol Logs', 'methods' => ['GET', 'POST'], 'auth' => 'bpso_session']),
                    endpoint('/api/bpso-forgot-password.php', [
                        'name' => 'BPSO Forgot Password',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['request', 'verify', 'reset'],
                    ]),
                ],
            ],
            'nw_portal' => [
                'description' => 'Require Neighborhood Watch member login session',
                'endpoints' => [
                    endpoint('/api/neighborhood-watcher-incidents.php', [
                        'name' => 'NW Incident Reports',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'nw_session or admin_session',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/neighborhood-watcher-member-profile.php', [
                        'name' => 'NW Member Profile',
                        'methods' => ['GET', 'POST'],
                        'auth' => 'nw_session',
                        'actions' => ['update_profile', 'change_password'],
                    ]),
                    endpoint('/api/neighborhood-watcher-forgot-password.php', [
                        'name' => 'NW Forgot Password',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['request', 'verify', 'reset'],
                    ]),
                ],
            ],
            'public' => [
                'description' => 'Public or lightly restricted endpoints',
                'endpoints' => [
                    endpoint('/api/tips.php', [
                        'name' => 'Submit Anonymous Tip',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/neighborhood-watcher-members.php', [
                        'name' => 'Neighborhood Watch Application',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/volunteers.php', [
                        'name' => 'NW Volunteer Application (legacy alias → neighborhood-watcher-members.php)',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/complaints.php', [
                        'name' => 'Submit Complaint (public form)',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['create'],
                    ]),
                    endpoint('/api/forgot-password.php', [
                        'name' => 'Admin Forgot Password',
                        'methods' => ['POST'],
                        'auth' => 'public',
                        'actions' => ['request', 'verify', 'reset'],
                    ]),
                ],
            ],
            'surveillance' => [
                'description' => 'CCTV live feed and detection (admin surveillance page)',
                'endpoints' => [
                    endpoint('/api/camera_status.php', ['name' => 'Camera Online Status', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/current_frame.php', ['name' => 'Live Camera Frame', 'methods' => ['GET', 'HEAD'], 'auth' => 'admin_session']),
                    endpoint('/api/get_detections.php', ['name' => 'AI Detections', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/get_frame_info.php', ['name' => 'Frame Metadata', 'methods' => ['GET'], 'auth' => 'admin_session']),
                ],
            ],
            'utility' => [
                'description' => 'Development and testing only — not for production partners',
                'endpoints' => [
                    endpoint('/api/test-email.php', ['name' => 'Test Email', 'methods' => ['GET', 'POST'], 'auth' => 'admin_session']),
                    endpoint('/api/test-notifications.php', ['name' => 'Test Notifications', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/debug-notifications.php', ['name' => 'Debug Notifications', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/clear-opcache.php', ['name' => 'Clear OPcache', 'methods' => ['GET'], 'auth' => 'admin_session']),
                    endpoint('/api/seed_patrol_requests_sample.php', ['name' => 'Seed Patrol Request Samples', 'methods' => ['CLI'], 'auth' => 'cli_only']),
                    endpoint('/api/seed_awareness_events_sample.php', ['name' => 'Seed Awareness Event Samples', 'methods' => ['CLI'], 'auth' => 'cli_only']),
                ],
            ],
        ],
        'quick_links' => [
            'partner_api' => integrationBaseUrl() . '/api/partner-api.php',
            'catalog' => integrationBaseUrl() . '/api/partner-api.php',
            'patrol_list' => integrationBaseUrl() . '/api/patrol_requests.php',
            'cctv_list' => integrationBaseUrl() . '/api/cctv_requests.php',
            'awareness_events_list' => integrationBaseUrl() . '/api/awareness_events.php?record_type=event',
            'awareness_reports_list' => integrationBaseUrl() . '/api/awareness_events.php?record_type=report',
            'markdown_docs' => integrationBaseUrl() . '/api/API_INTEGRATION.md',
        ],
    ];
}

jsonResponse(getIntegrationCatalog());
