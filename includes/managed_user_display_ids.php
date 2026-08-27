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
