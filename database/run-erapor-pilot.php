<?php

/**
 * Private cPanel Cron entry point for the additive eRapor pilot schema and
 * official rubric seeds. This file must only be invoked by PHP CLI/Cron.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

$root = dirname(__DIR__);
$migrationFiles = [
    '20260924_erapor_catalog.sql',
    '20260924_erapor_rts.sql',
    '20260924_erapor_ummi_ppi.sql',
    '20260924_erapor_bing.sql',
    '20260924_erapor_agama.sql',
    '20260924_erapor_sessions.sql',
    '20260924_erapor_rts_values.sql',
    '20260924_erapor_bing_ppi_values.sql',
    '20260924_erapor_agama_values.sql',
    '20260924_erapor_ummi_values.sql',
    '20260924_erapor_approval_flow.sql',
    '20260924_erapor_reception.sql',
    '20260924_erapor_approval_actions.sql',
    '20260924_erapor_extensions.sql',
    '20260925_erapor_approval_assignment_audit.sql',
    '20260925_erapor_signer_profile_audit.sql',
    '20260925_erapor_publication_artifact.sql',
];

require_once $root . '/app/models/EraporMigrationRunner.php';
require_once $root . '/app/models/EraporRtsSeed.php';
require_once $root . '/app/models/EraporUmmiPpiSeed.php';
require_once $root . '/app/models/EraporBingSeed.php';
require_once $root . '/app/models/EraporAgamaSeed.php';

function eraporPilotSeedPaths(string $root): array
{
    return [
        'RTS' => [$root . '/eRapor_Zivana_Spesifikasi/rubrik_rts_seed.json', EraporRtsSeed::class],
        'UMMI' => [$root . '/eRapor_Zivana_Spesifikasi/rubrik_ummi_seed.json', EraporUmmiPpiSeed::class],
        'PPI' => [$root . '/eRapor_Zivana_Spesifikasi/rubrik_ppi_seed.json', EraporUmmiPpiSeed::class],
        'BING' => [$root . '/eRapor_Zivana_Spesifikasi/rubrik_bing_seed.json', EraporBingSeed::class],
        'AGAMA' => [$root . '/eRapor_Zivana_Spesifikasi/rubrik_agama_seed.json', EraporAgamaSeed::class],
    ];
}

function eraporPilotLoadSeeds(string $root): array
{
    $seeds = [];
    foreach (eraporPilotSeedPaths($root) as $kind => [$file, $class]) {
        $seeds[$kind] = $class::load($file);
    }
    return $seeds;
}

function eraporPilotPlans(string $root, array $files): array
{
    $plans = [];
    foreach ($files as $file) {
        $plans[] = EraporMigrationRunner::plan($root . '/database/migrations/' . $file);
    }
    return $plans;
}

function eraporPilotPrintPlan(string $root, array $files): void
{
    $seeds = eraporPilotLoadSeeds($root);
    $plans = eraporPilotPlans($root, $files);
    $migrations = [];
    foreach ($plans as $plan) {
        $migrations[] = [
            'migration' => $plan['id'],
            'sha256' => $plan['sha256'],
            'operations' => array_keys($plan['steps']),
        ];
    }
    $rubrics = [];
    foreach ($seeds as $kind => $seed) {
        $rubrics[] = ['kind' => $kind, 'code' => $seed['rubrik']['kode']];
    }
    echo json_encode([
        'mode' => 'plan-only',
        'migrations' => $migrations,
        'rubric_seeds' => $rubrics,
        'approval_setup_seed' => '20260925_erapor_approval_setup.sql',
        'database_connected' => false,
        'writes_performed' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

function eraporPilotRequireSupportedDatabase(PDO $db): string
{
    $info = $db->query('SELECT VERSION() AS version, @@version_comment AS comment')->fetch(PDO::FETCH_ASSOC);
    $version = (string)($info['version'] ?? '');
    $comment = (string)($info['comment'] ?? '');
    if (stripos($version . ' ' . $comment, 'mariadb') !== false) {
        if (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)
            || version_compare($matches[1], '10.2.1', '<')) {
            throw new RuntimeException('MariaDB 10.2.1 or newer is required to enforce the schema CHECK constraints.');
        }
    } else {
        if (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)
            || version_compare($matches[1], '8.0.16', '<')) {
            throw new RuntimeException('MySQL 8.0.16 or newer is required to enforce the schema CHECK constraints.');
        }
    }
    return $version . ' (' . $comment . ')';
}

function eraporPilotCheckParentTables(PDO $db): void
{
    $required = ['murid', 'tahun_ajaran', 'periode_penilaian', 'kelas', 'users', 'permissions'];
    $marks = implode(',', array_fill(0, count($required), '?'));
    $query = $db->prepare('SELECT TABLE_NAME, ENGINE FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name IN (' . $marks . ')');
    $query->execute($required);
    $found = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $found[$row['TABLE_NAME']] = strtoupper((string)$row['ENGINE']);
    }
    $missing = array_values(array_diff($required, array_keys($found)));
    if ($missing) {
        throw new RuntimeException('Required application tables are missing: ' . implode(', ', $missing));
    }
    foreach ($required as $table) {
        if ($found[$table] !== 'INNODB') {
            throw new RuntimeException('Required table must use InnoDB: ' . $table);
        }
    }

    $idTables = ['murid', 'tahun_ajaran', 'periode_penilaian', 'kelas', 'users'];
    $idMarks = implode(',', array_fill(0, count($idTables), '?'));
    $query = $db->prepare("SELECT TABLE_NAME, COLUMN_TYPE FROM information_schema.columns
        WHERE table_schema=DATABASE() AND column_name='id' AND table_name IN ($idMarks)");
    $query->execute($idTables);
    $idTypes = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idTypes[$row['TABLE_NAME']] = strtolower((string)$row['COLUMN_TYPE']);
    }
    foreach ($idTables as $table) {
        if (!isset($idTypes[$table]) || !preg_match('/^int(?:\(\d+\))?$/', $idTypes[$table])) {
            throw new RuntimeException('Expected signed INT primary key on ' . $table . '.id');
        }
    }

    $permissionColumns = ['modul', 'section', 'sub_section', 'aksi', 'display_order'];
    $marks = implode(',', array_fill(0, count($permissionColumns), '?'));
    $query = $db->prepare('SELECT column_name FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name="permissions" AND column_name IN (' . $marks . ')');
    $query->execute($permissionColumns);
    $columns = $query->fetchAll(PDO::FETCH_COLUMN);
    $missingColumns = array_values(array_diff($permissionColumns, $columns));
    if ($missingColumns) {
        throw new RuntimeException('The permissions table is missing expected columns: ' . implode(', ', $missingColumns));
    }
}

function eraporPilotCheckMigrationState(PDO $db, array $plans): void
{
    $query = $db->prepare('SELECT table_name FROM information_schema.tables
        WHERE table_schema=DATABASE() AND LEFT(table_name,7)=?');
    $query->execute(['erapor_']);
    $existingTables = array_fill_keys($query->fetchAll(PDO::FETCH_COLUMN), true);

    $expectedTables = ['erapor_migrations' => true];
    $expectedColumns = [];
    $expectedMigrations = [];
    foreach ($plans as $plan) {
        $expectedMigrations[$plan['id']] = $plan['sha256'];
        foreach (array_keys($plan['steps']) as $target) {
            if (str_contains($target, '.')) {
                [$table, $column] = explode('.', $target, 2);
                $expectedColumns[$table][$column] = true;
                $expectedTables[$table] = true;
            } else {
                $expectedTables[$target] = true;
            }
        }
    }
    $unknown = array_values(array_diff(array_keys($existingTables), array_keys($expectedTables)));
    if ($unknown) {
        throw new RuntimeException('Unrecognized erapor_ tables exist; stop for manual inspection: ' . implode(', ', $unknown));
    }

    $ledgerExists = isset($existingTables['erapor_migrations']);
    $ledger = [];
    if ($ledgerExists) {
        $rows = $db->query('SELECT id,sha256,status FROM erapor_migrations')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!isset($expectedMigrations[$row['id']])) {
                throw new RuntimeException('Unrecognized migration ledger entry; stop for manual inspection.');
            }
            if (!hash_equals($expectedMigrations[$row['id']], (string)$row['sha256'])) {
                throw new RuntimeException('Migration checksum mismatch for ' . $row['id'] . '.');
            }
            if ($row['status'] !== 'complete') {
                throw new RuntimeException('Migration ' . $row['id'] . ' is not complete; do not retry automatically.');
            }
            $ledger[$row['id']] = true;
        }
    }

    if (!$ledgerExists && count($existingTables) > 0) {
        throw new RuntimeException('eRapor tables exist without a migration ledger; stop for manual inspection.');
    }

    foreach ($plans as $plan) {
        $isComplete = isset($ledger[$plan['id']]);
        foreach (array_keys($plan['steps']) as $target) {
            if (str_contains($target, '.')) {
                [$table, $column] = explode('.', $target, 2);
                $query = $db->prepare('SELECT COUNT(*) FROM information_schema.columns
                    WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
                $query->execute([$table, $column]);
            } else {
                $query = $db->prepare('SELECT COUNT(*) FROM information_schema.tables
                    WHERE table_schema=DATABASE() AND table_name=?');
                $query->execute([$target]);
            }
            $exists = (int)$query->fetchColumn() > 0;
            if ($isComplete && !$exists) {
                throw new RuntimeException('Completed migration target is missing: ' . $target);
            }
            if (!$isComplete && $exists) {
                throw new RuntimeException('Untracked migration target already exists: ' . $target);
            }
        }
    }
}

function eraporPilotApplyApprovalSetup(PDO $db, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Approval setup seed file is unavailable.');
    }
    $sql = preg_replace('/^\s*--[^\n]*(?:\n|$)/m', '', $sql);
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    if (!$statements) {
        throw new RuntimeException('Approval setup seed is empty.');
    }
    foreach ($statements as $statement) {
        if (!preg_match('/^INSERT(?:\s+IGNORE)?\s+INTO\s+(?:permissions|erapor_alur_penyetuju|erapor_alur_dokumen)\b/i', $statement)) {
            throw new RuntimeException('Approval setup seed contains an unreviewed statement.');
        }
    }
    $db->beginTransaction();
    try {
        foreach ($statements as $statement) {
            $db->exec($statement);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

$mode = $argv[1] ?? '--help';
if ($mode === '--plan') {
    try {
        eraporPilotPrintPlan($root, $migrationFiles);
    } catch (Throwable $error) {
        fwrite(STDERR, '[eRapor plan] Failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (!in_array($mode, ['--check', '--apply-production-schema-only'], true)) {
    fwrite(STDERR, "Usage:\n  php database/run-erapor-pilot.php --plan\n  php database/run-erapor-pilot.php --check\n  php database/run-erapor-pilot.php --apply-production-schema-only\n");
    exit($mode === '--help' ? 0 : 64);
}

$stage = 'bootstrap';
try {
    define('ROOT_PATH', $root);
    define('CONFIG_PATH', ROOT_PATH . '/config');
    require ROOT_PATH . '/config/config.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (ERAPOR_API_ENABLED) {
        throw new RuntimeException('ERAPOR_API_ENABLED must remain false during migration.');
    }

    require_once ROOT_PATH . '/app/core/Database.php';
    $db = Database::getInstance();
    $plans = eraporPilotPlans($root, $migrationFiles);
    $seeds = eraporPilotLoadSeeds($root);
    $server = eraporPilotRequireSupportedDatabase($db);
    eraporPilotCheckParentTables($db);
    eraporPilotCheckMigrationState($db, $plans);

    echo '[eRapor] Preflight passed; database server: ' . $server . PHP_EOL;
    echo '[eRapor] 17 additive migrations; legacy report data will not be converted or modified.' . PHP_EOL;
    if ($mode === '--check') {
        echo '[eRapor] Check-only mode: no writes performed.' . PHP_EOL;
        exit(0);
    }

    $databaseName = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'erapor_deploy_' . substr(hash('sha256', $databaseName), 0, 42);
    $lockQuery = $db->prepare('SELECT GET_LOCK(?,0)');
    $lockQuery->execute([$lockName]);
    if ((int)$lockQuery->fetchColumn() !== 1) {
        throw new RuntimeException('Another eRapor deployment job is running.');
    }

    try {
        foreach ($migrationFiles as $file) {
            $stage = 'migration ' . pathinfo($file, PATHINFO_FILENAME);
            $result = EraporMigrationRunner::apply($db, ROOT_PATH . '/database/migrations/' . $file);
            echo '[eRapor] ' . $stage . ': ' . $result . PHP_EOL;
        }

        foreach (['RTS', 'UMMI', 'PPI', 'BING', 'AGAMA'] as $kind) {
            $stage = 'rubric seed ' . $kind;
            [, $class] = eraporPilotSeedPaths($root)[$kind];
            $result = $class::apply($db, $seeds[$kind]);
            echo '[eRapor] ' . $stage . ': ' . $result . PHP_EOL;
        }

        $stage = 'approval setup seed';
        eraporPilotApplyApprovalSetup($db, ROOT_PATH . '/database/seeds/20260925_erapor_approval_setup.sql');

        $migrationCount = (int)$db->query("SELECT COUNT(*) FROM erapor_migrations WHERE status='complete'")->fetchColumn();
        $rubricCount = (int)$db->query('SELECT COUNT(*) FROM erapor_rubrik')->fetchColumn();
        $flowCount = (int)$db->query('SELECT COUNT(*) FROM erapor_alur_penyetuju')->fetchColumn();
        $assignmentCount = (int)$db->query('SELECT COUNT(*) FROM erapor_penyetuju_user')->fetchColumn();
        if ($migrationCount !== count($migrationFiles) || $rubricCount !== 5 || $flowCount !== 3) {
            throw new RuntimeException('Post-migration verification counts did not match the pilot baseline.');
        }
        echo '[eRapor] approval setup seed: applied' . PHP_EOL;
        echo '[eRapor] Verified: migrations=' . $migrationCount . ', rubrics=' . $rubricCount
            . ', approval stages=' . $flowCount . ', existing user assignments preserved=' . $assignmentCount . PHP_EOL;
        echo '[eRapor] ERAPOR_API_ENABLED remains false. Remove this Cron Job after capturing the output.' . PHP_EOL;
    } finally {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, '[eRapor] STOP at ' . $stage . ': ' . $error->getMessage() . PHP_EOL);
    fwrite(STDERR, '[eRapor] Do not delete migration ledger rows or rerun after a partial DDL failure; preserve this output for inspection.' . PHP_EOL);
    exit(1);
}
