<?php

require_once __DIR__ . '/schema_helpers.php';

/** @var array<string, true> */
$GLOBALS['_public_id_allowed_columns'] = [
    'tips.tip_id' => true,
    'complaints.complaint_id' => true,
    'nw_incident_reports.report_id' => true,
    'cctv_requests.request_id' => true,
    'patrol_requests.request_id' => true,
    'awareness_events.event_id' => true,
    'awareness_event_reports.report_id' => true,
    'risk_alerts.alert_id' => true,
];

function publicIdAssertAllowed(string $table, string $column): void
{
    $key = $table . '.' . $column;
    if (empty($GLOBALS['_public_id_allowed_columns'][$key])) {
        throw InvalidArgumentException('Unsupported public ID column: ' . $key);
    }
}

function publicIdTableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt !== false && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function publicIdFullPrefix(string $prefix, string $year): string
{
    return rtrim($prefix, '-') . '-' . $year . '-';
}

function publicIdExtractYear(string $value, string $prefix, ?string $dateValue = null): string
{
    $base = preg_quote(rtrim($prefix, '-'), '/');
    if (preg_match('/^' . $base . '-(\d{4})-\d+$/', $value, $matches)) {
        return $matches[1];
    }

    if ($dateValue) {
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y', $timestamp);
        }
    }

    return date('Y');
}

function publicIdMaxSequenceForPrefix(PDO $pdo, string $table, string $column, string $fullPrefix): int
{
    publicIdAssertAllowed($table, $column);

    $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :pattern");
    $stmt->execute([':pattern' => $fullPrefix . '%']);

    $regex = '/^' . preg_quote($fullPrefix, '/') . '(\d+)$/';
    $max = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = (string) ($row[$column] ?? '');
        if (preg_match($regex, $value, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }

    return $max;
}

function generateYearlySequentialId(PDO $pdo, string $table, string $column, string $prefix, int $padLength = 3): string
{
    publicIdAssertAllowed($table, $column);

    $year = date('Y');
    $fullPrefix = publicIdFullPrefix($prefix, $year);
    $next = publicIdMaxSequenceForPrefix($pdo, $table, $column, $fullPrefix) + 1;

    return $fullPrefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
}

function renumberYearlyPublicIds(
    PDO $pdo,
    string $table,
    string $column,
    string $prefix,
    int $padLength = 3,
    string $orderColumn = 'id',
    ?string $dateColumn = null
): array {
    publicIdAssertAllowed($table, $column);

    if (!publicIdTableExists($pdo, $table) || !tableHasColumn($pdo, $table, $column)) {
        return [];
    }

    $select = "SELECT id, {$column}";
    if ($dateColumn !== null && tableHasColumn($pdo, $table, $dateColumn)) {
        $select .= ", {$dateColumn}";
    }
    $select .= " FROM {$table} ORDER BY {$orderColumn} ASC";

    $rows = $pdo->query($select)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return [];
    }

    $byYear = [];
    foreach ($rows as $row) {
        $dateValue = ($dateColumn !== null && isset($row[$dateColumn])) ? (string) $row[$dateColumn] : null;
        $year = publicIdExtractYear((string) ($row[$column] ?? ''), $prefix, $dateValue);
        $byYear[$year][] = $row;
    }

    $oldToNew = [];
    foreach ($byYear as $year => $yearRows) {
        $fullPrefix = publicIdFullPrefix($prefix, $year);
        $num = 1;
        foreach ($yearRows as $row) {
            $newId = $fullPrefix . str_pad((string) $num, $padLength, '0', STR_PAD_LEFT);
            $oldId = (string) ($row[$column] ?? '');
            if ($oldId !== '' && $oldId !== $newId) {
                $oldToNew[$oldId] = $newId;
            }
            $num++;
        }
    }

    if (!$oldToNew) {
        return [];
    }

    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $oldId = (string) ($row[$column] ?? '');
            if (!isset($oldToNew[$oldId])) {
                continue;
            }
            $tempId = '__REN_' . $row['id'] . '__';
            $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = :temp WHERE id = :id");
            $stmt->execute([':temp' => $tempId, ':id' => $row['id']]);
        }

        foreach ($rows as $row) {
            $oldId = (string) ($row[$column] ?? '');
            if (!isset($oldToNew[$oldId])) {
                continue;
            }
            $stmt = $pdo->prepare("UPDATE {$table} SET {$column} = :new WHERE id = :id");
            $stmt->execute([':new' => $oldToNew[$oldId], ':id' => $row['id']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $oldToNew;
}

function ensureAppMetaTable(PDO $pdo): void
{
    if (publicIdTableExists($pdo, 'app_meta')) {
        return;
    }

    $pdo->exec("CREATE TABLE app_meta (
        meta_key VARCHAR(100) PRIMARY KEY,
        meta_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getAppMeta(PDO $pdo, string $key): ?string
{
    ensureAppMetaTable($pdo);
    $stmt = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : (string) $value;
}

function setAppMeta(PDO $pdo, string $key, string $value): void
{
    ensureAppMetaTable($pdo);
    $stmt = $pdo->prepare('INSERT INTO app_meta (meta_key, meta_value) VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function updateNotificationReferencesForIdMap(PDO $pdo, array $oldToNew, string $linkNeedle = ''): void
{
    if (!$oldToNew || !publicIdTableExists($pdo, 'notifications')) {
        return;
    }

    foreach ($oldToNew as $oldId => $newId) {
        if ($oldId === $newId) {
            continue;
        }

        $like = '%' . $oldId . '%';
        $stmt = $pdo->prepare('SELECT id, message, link FROM notifications WHERE message LIKE :like OR link LIKE :like');
        $stmt->execute([':like' => $like]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $message = str_replace($oldId, $newId, (string) ($row['message'] ?? ''));
            $link = str_replace($oldId, $newId, (string) ($row['link'] ?? ''));
            if ($linkNeedle !== '' && $link !== '' && strpos($link, $linkNeedle) === false) {
                continue;
            }

            $update = $pdo->prepare('UPDATE notifications SET message = :message, link = :link WHERE id = :id');
            $update->execute([
                ':message' => $message,
                ':link' => $link !== '' ? $link : null,
                ':id' => $row['id'],
            ]);
        }
    }
}

function ensurePublicIdsRenumbered(PDO $pdo): void
{
    if (getAppMeta($pdo, 'public_ids_renumbered_v1') === '1') {
        return;
    }

    $configs = [
        ['table' => 'tips', 'column' => 'tip_id', 'prefix' => 'TIP-', 'date' => 'submitted_at', 'link' => 'review-tip.php?id='],
        ['table' => 'complaints', 'column' => 'complaint_id', 'prefix' => 'COMP-', 'date' => 'submitted_at', 'link' => 'track-complaint.php?id='],
        ['table' => 'nw_incident_reports', 'column' => 'report_id', 'prefix' => 'NWI-', 'date' => 'created_at', 'link' => 'review-neighborhood-watcher-incidents.php'],
        ['table' => 'cctv_requests', 'column' => 'request_id', 'prefix' => 'CCTV-REQ-', 'date' => 'submitted_at', 'link' => 'cctv-request.php'],
        ['table' => 'patrol_requests', 'column' => 'request_id', 'prefix' => 'PT-REQ-', 'date' => 'submitted_at', 'link' => 'patrol-request.php'],
        ['table' => 'awareness_events', 'column' => 'event_id', 'prefix' => 'EVT-', 'date' => 'created_at', 'link' => 'event-list.php'],
        ['table' => 'awareness_event_reports', 'column' => 'report_id', 'prefix' => 'EVT-RPT-', 'date' => 'created_at', 'link' => 'event-reports.php'],
        ['table' => 'risk_alerts', 'column' => 'alert_id', 'prefix' => 'RISK-', 'date' => 'received_at', 'link' => 'patrol-schedule.php'],
    ];

    foreach ($configs as $config) {
        if (!publicIdTableExists($pdo, $config['table'])) {
            continue;
        }

        $dateColumn = tableHasColumn($pdo, $config['table'], $config['date']) ? $config['date'] : null;
        $map = renumberYearlyPublicIds(
            $pdo,
            $config['table'],
            $config['column'],
            $config['prefix'],
            3,
            'id',
            $dateColumn
        );

        updateNotificationReferencesForIdMap($pdo, $map, $config['link']);
    }

    setAppMeta($pdo, 'public_ids_renumbered_v1', '1');
}
