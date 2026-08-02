<?php

/**
 * Inbound API — partner systems (e.g. Incident Reporting Digital Blotter) push complaint Status updates
 * back into AlertaraQC Track Complaint.
 *
 * POST JSON:
 *   {
 *     "complaint_id": "COMP-2026-001",          // preferred (AlertaraQC ID)
 *     "source_complaint_id": "COMP-2026-001",   // alias
 *     "status": "Resolved",
 *     "notes": "Optional partner note",
 *     "status_description": "Optional"
 *   }
 *
 * Public — no API key required.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/complaints_schema.php';
require_once __DIR__ . '/notifications_schema.php';

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

try {
    ensureComplaintsTable($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare complaints table.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$complaintId = trim($input['complaint_id'] ?? $input['source_complaint_id'] ?? $input['source_complaintId'] ?? '');
$status = trim($input['status'] ?? '');
$notes = trim($input['notes'] ?? $input['status_description'] ?? '');

$allowedStatuses = ['Pending', 'Processing', 'Resolved', 'Rejected', 'Forwarded to Digital Blotter'];

if ($complaintId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'complaint_id (or source_complaint_id) is required.']);
    exit;
}

if ($status === '' || !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid status is required. Allowed: ' . implode(', ', $allowedStatuses),
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, complaint_id, status, notes FROM complaints WHERE complaint_id = :complaint_id LIMIT 1');
    $stmt->execute([':complaint_id' => $complaintId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
        exit;
    }

    $updatedNotes = trim((string) ($complaint['notes'] ?? ''));
    if ($notes !== '') {
        $stamp = date('Y-m-d H:i:s');
        $entry = "[{$stamp}] Partner status update: {$status}. {$notes}";
        $updatedNotes = trim($updatedNotes . "\n\n" . $entry);
    }

    $update = $pdo->prepare('UPDATE complaints SET status = :status, notes = :notes WHERE id = :id');
    $update->execute([
        ':status' => $status,
        ':notes' => $updatedNotes !== '' ? $updatedNotes : null,
        ':id' => (int) $complaint['id'],
    ]);

    if ($status === 'Resolved' || $status === 'Rejected') {
        createAdminNotification(
            $pdo,
            'complaint',
            'Complaint Status Updated',
            $complaintId . ' is now ' . $status . ' (partner update).',
            'track-complaint.php?id=' . urlencode($complaintId) . '&activity=status_' . strtolower($status)
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Complaint status updated.',
        'data' => [
            'complaint_id' => $complaintId,
            'status' => $status,
            'previous_status' => $complaint['status'] ?? null,
        ],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update complaint status.']);
}
