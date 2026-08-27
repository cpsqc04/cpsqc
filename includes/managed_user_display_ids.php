<?php

function formatAdminDisplayId(int $sequence): string
{
    return 'Admin - ' . max(1, $sequence);
}

function formatBpsoDisplayId(int $sequence): string
{
    return 'PAT - ' . str_pad((string) max(1, $sequence), 2, '0', STR_PAD_LEFT);
}

function formatNwMemberDisplayId(int $sequence): string
{
    return 'NW-' . str_pad((string) max(1, $sequence), 2, '0', STR_PAD_LEFT);
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, int>
 */
function buildSequentialDisplayIdMap(array $rows, string $idKey = 'id'): array
{
    $sorted = $rows;
    usort($sorted, static function (array $a, array $b) use ($idKey): int {
        return ((int) ($a[$idKey] ?? 0)) <=> ((int) ($b[$idKey] ?? 0));
    });

    $map = [];
    $sequence = 1;
    foreach ($sorted as $row) {
        $map[(int) ($row[$idKey] ?? 0)] = $sequence++;
    }

    return $map;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, string>
 */
function buildAdminDisplayIdMap(array $rows): array
{
    $sequenceMap = buildSequentialDisplayIdMap($rows);
    $displayMap = [];
    foreach ($sequenceMap as $id => $sequence) {
        $displayMap[$id] = formatAdminDisplayId($sequence);
    }

    return $displayMap;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, string>
 */
function buildBpsoDisplayIdMap(array $rows): array
{
    $sequenceMap = buildSequentialDisplayIdMap($rows);
    $displayMap = [];
    foreach ($sequenceMap as $id => $sequence) {
        $displayMap[$id] = formatBpsoDisplayId($sequence);
    }

    return $displayMap;
}

/**
 * @param array<int, array<string, mixed>> $rows rows with at least id + status
 * @return array<int, string>
 */
function buildNwMemberDisplayIdMap(array $rows): array
{
    $activeRows = array_values(array_filter($rows, static function (array $row): bool {
        return ($row['status'] ?? '') === 'Active';
    }));

    $sequenceMap = buildSequentialDisplayIdMap($activeRows);
    $displayMap = [];
    foreach ($sequenceMap as $id => $sequence) {
        $displayMap[$id] = formatNwMemberDisplayId($sequence);
    }

    return $displayMap;
}

function resolveNwMemberDisplayCode(PDO $pdo, int $memberId): string
{
    $stmt = $pdo->query('SELECT id, status FROM nw_members ORDER BY id ASC');
    $displayMap = buildNwMemberDisplayIdMap($stmt->fetchAll(PDO::FETCH_ASSOC));

    return $displayMap[$memberId] ?? formatNwMemberDisplayId(max(1, count($displayMap) + 1));
}

function syncBpsoPersonnelIdsToPatFormat(PDO $pdo): void
{
    require_once __DIR__ . '/public_id.php';

    if (getAppMeta($pdo, 'bpso_personnel_ids_pat_v1') === '1') {
        return;
    }

    try {
        $stmt = $pdo->query('SELECT id FROM patrols ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            setAppMeta($pdo, 'bpso_personnel_ids_pat_v1', '1');
            return;
        }

        $displayMap = buildBpsoDisplayIdMap($rows);
        $updatePatrol = $pdo->prepare('UPDATE patrols SET bpso_personnel_id = :code WHERE id = :id');
        $updateAttendance = $pdo->prepare('UPDATE bpso_attendance SET bpso_personnel_id = :code WHERE patrol_id = :id');

        foreach ($displayMap as $id => $code) {
            $updatePatrol->execute([':code' => $code, ':id' => $id]);
            try {
                $updateAttendance->execute([':code' => $code, ':id' => $id]);
            } catch (PDOException $e) {
                // Attendance table may not exist yet.
            }
        }

        setAppMeta($pdo, 'bpso_personnel_ids_pat_v1', '1');
    } catch (Throwable $e) {
        // Best-effort migration only.
    }
}

function syncNwMemberCodesToDisplayIds(PDO $pdo): void
{
    require_once __DIR__ . '/public_id.php';

    if (getAppMeta($pdo, 'nw_member_codes_display_v1') === '1') {
        return;
    }

    try {
        $stmt = $pdo->query('SELECT id, status FROM nw_members ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $displayMap = buildNwMemberDisplayIdMap($rows);
        $update = $pdo->prepare('UPDATE nw_members SET member_code = :code WHERE id = :id');

        foreach ($displayMap as $id => $code) {
            $update->execute([':code' => $code, ':id' => $id]);
        }

        setAppMeta($pdo, 'nw_member_codes_display_v1', '1');
    } catch (Throwable $e) {
        // Best-effort migration only.
    }
}

function generateNextBpsoPersonnelId(PDO $pdo): string
{
    syncBpsoPersonnelIdsToPatFormat($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM patrols')->fetchColumn();

    return formatBpsoDisplayId($count + 1);
}
