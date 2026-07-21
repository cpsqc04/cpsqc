<?php

/**
 * Admin endpoint: forward CCTV evidence to Group 1 Incident Logging and Classification.
 *
 * POST JSON:
 *   { "id": 1 }
 *   or { "request_id": "CCTV-REQ-2026-001" }
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/cctv_requests_schema.php';
require_once __DIR__ . '/../includes/cctv_forward.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);
$requestId = trim($input['request_id'] ?? '');

try {
    ensureCctvRequestsTable($pdo);

    if ($id <= 0 && $requestId !== '') {
        $stmt = $pdo->prepare('SELECT id FROM cctv_requests WHERE request_id = :request_id LIMIT 1');
        $stmt->execute([':request_id' => $requestId]);
        $id = (int) $stmt->fetchColumn();
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid CCTV request id is required.']);
        exit;
    }

    $columns = cctvRequestsSelectColumns();
    $stmt = $pdo->prepare("SELECT {$columns} FROM cctv_requests WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'CCTV request not found.']);
        exit;
    }

    if (!empty($request['forwarded_to_group1_at'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This CCTV request was already sent to Group 1.',
            'data' => [
                'forwarded_to_group1_at' => $request['forwarded_to_group1_at'],
                'group1_evidence_reference_id' => $request['group1_evidence_reference_id'],
                'forwarded_recording_files' => json_decode((string) ($request['forwarded_recording_files'] ?? ''), true),
            ],
        ]);
        exit;
    }

    $blockedStatuses = ['Rejected', 'Cancelled'];
    if (in_array((string) ($request['status'] ?? ''), $blockedStatuses, true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cannot forward a rejected or cancelled CCTV request.']);
        exit;
    }

    $result = forwardCctvEvidenceToGroup1($request);
    if (!$result['success']) {
        $message = $result['message'] ?? 'Failed to forward CCTV evidence.';
        $httpCode = stripos($message, 'no matching') !== false ? 404 : 502;
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        exit;
    }

    $timestamp = date('Y-m-d H:i:s');
    $referenceId = trim($result['evidence_reference_id'] ?? '');
    $filenames = array_values(array_map(static function ($segment) {
        return $segment['filename'];
    }, $result['segments'] ?? []));

    $fulfillmentNotes = trim((string) ($request['fulfillment_notes'] ?? ''));
    $noteLine = 'Sent to Group 1 for evidence'
        . ($referenceId !== '' ? ' — Ref: ' . $referenceId : '')
        . ' (' . count($filenames) . ' recording segment' . (count($filenames) === 1 ? '' : 's') . ').';
    if ($fulfillmentNotes !== '') {
        $fulfillmentNotes .= "\n" . $noteLine;
    } else {
        $fulfillmentNotes = $noteLine;
    }

    $update = $pdo->prepare('UPDATE cctv_requests SET
        status = :status,
        forwarded_to_group1_at = :forwarded_at,
        group1_evidence_reference_id = :reference_id,
        forwarded_recording_files = :recording_files,
        fulfillment_notes = :fulfillment_notes,
        fulfilled_at = COALESCE(fulfilled_at, :fulfilled_at),
        reviewed_by = COALESCE(reviewed_by, :reviewed_by)
        WHERE id = :id');
    $update->execute([
        ':status' => 'Fulfilled',
        ':forwarded_at' => $timestamp,
        ':reference_id' => $referenceId !== '' ? $referenceId : null,
        ':recording_files' => json_encode($filenames, JSON_UNESCAPED_UNICODE),
        ':fulfillment_notes' => $fulfillmentNotes,
        ':fulfilled_at' => $timestamp,
        ':reviewed_by' => $_SESSION['admin_username'] ?? 'admin',
        ':id' => $id,
    ]);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'id' => $id,
            'request_id' => $request['request_id'],
            'forwarded_to_group1_at' => $timestamp,
            'group1_evidence_reference_id' => $referenceId,
            'forwarded_recording_files' => $filenames,
            'segment_count' => count($filenames),
            'status' => 'Fulfilled',
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
