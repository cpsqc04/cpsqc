<?php

/**
 * Shared schema helpers (column renames, etc.).
 */

function renameTableColumnIfNeeded(PDO $pdo, string $table, string $oldName, string $newName, string $columnDefinitionSql): void
{
    $columns = [];
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $row) {
            $columns[$row['Field']] = true;
        }
    } catch (PDOException $e) {
        return;
    }

    if (isset($columns[$newName]) || !isset($columns[$oldName])) {
        return;
    }

    $safeTable = str_replace('`', '``', $table);
    $safeOld = str_replace('`', '``', $oldName);
    $safeNew = str_replace('`', '``', $newName);
    $pdo->exec(
        "ALTER TABLE `{$safeTable}` CHANGE COLUMN `{$safeOld}` `{$safeNew}` {$columnDefinitionSql}"
    );
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $row) {
            if ($row['Field'] === $column) {
                return true;
            }
        }
    } catch (PDOException $e) {
        return false;
    }

    return false;
}
