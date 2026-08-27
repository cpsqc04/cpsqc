<?php

/**
 * Fixed BPSO duty shift definitions and patrol time helpers.
 */

const PATROL_SHIFT_DAY = 'Day Shift';
const PATROL_SHIFT_NIGHT = 'Night Shift';
const PATROL_SHIFT_DAY_START = '08:00';
const PATROL_SHIFT_DAY_END = '20:00';
const PATROL_SHIFT_NIGHT_START = '20:00';
const PATROL_SHIFT_NIGHT_END = '08:00';

function patrolShiftOptions(): array
{
    return [PATROL_SHIFT_DAY, PATROL_SHIFT_NIGHT];
}

function isValidPatrolShift(?string $shift): bool
{
    return in_array(trim((string) $shift), patrolShiftOptions(), true);
}

/**
 * Human-readable shift with fixed duty hours, e.g. "Day Shift (8:00 AM – 8:00 PM)".
 */
function formatPatrolShiftLabel(?string $shift): string
{
    $normalized = trim((string) $shift);
    if (strcasecmp($normalized, PATROL_SHIFT_DAY) === 0 || stripos($normalized, 'day') !== false) {
        return PATROL_SHIFT_DAY . ' (8:00 AM – 8:00 PM)';
    }
    if (strcasecmp($normalized, PATROL_SHIFT_NIGHT) === 0 || stripos($normalized, 'night') !== false) {
        return PATROL_SHIFT_NIGHT . ' (8:00 PM – 8:00 AM)';
    }

    return $normalized !== '' ? $normalized : '—';
}

function normalizePatrolTime(?string $time): string
{
    $time = trim((string) $time);
    if ($time === '') {
        return '';
    }

    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        $time .= ':00';
    }

    $dt = DateTime::createFromFormat('H:i:s', $time);
    if (!$dt) {
        $dt = DateTime::createFromFormat('g:i A', strtoupper($time));
    }
    if (!$dt) {
        $dt = DateTime::createFromFormat('H:i', substr($time, 0, 5));
    }

    return $dt ? $dt->format('H:i:s') : '';
}

function patrolTimeToMinutes(string $time): int
{
    $normalized = normalizePatrolTime($time);
    if ($normalized === '') {
        return 0;
    }

    [$hour, $minute] = array_map('intval', explode(':', $normalized));

    return ($hour * 60) + $minute;
}

function patrolMinutesToTime(int $minutes): string
{
    $minutes = max(0, $minutes) % (24 * 60);
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;

    return sprintf('%02d:%02d:00', $hour, $minute);
}

function buildPatrolDateTimeRange(string $scheduleDate, string $startTime, string $endTime): array
{
    $start = new DateTime($scheduleDate . ' ' . normalizePatrolTime($startTime));
    $end = new DateTime($scheduleDate . ' ' . normalizePatrolTime($endTime));

    if ($end <= $start) {
        $end->modify('+1 day');
    }

    return ['start' => $start, 'end' => $end];
}

function calculatePatrolDurationMinutes(string $scheduleDate, string $startTime, string $endTime): int
{
    $startTime = normalizePatrolTime($startTime);
    $endTime = normalizePatrolTime($endTime);
    if ($startTime === '' || $endTime === '') {
        return 0;
    }

    $range = buildPatrolDateTimeRange($scheduleDate, $startTime, $endTime);
    $seconds = $range['end']->getTimestamp() - $range['start']->getTimestamp();

    return max(0, (int) round($seconds / 60));
}

function formatPatrolDurationLabel(?int $minutes, ?string $status = null): string
{
    if ($minutes === null || $minutes <= 0) {
        if ($status === 'In Progress') {
            return 'In progress';
        }
        if ($status === 'Scheduled') {
            return '—';
        }

        return $status === 'Completed' ? '—' : '—';
    }

    if ($minutes < 60) {
        return $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    if ($remaining === 0) {
        return $hours === 1 ? '1 Hour' : $hours . ' Hours';
    }

    return $hours . 'h ' . $remaining . 'm';
}

function formatPatrolTimeDisplay(?string $time): string
{
    $normalized = normalizePatrolTime($time ?? '');
    if ($normalized === '') {
        return '—';
    }

    $dt = DateTime::createFromFormat('H:i:s', $normalized);

    return $dt ? $dt->format('g:i A') : '—';
}

function formatHallDurationLabel(?string $timeIn, ?string $timeOut): string
{
    if (!$timeIn) {
        return '—';
    }

    if (!$timeOut) {
        return 'In progress';
    }

    $start = new DateTime(str_replace(' ', 'T', $timeIn));
    $end = new DateTime(str_replace(' ', 'T', $timeOut));
    $minutes = max(0, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));

    return formatPatrolDurationLabel($minutes);
}

/**
 * Manila "now" for shift deadline checks.
 */
function manilaNow(): DateTime
{
    return new DateTime('now', new DateTimeZone('Asia/Manila'));
}

/**
 * Start datetime of a patrol duty shift for the given schedule date.
 */
function getPatrolShiftStartDateTime(string $scheduleDate, string $shift): ?DateTime
{
    $shift = trim($shift);
    $tz = new DateTimeZone('Asia/Manila');

    if ($shift === PATROL_SHIFT_DAY) {
        return new DateTime($scheduleDate . ' ' . normalizePatrolTime(PATROL_SHIFT_DAY_START), $tz);
    }

    if ($shift === PATROL_SHIFT_NIGHT) {
        return new DateTime($scheduleDate . ' ' . normalizePatrolTime(PATROL_SHIFT_NIGHT_START), $tz);
    }

    return null;
}

/**
 * Whether the assigned shift window has started.
 */
function hasPatrolShiftStarted(string $scheduleDate, string $shift, ?DateTime $now = null): bool
{
    $start = getPatrolShiftStartDateTime($scheduleDate, $shift);
    if (!$start) {
        return false;
    }

    $now = $now ?? manilaNow();

    return $now >= $start;
}

/**
 * Normalize free-text duty labels to Day Shift / Night Shift.
 */
function normalizePatrolShiftName(?string $shift): string
{
    $normalized = trim((string) $shift);
    if ($normalized === '') {
        return '';
    }

    if (strcasecmp($normalized, PATROL_SHIFT_NIGHT) === 0 || stripos($normalized, 'night') !== false) {
        return PATROL_SHIFT_NIGHT;
    }

    if (strcasecmp($normalized, PATROL_SHIFT_DAY) === 0 || stripos($normalized, 'day') !== false) {
        return PATROL_SHIFT_DAY;
    }

    return '';
}

/**
 * Schedule date for a clock-on + duty shift.
 * Night sessions before 08:00 belong to the previous calendar day's Night Shift.
 */
function resolvePatrolShiftScheduleDateFromClockOn(string $timeIn, ?string $dutyShift): ?string
{
    $shift = normalizePatrolShiftName($dutyShift);
    if ($shift === '') {
        return null;
    }

    try {
        $tz = new DateTimeZone('Asia/Manila');
        $dt = new DateTime(str_replace(' ', 'T', $timeIn), $tz);
        if ($shift === PATROL_SHIFT_NIGHT && (int) $dt->format('G') < 8) {
            $dt->modify('-1 day');
        }

        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * End datetime of a patrol duty shift for the given schedule date.
 */
function getPatrolShiftEndDateTime(string $scheduleDate, string $shift): ?DateTime
{
    $shift = normalizePatrolShiftName($shift);
    $tz = new DateTimeZone('Asia/Manila');

    if ($shift === PATROL_SHIFT_DAY) {
        return new DateTime($scheduleDate . ' ' . normalizePatrolTime(PATROL_SHIFT_DAY_END), $tz);
    }

    if ($shift === PATROL_SHIFT_NIGHT) {
        $end = new DateTime($scheduleDate . ' ' . normalizePatrolTime(PATROL_SHIFT_NIGHT_END), $tz);
        $end->modify('+1 day');
        return $end;
    }

    return null;
}

/**
 * Minutes past official shift end (Day 8:00 PM / Night 8:00 AM). Null if inputs invalid.
 */
function computeOvertimeMinutesPastShiftEnd(?string $timeIn, ?string $timeOut, ?string $dutyShift): ?int
{
    if (!$timeIn) {
        return null;
    }

    $scheduleDate = resolvePatrolShiftScheduleDateFromClockOn($timeIn, $dutyShift);
    if (!$scheduleDate) {
        return null;
    }

    $shiftEnd = getPatrolShiftEndDateTime($scheduleDate, (string) $dutyShift);
    if (!$shiftEnd) {
        return null;
    }

    try {
        $tz = new DateTimeZone('Asia/Manila');
        $end = $timeOut
            ? new DateTime(str_replace(' ', 'T', $timeOut), $tz)
            : manilaNow();
    } catch (Exception $e) {
        return null;
    }

    return max(0, (int) round(($end->getTimestamp() - $shiftEnd->getTimestamp()) / 60));
}

/**
 * Whether the assigned shift window has fully ended.
 */
function hasPatrolShiftEnded(string $scheduleDate, string $shift, ?DateTime $now = null): bool
{
    $end = getPatrolShiftEndDateTime($scheduleDate, $shift);
    if (!$end) {
        return false;
    }

    $now = $now ?? manilaNow();

    return $now >= $end;
}
