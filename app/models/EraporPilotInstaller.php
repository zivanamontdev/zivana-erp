<?php

/** One reviewed additive installer shared by CLI and the temporary Admin page. */
final class EraporPilotInstaller
{
    private const MIGRATIONS = [
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
        '20260926_erapor_rts_belum_dikenalkan.sql',
    ];

    private const SEEDS = [
        'RTS' => ['rubrik_rts_seed.json', EraporRtsSeed::class],
        'UMMI' => ['rubrik_ummi_seed.json', EraporUmmiPpiSeed::class],
        'PPI' => ['rubrik_ppi_seed.json', EraporUmmiPpiSeed::class],
        'BING' => ['rubrik_bing_seed.json', EraporBingSeed::class],
        'AGAMA' => ['rubrik_agama_seed.json', EraporAgamaSeed::class],
    ];

    public static function plan(string $root): array
    {
        self::loadDependencies($root);
        $migrations = [];
        foreach (self::MIGRATIONS as $file) {
            $plan = EraporMigrationRunner::plan($root . '/database/migrations/' . $file);
            $migrations[] = [
                'migration' => $plan['id'],
                'sha256' => $plan['sha256'],
                'operations' => array_keys($plan['steps']),
            ];
        }

        $rubrics = [];
        foreach (self::loadSeeds($root) as $kind => $seed) {
            $rubrics[] = ['kind' => $kind, 'code' => $seed['rubrik']['kode']];
        }

        return [
            'mode' => 'plan-only',
            'migrations' => $migrations,
            'rubric_seeds' => $rubrics,
            'approval_setup_seed' => '20260925_erapor_approval_setup.sql',
            'database_connected' => false,
            'writes_performed' => false,
        ];
    }

    public static function preflight(PDO $db, string $root): array
    {
        self::loadDependencies($root);
        if (defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED) {
            throw new RuntimeException('ERAPOR_API_ENABLED must remain false during migration.');
        }
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
            throw new RuntimeException('Installer requires a dedicated MySQL connection outside a transaction.');
        }

        $server = self::requireSupportedDatabase($db);
        self::checkParentTables($db);
        $plans = self::migrationPlans($root);
        $ledger = self::checkMigrationState($db, $plans);
        $rubricCount = self::countIfTableExists($db, 'erapor_rubrik');
        $seedHistoryCount = self::countIfTableExists($db, 'erapor_seed_history');
        $approvalStageCount = self::countIfTableExists($db, 'erapor_alur_penyetuju');
        $assignmentCount = self::countIfTableExists($db, 'erapor_penyetuju_user');
        $complete = count($ledger) === count(self::MIGRATIONS)
            && $rubricCount === 5
            && $seedHistoryCount === 5
            && $approvalStageCount === 3;

        return [
            'server' => $server,
            'migration_count' => count($ledger),
            'migration_total' => count(self::MIGRATIONS),
            'rubric_count' => $rubricCount,
            'seed_history_count' => $seedHistoryCount,
            'approval_stage_count' => $approvalStageCount,
            'assignment_count' => $assignmentCount,
            'complete' => $complete,
        ];
    }

    public static function apply(PDO $db, string $root, ?callable $onProgress = null): array
    {
        self::loadDependencies($root);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
            throw new RuntimeException('Installer requires a dedicated MySQL connection outside a transaction.');
        }

        $databaseName = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'erapor_deploy_' . substr(hash('sha256', $databaseName), 0, 42);
        $lockQuery = $db->prepare('SELECT GET_LOCK(?,0)');
        $lockQuery->execute([$lockName]);
        if ((int)$lockQuery->fetchColumn() !== 1) {
            throw new RuntimeException('Another eRapor installation job is running.');
        }

        $report = [];
        $emit = static function (string $message) use (&$report, $onProgress): void {
            $report[] = $message;
            if ($onProgress !== null) {
                $onProgress($message);
            }
        };

        try {
            $initial = self::preflight($db, $root);
            if ($initial['complete']) {
                return ['already_complete' => true, 'preflight' => $initial, 'steps' => []];
            }

            foreach (self::MIGRATIONS as $file) {
                $id = pathinfo($file, PATHINFO_FILENAME);
                $result = EraporMigrationRunner::apply($db, $root . '/database/migrations/' . $file);
                $emit('Migration ' . $id . ': ' . $result);
            }

            foreach (self::loadSeeds($root) as $kind => $seed) {
                [, $class] = self::SEEDS[$kind];
                $result = $class::apply($db, $seed);
                $emit('Rubric seed ' . $kind . ': ' . $result);
            }

            self::applyApprovalSetup($db, $root . '/database/seeds/20260925_erapor_approval_setup.sql');
            $emit('Approval setup seed: applied');

            $final = self::preflight($db, $root);
            if (!$final['complete']) {
                throw new RuntimeException('Post-migration verification did not match the pilot baseline.');
            }
            $emit('Verified: migrations=' . $final['migration_count']
                . ', rubrics=' . $final['rubric_count']
                . ', approval stages=' . $final['approval_stage_count']
                . ', existing user assignments preserved=' . $final['assignment_count']);
            return ['already_complete' => false, 'preflight' => $final, 'steps' => $report];
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }

    private static function loadDependencies(string $root): void
    {
        foreach ([
            'EraporMigrationRunner.php',
            'EraporRtsSeed.php',
            'EraporUmmiPpiSeed.php',
            'EraporBingSeed.php',
            'EraporAgamaSeed.php',
        ] as $file) {
            require_once $root . '/app/models/' . $file;
        }
    }

    private static function loadSeeds(string $root): array
    {
        self::loadDependencies($root);
        $seeds = [];
        foreach (self::SEEDS as $kind => [$file, $class]) {
            $seeds[$kind] = $class::load($root . '/eRapor_Zivana_Spesifikasi/' . $file);
        }
        return $seeds;
    }

    private static function migrationPlans(string $root): array
    {
        self::loadDependencies($root);
        $plans = [];
        foreach (self::MIGRATIONS as $file) {
            $plans[] = EraporMigrationRunner::plan($root . '/database/migrations/' . $file);
        }
        return $plans;
    }

    private static function requireSupportedDatabase(PDO $db): string
    {
        $info = $db->query('SELECT VERSION() AS version, @@version_comment AS comment')->fetch(PDO::FETCH_ASSOC);
        $version = (string)($info['version'] ?? '');
        $comment = (string)($info['comment'] ?? '');
        if (stripos($version . ' ' . $comment, 'mariadb') !== false) {
            if (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)
                || version_compare($matches[1], '10.2.1', '<')) {
                throw new RuntimeException('MariaDB 10.2.1 or newer is required to enforce schema CHECK constraints.');
            }
        } elseif (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches)
            || version_compare($matches[1], '8.0.16', '<')) {
            throw new RuntimeException('MySQL 8.0.16 or newer is required to enforce schema CHECK constraints.');
        }
        return $version . ' (' . $comment . ')';
    }

    private static function checkParentTables(PDO $db): void
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

    private static function checkMigrationState(PDO $db, array $plans): array
    {
        $query = $db->prepare('SELECT table_name FROM information_schema.tables
            WHERE table_schema=DATABASE() AND LEFT(table_name,7)=?');
        $query->execute(['erapor_']);
        $existingTables = array_fill_keys($query->fetchAll(PDO::FETCH_COLUMN), true);

        $expectedTables = ['erapor_migrations' => true];
        $expectedMigrations = [];
        foreach ($plans as $plan) {
            $expectedMigrations[$plan['id']] = $plan['sha256'];
            foreach (array_keys($plan['steps']) as $target) {
                if (str_contains($target, '.')) {
                    [$table] = explode('.', $target, 2);
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

        $ledger = [];
        $ledgerExists = isset($existingTables['erapor_migrations']);
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
        return $ledger;
    }

    private static function countIfTableExists(PDO $db, string $table): int
    {
        $query = $db->prepare('SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema=DATABASE() AND table_name=?');
        $query->execute([$table]);
        if ((int)$query->fetchColumn() === 0) {
            return 0;
        }
        return (int)$db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    private static function applyApprovalSetup(PDO $db, string $file): void
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
}
