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
