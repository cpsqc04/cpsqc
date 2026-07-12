<?php

/**
 * Neighborhood Watch members table schema and migration helpers.
 * Legacy table name was `volunteers`; data is preserved via RENAME TABLE.
 */

function nwMembersTableName(): string
{
    return 'nw_members';
}

function nwMembersTableExists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM nw_members LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function volunteersLegacyTableExists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM volunteers LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function migrateVolunteersTableToNwMembers(PDO $pdo): void
{
    if (volunteersLegacyTableExists($pdo) && !nwMembersTableExists($pdo)) {
        $pdo->exec('RENAME TABLE volunteers TO nw_members');
    }
}

/**
 * Remove unused legacy `members` table (superseded by nw_members).
 */
function dropLegacyMembersTable(PDO $pdo): void
{
    try {
        $pdo->query('SELECT 1 FROM members LIMIT 1');
        $pdo->exec('DROP TABLE members');
    } catch (PDOException $e) {
        // Table already removed or never created.
    }
}

/**
 * Ensure the nw_members table (and required columns) exist in the database.
 */
function ensureNwMembersTable(PDO $pdo): void
{
    migrateVolunteersTableToNwMembers($pdo);
    dropLegacyMembersTable($pdo);

    $table = nwMembersTableName();
    $columns = [];
    $tableExists = false;

    try {
        foreach ($pdo->query("SHOW COLUMNS FROM {$table}") as $row) {
            $columns[$row['Field']] = true;
            $tableExists = true;
        }
    } catch (PDOException $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        $pdo->exec("CREATE TABLE {$table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            category VARCHAR(100) NOT NULL,
            skills TEXT NOT NULL,
            availability VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            notes TEXT DEFAULT NULL,
            photo_data LONGTEXT NULL,
            photo_id_data LONGTEXT NULL,
            certifications_data LONGTEXT NULL,
            certifications_description TEXT DEFAULT NULL,
            emergency_contact_name VARCHAR(255) NOT NULL,
            emergency_contact_number VARCHAR(50) NOT NULL,
            member_code VARCHAR(50) NULL DEFAULT NULL,
            password_hash VARCHAR(255) NULL DEFAULT NULL,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }

    $alterations = [
        'name' => "ALTER TABLE {$table} ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT '' AFTER id",
        'contact' => "ALTER TABLE {$table} ADD COLUMN contact VARCHAR(50) NOT NULL DEFAULT '' AFTER name",
        'email' => "ALTER TABLE {$table} ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER contact",
        'address' => "ALTER TABLE {$table} ADD COLUMN address TEXT NOT NULL DEFAULT '' AFTER email",
        'category' => "ALTER TABLE {$table} ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT '' AFTER address",
        'skills' => "ALTER TABLE {$table} ADD COLUMN skills TEXT NOT NULL DEFAULT '' AFTER category",
        'availability' => "ALTER TABLE {$table} ADD COLUMN availability VARCHAR(50) NOT NULL DEFAULT 'Flexible' AFTER skills",
        'status' => "ALTER TABLE {$table} ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER availability",
        'notes' => "ALTER TABLE {$table} ADD COLUMN notes TEXT DEFAULT NULL AFTER status",
        'photo_data' => "ALTER TABLE {$table} ADD COLUMN photo_data LONGTEXT NULL AFTER notes",
        'photo_id_data' => "ALTER TABLE {$table} ADD COLUMN photo_id_data LONGTEXT NULL AFTER photo_data",
        'certifications_data' => "ALTER TABLE {$table} ADD COLUMN certifications_data LONGTEXT NULL AFTER photo_id_data",
        'certifications_description' => "ALTER TABLE {$table} ADD COLUMN certifications_description TEXT DEFAULT NULL AFTER certifications_data",
        'emergency_contact_name' => "ALTER TABLE {$table} ADD COLUMN emergency_contact_name VARCHAR(255) NOT NULL DEFAULT '' AFTER certifications_description",
        'emergency_contact_number' => "ALTER TABLE {$table} ADD COLUMN emergency_contact_number VARCHAR(50) NOT NULL DEFAULT '' AFTER emergency_contact_name",
        'created_at' => "ALTER TABLE {$table} ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'member_code' => "ALTER TABLE {$table} ADD COLUMN member_code VARCHAR(50) NULL DEFAULT NULL AFTER emergency_contact_number",
        'password_hash' => "ALTER TABLE {$table} ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL AFTER member_code",
        'must_change_password' => "ALTER TABLE {$table} ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash",
    ];

    foreach ($alterations as $column => $sql) {
        if (!isset($columns[$column])) {
            $pdo->exec($sql);
        }
    }

    if (isset($columns['volunteer_code'])) {
        try {
            $indexes = [];
            foreach ($pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = 'volunteer_code'") as $row) {
                $indexes[] = $row['Key_name'];
            }
            if (!empty($indexes)) {
                $pdo->exec("ALTER TABLE {$table} DROP INDEX volunteer_code");
            }
        } catch (PDOException $e) {
            // Index might not exist.
        }

        try {
            $pdo->exec("ALTER TABLE {$table} MODIFY COLUMN volunteer_code VARCHAR(50) NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already be nullable.
        }
    }
}

/** @deprecated Use ensureNwMembersTable() */
function ensureVolunteersTable(PDO $pdo): void
{
    ensureNwMembersTable($pdo);
}
