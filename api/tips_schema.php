<?php

/**
 * Tips table schema and column helpers.
 */

require_once __DIR__ . '/../includes/schema_helpers.php';

function ensureTipsTable(PDO $pdo): void
{
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query('SHOW COLUMNS FROM tips') as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE tips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tip_id VARCHAR(255) UNIQUE NOT NULL,
            location TEXT NOT NULL,
            description TEXT NOT NULL,
            contact_number VARCHAR(50) DEFAULT NULL,
            photo_data LONGTEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'New',
            outcome VARCHAR(100) DEFAULT 'No Outcome Yet',
            assigned_to VARCHAR(255) DEFAULT NULL,
            assigned_patrol_id INT NULL,
            resolution_report TEXT NULL,
            assigned_at DATETIME DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            police_backup_reason TEXT DEFAULT NULL,
            forwarded_at DATETIME DEFAULT NULL,
            blotter_reference_id VARCHAR(100) DEFAULT NULL,
            backup_requested_at DATETIME DEFAULT NULL,
            emergency_response_reference_id VARCHAR(100) DEFAULT NULL,
            backup_status VARCHAR(50) NOT NULL DEFAULT 'Not Requested',
            backup_status_updated_at DATETIME DEFAULT NULL,
            backup_status_notes TEXT DEFAULT NULL,
            saved_at DATETIME DEFAULT NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assigned_patrol_id (assigned_patrol_id),
            INDEX idx_status (status),
            INDEX idx_backup_status (backup_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    renameTableColumnIfNeeded(
        $pdo,
        'tips',
        'group3_reference_id',
        'emergency_response_reference_id',
        'VARCHAR(100) DEFAULT NULL'
    );

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM tips') as $row) {
        $columns[$row['Field']] = true;
    }

    $alterations = [
        'tip_id' => 'ALTER TABLE tips ADD COLUMN tip_id VARCHAR(255) UNIQUE NOT NULL DEFAULT "" AFTER id',
        'location' => 'ALTER TABLE tips ADD COLUMN location TEXT NOT NULL DEFAULT "" AFTER tip_id',
        'description' => 'ALTER TABLE tips ADD COLUMN description TEXT NOT NULL DEFAULT "" AFTER location',
        'contact_number' => 'ALTER TABLE tips ADD COLUMN contact_number VARCHAR(50) DEFAULT NULL AFTER description',
        'photo_data' => 'ALTER TABLE tips ADD COLUMN photo_data LONGTEXT NULL AFTER contact_number',
        'status' => 'ALTER TABLE tips ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT "New" AFTER photo_data',
        'outcome' => 'ALTER TABLE tips ADD COLUMN outcome VARCHAR(100) DEFAULT "No Outcome Yet" AFTER status',
        'assigned_to' => 'ALTER TABLE tips ADD COLUMN assigned_to VARCHAR(255) DEFAULT NULL AFTER outcome',
        'assigned_patrol_id' => 'ALTER TABLE tips ADD COLUMN assigned_patrol_id INT NULL AFTER assigned_to',
        'resolution_report' => 'ALTER TABLE tips ADD COLUMN resolution_report TEXT NULL AFTER assigned_patrol_id',
        'assigned_at' => 'ALTER TABLE tips ADD COLUMN assigned_at DATETIME DEFAULT NULL AFTER resolution_report',
        'resolved_at' => 'ALTER TABLE tips ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER assigned_at',
        'police_backup_reason' => 'ALTER TABLE tips ADD COLUMN police_backup_reason TEXT DEFAULT NULL AFTER resolved_at',
        'forwarded_at' => 'ALTER TABLE tips ADD COLUMN forwarded_at DATETIME DEFAULT NULL AFTER police_backup_reason',
        'blotter_reference_id' => 'ALTER TABLE tips ADD COLUMN blotter_reference_id VARCHAR(100) DEFAULT NULL AFTER forwarded_at',
        'backup_requested_at' => 'ALTER TABLE tips ADD COLUMN backup_requested_at DATETIME DEFAULT NULL AFTER blotter_reference_id',
        'emergency_response_reference_id' => 'ALTER TABLE tips ADD COLUMN emergency_response_reference_id VARCHAR(100) DEFAULT NULL AFTER backup_requested_at',
        'backup_status' => 'ALTER TABLE tips ADD COLUMN backup_status VARCHAR(50) NOT NULL DEFAULT "Not Requested" AFTER emergency_response_reference_id',
        'backup_status_updated_at' => 'ALTER TABLE tips ADD COLUMN backup_status_updated_at DATETIME DEFAULT NULL AFTER backup_status',
        'backup_status_notes' => 'ALTER TABLE tips ADD COLUMN backup_status_notes TEXT DEFAULT NULL AFTER backup_status_updated_at',
        'saved_at' => 'ALTER TABLE tips ADD COLUMN saved_at DATETIME DEFAULT NULL AFTER backup_status_notes',
        'submitted_at' => 'ALTER TABLE tips ADD COLUMN submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER saved_at',
        'created_at' => 'ALTER TABLE tips ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($alterations as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    // Backfill police backup status for tips already sent to Inter-Agency.
    try {
        $pdo->exec("UPDATE tips
            SET backup_status = 'Requested',
                backup_status_updated_at = COALESCE(backup_status_updated_at, backup_requested_at)
            WHERE backup_requested_at IS NOT NULL
              AND (backup_status IS NULL OR backup_status = '' OR backup_status = 'Not Requested')");
    } catch (PDOException $e) {
        // ignore
    }

    // Migrate legacy review statuses into the new response workflow.
    try {
        $pdo->exec("UPDATE tips
            SET status = CASE
                WHEN assigned_patrol_id IS NOT NULL AND status NOT IN ('Resolved') THEN 'Assigned'
                WHEN status IN ('Under Review', 'Reviewed', '') OR status IS NULL THEN 'New'
                ELSE status
            END
            WHERE status IN ('Under Review', 'Reviewed', '') OR status IS NULL
               OR (assigned_patrol_id IS NOT NULL AND status = 'New')");
    } catch (PDOException $e) {
        // ignore migration failures on restricted hosts
    }

    // Outcome is set only by the assigned patrol's report — clear stale admin-edited values.
    try {
        $pdo->exec("UPDATE tips
            SET outcome = 'No Outcome Yet'
            WHERE (assigned_patrol_id IS NULL OR assigned_patrol_id = 0)
              AND outcome IS NOT NULL
              AND outcome <> 'No Outcome Yet'");
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * Tip SELECT columns. Omit full photo_data for list endpoints (use has_photo instead).
 */
function tipsSelectColumns(string $prefix = '', bool $includePhoto = true): string
{
    $p = $prefix !== '' ? $prefix . '.' : '';
    $photoCol = $includePhoto
        ? "{$p}photo_data"
        : "(CASE WHEN {$p}photo_data IS NOT NULL AND TRIM({$p}photo_data) <> '' THEN 1 ELSE 0 END) AS has_photo";

    return implode(', ', [
        "{$p}id",
        "{$p}tip_id",
        "{$p}location",
        "{$p}description",
        "{$p}contact_number",
        $photoCol,
        "{$p}status",
        "{$p}outcome",
        "{$p}assigned_to",
        "{$p}assigned_patrol_id",
        "{$p}resolution_report",
        "{$p}assigned_at",
        "{$p}resolved_at",
        "{$p}police_backup_reason",
        "{$p}forwarded_at",
        "{$p}blotter_reference_id",
        "{$p}backup_requested_at",
        "{$p}emergency_response_reference_id",
        "{$p}backup_status",
        "{$p}backup_status_updated_at",
        "{$p}backup_status_notes",
        "{$p}saved_at",
        "{$p}submitted_at",
        "{$p}created_at",
    ]);
}

function tipHasPhotoFlag(array $tip): bool
{
    if (array_key_exists('has_photo', $tip)) {
        return !empty($tip['has_photo']);
    }
    $photo = trim((string) ($tip['photo_data'] ?? ''));
    return $photo !== '';
}

function tipBackupStatusOptions(): array
{
    return ['Not Requested', 'Requested', 'Dispatched', 'Completed'];
}

/**
 * Normalize partner/admin backup status into the canonical set.
 */
function normalizeTipBackupStatus(?string $status, $backupRequestedAt = null): string
{
    $value = trim((string) $status);
    $map = [
        'not requested' => 'Not Requested',
        'none' => 'Not Requested',
        'requested' => 'Requested',
        'pending' => 'Requested',
        'received' => 'Requested',
        'dispatched' => 'Dispatched',
        'on scene' => 'Dispatched',
        'on-scene' => 'Dispatched',
        'en route' => 'Dispatched',
        'enroute' => 'Dispatched',
        'responding' => 'Dispatched',
        'completed' => 'Completed',
        'resolved' => 'Completed',
        'closed' => 'Completed',
    ];
    $key = strtolower($value);
    if (isset($map[$key])) {
        return $map[$key];
    }
    if (in_array($value, tipBackupStatusOptions(), true)) {
        return $value;
    }
    if (!empty($backupRequestedAt)) {
        return 'Requested';
    }
    return 'Not Requested';
}

/**
 * Persist a police backup status update on a tip.
 *
 * @return array{success:bool,message?:string,data?:array}
 */
function updateTipBackupStatus(
    PDO $pdo,
    int $tipId,
    string $status,
    ?string $notes = null,
    ?string $referenceId = null
): array {
    $tip = fetchTipById($pdo, $tipId);
    if (!$tip) {
        return ['success' => false, 'message' => 'Tip not found.'];
    }

    $normalized = normalizeTipBackupStatus($status, $tip['backup_requested_at'] ?? null);
    if ($normalized === 'Not Requested' && empty($tip['backup_requested_at'])) {
        // Allow explicit Not Requested only when never requested.
    } elseif ($normalized === 'Not Requested' && !empty($tip['backup_requested_at'])) {
        return ['success' => false, 'message' => 'Cannot reset Inter-Agency status to Not Requested after a request was sent.'];
    }

    if (!in_array($normalized, tipBackupStatusOptions(), true)) {
        return ['success' => false, 'message' => 'Invalid Inter-Agency status.'];
    }

    if ($normalized !== 'Not Requested' && empty($tip['backup_requested_at'])) {
        return ['success' => false, 'message' => 'Inter-Agency assistance has not been requested for this tip yet.'];
    }

    $previous = normalizeTipBackupStatus($tip['backup_status'] ?? null, $tip['backup_requested_at'] ?? null);
    $timestamp = date('Y-m-d H:i:s');
    $notesValue = $notes !== null ? trim($notes) : null;
    if ($notesValue === '') {
        $notesValue = null;
    }

    $ref = $referenceId !== null ? trim($referenceId) : '';
    $sql = 'UPDATE tips SET backup_status = :backup_status, backup_status_updated_at = :updated_at';
    $params = [
        ':backup_status' => $normalized,
        ':updated_at' => $timestamp,
        ':id' => $tipId,
    ];

    if ($notesValue !== null) {
        $sql .= ', backup_status_notes = :notes';
        $params[':notes'] = $notesValue;
    }
    if ($ref !== '') {
        $sql .= ', emergency_response_reference_id = :reference_id';
        $params[':reference_id'] = $ref;
    }
    $sql .= ' WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $updated = fetchTipById($pdo, $tipId) ?: $tip;
    $updated['backup_status'] = $normalized;
    $updated['backup_status_updated_at'] = $timestamp;
    if ($notesValue !== null) {
        $updated['backup_status_notes'] = $notesValue;
    }

    return [
        'success' => true,
        'message' => 'Inter-Agency status updated to ' . $normalized . '.',
        'data' => [
            'id' => $tipId,
            'tip_id' => $updated['tip_id'] ?? null,
            'previous_status' => $previous,
            'backup_status' => $normalized,
            'backup_status_updated_at' => $timestamp,
            'backup_status_notes' => $updated['backup_status_notes'] ?? null,
            'emergency_response_reference_id' => $updated['emergency_response_reference_id'] ?? null,
            'tip' => $updated,
        ],
    ];
}

/**
 * Notify assigned tanod and admins when Inter-Agency updates backup status.
 */
function notifyTipBackupStatusChange(PDO $pdo, array $tip, string $status): void
{
    require_once __DIR__ . '/notifications_schema.php';

    $tipLabel = (string) ($tip['tip_id'] ?? ('#' . ($tip['id'] ?? '')));
    $status = normalizeTipBackupStatus($status, $tip['backup_requested_at'] ?? null);
    $title = 'Inter-Agency Status: ' . $status;
    $message = 'Tip #' . $tipLabel . ' Inter-Agency status is now ' . $status . '.';
    $linkSuffix = 'backup:' . strtolower(str_replace(' ', '-', $status)) . ':' . ($tip['id'] ?? 0);

    $patrolId = (int) ($tip['assigned_patrol_id'] ?? 0);
    if ($patrolId > 0) {
        createPatrolNotification(
            $pdo,
            $patrolId,
            'tip_backup_status',
            $title,
            $message,
            'tab:tips:' . ($tip['id'] ?? 0) . ':' . $linkSuffix
        );
    }

    createAdminNotification(
        $pdo,
        'tip_backup_status',
        $title,
        $message,
        'review-tip.php?id=' . rawurlencode((string) ($tip['tip_id'] ?? '')) . '&' . $linkSuffix
    );
}

function normalizeTipStatus(?string $status): string
{
    $value = trim((string) $status);
    if (in_array($value, ['Assigned', 'Resolved', 'New'], true)) {
        return $value;
    }
    if (in_array($value, ['Under Review', 'Reviewed', 'Pending'], true)) {
        return 'New';
    }
    if ($value === 'Processing' || $value === 'In Progress') {
        return 'Assigned';
    }
    return $value !== '' ? $value : 'New';
}

function tipOutcomeOptions(): array
{
    return [
        'No Outcome Yet',
        'Under Investigation',
        'Investigation Successful',
        'Arrest Made',
        'Unfounded / No Action',
    ];
}

function fetchTipById(PDO $pdo, int $id, bool $includePhoto = true): ?array
{
    $cols = tipsSelectColumns('', $includePhoto);
    $stmt = $pdo->prepare("SELECT {$cols} FROM tips WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['has_photo'] = tipHasPhotoFlag($row);
    return $row;
}

function normalizeTipListRow(array $tip): array
{
    $tip['status'] = normalizeTipStatus($tip['status'] ?? 'New');
    $tip['backup_status'] = normalizeTipBackupStatus(
        $tip['backup_status'] ?? null,
        $tip['backup_requested_at'] ?? null
    );
    $tip['has_photo'] = tipHasPhotoFlag($tip);
    if (!array_key_exists('photo_data', $tip)) {
        $tip['photo_data'] = null;
    }
    return $tip;
}
