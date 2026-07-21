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
            first_name VARCHAR(120) NULL DEFAULT NULL,
            middle_name VARCHAR(120) NULL DEFAULT NULL,
            last_name VARCHAR(120) NULL DEFAULT NULL,
            gender VARCHAR(50) NULL DEFAULT NULL,
            marital_status VARCHAR(50) NULL DEFAULT NULL,
            contact VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            address_unit_street VARCHAR(255) NULL DEFAULT NULL,
            address_subdivision VARCHAR(255) NULL DEFAULT NULL,
            address_barangay VARCHAR(120) NULL DEFAULT NULL,
            address_city VARCHAR(120) NULL DEFAULT NULL,
            address_postal_code VARCHAR(20) NULL DEFAULT NULL,
            address_country VARCHAR(120) NULL DEFAULT NULL,
            birthday DATE NULL DEFAULT NULL,
            id_number VARCHAR(100) NULL DEFAULT NULL,
            category VARCHAR(100) NOT NULL,
            skills TEXT NOT NULL,
            availability VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Pending',
            notes TEXT DEFAULT NULL,
            photo_data LONGTEXT NULL,
            photo_id_data LONGTEXT NULL,
            barangay_clearance_data LONGTEXT NULL,
            eligibility_answers LONGTEXT NULL,
            rejection_reason VARCHAR(255) NULL DEFAULT NULL,
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
        'first_name' => "ALTER TABLE {$table} ADD COLUMN first_name VARCHAR(120) NULL DEFAULT NULL AFTER name",
        'middle_name' => "ALTER TABLE {$table} ADD COLUMN middle_name VARCHAR(120) NULL DEFAULT NULL AFTER first_name",
        'last_name' => "ALTER TABLE {$table} ADD COLUMN last_name VARCHAR(120) NULL DEFAULT NULL AFTER middle_name",
        'gender' => "ALTER TABLE {$table} ADD COLUMN gender VARCHAR(50) NULL DEFAULT NULL AFTER last_name",
        'marital_status' => "ALTER TABLE {$table} ADD COLUMN marital_status VARCHAR(50) NULL DEFAULT NULL AFTER gender",
        'contact' => "ALTER TABLE {$table} ADD COLUMN contact VARCHAR(50) NOT NULL DEFAULT '' AFTER marital_status",
        'email' => "ALTER TABLE {$table} ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER contact",
        'address' => "ALTER TABLE {$table} ADD COLUMN address TEXT NOT NULL DEFAULT '' AFTER email",
        'address_unit_street' => "ALTER TABLE {$table} ADD COLUMN address_unit_street VARCHAR(255) NULL DEFAULT NULL AFTER address",
        'address_subdivision' => "ALTER TABLE {$table} ADD COLUMN address_subdivision VARCHAR(255) NULL DEFAULT NULL AFTER address_unit_street",
        'address_barangay' => "ALTER TABLE {$table} ADD COLUMN address_barangay VARCHAR(120) NULL DEFAULT NULL AFTER address_subdivision",
        'address_city' => "ALTER TABLE {$table} ADD COLUMN address_city VARCHAR(120) NULL DEFAULT NULL AFTER address_barangay",
        'address_postal_code' => "ALTER TABLE {$table} ADD COLUMN address_postal_code VARCHAR(20) NULL DEFAULT NULL AFTER address_city",
        'address_country' => "ALTER TABLE {$table} ADD COLUMN address_country VARCHAR(120) NULL DEFAULT NULL AFTER address_postal_code",
        'birthday' => "ALTER TABLE {$table} ADD COLUMN birthday DATE NULL DEFAULT NULL AFTER address_country",
        'id_number' => "ALTER TABLE {$table} ADD COLUMN id_number VARCHAR(100) NULL DEFAULT NULL AFTER birthday",
        'category' => "ALTER TABLE {$table} ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT '' AFTER id_number",
        'skills' => "ALTER TABLE {$table} ADD COLUMN skills TEXT NOT NULL DEFAULT '' AFTER category",
        'availability' => "ALTER TABLE {$table} ADD COLUMN availability VARCHAR(50) NOT NULL DEFAULT 'Flexible' AFTER skills",
        'status' => "ALTER TABLE {$table} ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER availability",
        'notes' => "ALTER TABLE {$table} ADD COLUMN notes TEXT DEFAULT NULL AFTER status",
        'photo_data' => "ALTER TABLE {$table} ADD COLUMN photo_data LONGTEXT NULL AFTER notes",
        'photo_id_data' => "ALTER TABLE {$table} ADD COLUMN photo_id_data LONGTEXT NULL AFTER photo_data",
        'barangay_clearance_data' => "ALTER TABLE {$table} ADD COLUMN barangay_clearance_data LONGTEXT NULL AFTER photo_id_data",
        'eligibility_answers' => "ALTER TABLE {$table} ADD COLUMN eligibility_answers LONGTEXT NULL AFTER barangay_clearance_data",
        'rejection_reason' => "ALTER TABLE {$table} ADD COLUMN rejection_reason VARCHAR(255) NULL DEFAULT NULL AFTER eligibility_answers",
        'certifications_data' => "ALTER TABLE {$table} ADD COLUMN certifications_data LONGTEXT NULL AFTER rejection_reason",
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

    // Backfill first/last name from legacy "Last, First Middle" values when missing.
    try {
        $pdo->exec("UPDATE {$table}
            SET
                last_name = TRIM(SUBSTRING_INDEX(name, ',', 1)),
                first_name = TRIM(SUBSTRING_INDEX(TRIM(SUBSTRING(name, LOCATE(',', name) + 1)), ' ', 1)),
                middle_name = NULLIF(TRIM(SUBSTRING(TRIM(SUBSTRING(name, LOCATE(',', name) + 1)), LOCATE(' ', TRIM(SUBSTRING(name, LOCATE(',', name) + 1))) + 1)), '')
            WHERE (first_name IS NULL OR first_name = '')
              AND name LIKE '%,%'");
        $pdo->exec("UPDATE {$table}
            SET first_name = TRIM(SUBSTRING_INDEX(name, ' ', 1)),
                last_name = TRIM(SUBSTRING(name, LOCATE(' ', name) + 1))
            WHERE (first_name IS NULL OR first_name = '')
              AND name NOT LIKE '%,%'
              AND TRIM(name) <> ''
              AND LOCATE(' ', name) > 0");
    } catch (PDOException $e) {
        // Backfill is best-effort.
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
