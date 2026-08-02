<?php

/**
 * Patrol personnel availability statuses (Patrol List).
 *
 * Recommended labels/colors:
 * - Available (green)
 * - Assigned (blue)
 * - Assigned to Simulation (orange)
 * - On Patrol (yellow)
 * - On Reporting (purple) — post-simulation, awaiting patrol report
 * - Unavailable (red)
 */

function patrolAvailabilityStatuses(): array
{
    return [
        'Available',
        'Assigned',
        'Assigned to Simulation',
        'On Patrol',
        'On Reporting',
        'Unavailable',
    ];
}

function normalizePatrolAvailabilityStatus(?string $status): string
{
    $raw = trim((string) $status);
    $lower = strtolower($raw);

    $map = [
        'available' => 'Available',
        'assigned' => 'Assigned',
        'assigned to simulation' => 'Assigned to Simulation',
        'simulation' => 'Assigned to Simulation',
        'on patrol' => 'On Patrol',
        'on-patrol' => 'On Patrol',
        'on reporting' => 'On Reporting',
        'on-reporting' => 'On Reporting',
        'reporting' => 'On Reporting',
        'unavailable' => 'Unavailable',
        'off duty' => 'Unavailable',
        'off-duty' => 'Unavailable',
        'offduty' => 'Unavailable',
    ];

    if (isset($map[$lower])) {
        return $map[$lower];
    }

    return in_array($raw, patrolAvailabilityStatuses(), true) ? $raw : 'Available';
}

function isValidPatrolAvailabilityStatus(?string $status): bool
{
    return in_array(normalizePatrolAvailabilityStatus($status), patrolAvailabilityStatuses(), true);
}

function setPatrolAvailabilityStatus(PDO $pdo, int $patrolId, string $status): void
{
    if ($patrolId <= 0) {
        return;
    }

    $normalized = normalizePatrolAvailabilityStatus($status);
    $stmt = $pdo->prepare('UPDATE patrols SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $normalized,
        ':id' => $patrolId,
    ]);
}

/**
 * Resolve live availability for a patrol officer.
 * Priority: On Patrol > On Reporting > Unavailable > Assigned to Simulation > Assigned > Available
 */
function resolvePatrolAvailabilityStatus(PDO $pdo, int $patrolId, ?string $storedStatus = null): string
{
    if ($patrolId <= 0) {
        return 'Available';
    }

    if ($storedStatus === null) {
        $stmt = $pdo->prepare('SELECT status FROM patrols WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $patrolId]);
        $storedStatus = (string) ($stmt->fetchColumn() ?: 'Available');
    }
    $stored = normalizePatrolAvailabilityStatus($storedStatus);

    // Active patrol route in progress → On Patrol
    try {
        $inProgress = $pdo->prepare(
            "SELECT COUNT(*) FROM patrol_schedules
             WHERE patrol_id = :patrol_id
               AND status = 'In Progress'"
        );
        $inProgress->execute([':patrol_id' => $patrolId]);
        if ((int) $inProgress->fetchColumn() > 0) {
            return 'On Patrol';
        }
    } catch (PDOException $e) {
        // Table may not exist yet during first boot.
    }

    if ($stored === 'Unavailable') {
        return 'Unavailable';
    }

    // Partner simulation lifecycle sets On Patrol while a drill is ongoing.
    // Preserve it — do not let scheduled routes / stale cleanup overwrite it.
    if ($stored === 'On Patrol') {
        return 'On Patrol';
    }

    // After simulation complete: stay On Reporting until this officer submits a report.
    if ($stored === 'On Reporting') {
        return 'On Reporting';
    }

    if ($stored === 'Assigned to Simulation') {
        return 'Assigned to Simulation';
    }

    // Upcoming / open scheduled patrol assignment
    try {
        $today = date('Y-m-d');
        $scheduled = $pdo->prepare(
            "SELECT COUNT(*) FROM patrol_schedules
             WHERE patrol_id = :patrol_id
               AND status = 'Scheduled'
               AND schedule_date >= :today"
        );
        $scheduled->execute([
            ':patrol_id' => $patrolId,
            ':today' => $today,
        ]);
        if ((int) $scheduled->fetchColumn() > 0) {
            return 'Assigned';
        }
    } catch (PDOException $e) {
        // ignore
    }

    // Assigned to an open complaint
    try {
        $complaint = $pdo->prepare(
            "SELECT COUNT(*) FROM complaints
             WHERE assigned_patrol_id = :patrol_id
               AND status IN ('Pending', 'Processing', 'Assigned', 'In Progress')"
        );
        $complaint->execute([':patrol_id' => $patrolId]);
        if ((int) $complaint->fetchColumn() > 0) {
            return 'Assigned';
        }
    } catch (PDOException $e) {
        // ignore if schema differs
    }

    // Assigned to open NW incident
    try {
        $incident = $pdo->prepare(
            "SELECT COUNT(*) FROM nw_incident_reports
             WHERE assigned_patrol_id = :patrol_id
               AND status IN ('Pending', 'Processing', 'Assigned', 'In Progress', 'Under Review')"
        );
        $incident->execute([':patrol_id' => $patrolId]);
        if ((int) $incident->fetchColumn() > 0) {
            return 'Assigned';
        }
    } catch (PDOException $e) {
        // ignore
    }

    if ($stored === 'Assigned') {
        // Stale Assigned with no live work → Available
        return 'Available';
    }

    return $stored === 'Available' ? 'Available' : $stored;
}

/**
 * Recompute and persist availability status for a patrol officer.
 */
function refreshPatrolAvailabilityStatus(PDO $pdo, int $patrolId): string
{
    $status = resolvePatrolAvailabilityStatus($pdo, $patrolId);
    setPatrolAvailabilityStatus($pdo, $patrolId, $status);
    return $status;
}

/**
 * After a patrol report is submitted, leave On Reporting then re-resolve
 * (may become Available, or Assigned if other live work remains).
 */
function clearOnReportingAfterPatrolReport(PDO $pdo, int $patrolId): string
{
    if ($patrolId <= 0) {
        return 'Available';
    }

    $stmt = $pdo->prepare('SELECT status FROM patrols WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $patrolId]);
    $stored = normalizePatrolAvailabilityStatus((string) ($stmt->fetchColumn() ?: 'Available'));

    if ($stored === 'On Reporting') {
        setPatrolAvailabilityStatus($pdo, $patrolId, 'Available');
    }

    return refreshPatrolAvailabilityStatus($pdo, $patrolId);
}

function patrolAvailabilityStatusCssClass(string $status): string
{
    switch (normalizePatrolAvailabilityStatus($status)) {
        case 'Available':
            return 'status-available';
        case 'Assigned':
            return 'status-assigned';
        case 'Assigned to Simulation':
            return 'status-simulation';
        case 'On Patrol':
            return 'status-on-patrol';
        case 'On Reporting':
            return 'status-on-reporting';
        case 'Unavailable':
            return 'status-unavailable';
        default:
            return 'status-available';
    }
}
