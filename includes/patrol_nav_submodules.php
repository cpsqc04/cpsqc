<?php
/**
 * Shared Patrol Scheduling sidebar links.
 * Set $patrolNavActive to: patrol-list | patrol-schedule | patrol-logs | bpso-attendance | patrol-request
 */
$patrolNavActive = $patrolNavActive ?? '';
?>
<a href="patrol-list.php" class="nav-submodule<?php echo $patrolNavActive === 'patrol-list' ? ' active' : ''; ?>" data-tooltip="Patrol List">
    <span class="nav-submodule-icon"><i class="fas fa-list"></i></span>
    <span class="nav-submodule-text">Patrol List</span>
</a>
<a href="patrol-schedule.php" class="nav-submodule<?php echo $patrolNavActive === 'patrol-schedule' ? ' active' : ''; ?>" data-tooltip="Patrol Schedule">
    <span class="nav-submodule-icon"><i class="fas fa-calendar-alt"></i></span>
    <span class="nav-submodule-text">Patrol Schedule</span>
</a>
<a href="bpso-attendance.php" class="nav-submodule<?php echo $patrolNavActive === 'bpso-attendance' ? ' active' : ''; ?>" data-tooltip="Patrol Attendance">
    <span class="nav-submodule-icon"><i class="fas fa-user-check"></i></span>
    <span class="nav-submodule-text">Patrol Attendance</span>
</a>
<a href="patrol-logs.php" class="nav-submodule<?php echo $patrolNavActive === 'patrol-logs' ? ' active' : ''; ?>" data-tooltip="Patrol Logs">
    <span class="nav-submodule-icon"><i class="fas fa-file"></i></span>
    <span class="nav-submodule-text">Patrol Logs</span>
</a>
<a href="patrol-request.php" class="nav-submodule<?php echo $patrolNavActive === 'patrol-request' ? ' active' : ''; ?>" data-tooltip="Patrol Request">
    <span class="nav-submodule-icon"><i class="fas fa-clipboard-check"></i></span>
    <span class="nav-submodule-text">Patrol Request</span>
</a>
