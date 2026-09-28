<?php

/** Reviewed additive eRapor DDL only; never used by an HTTP route.
 * MySQL DDL auto-commits. Partial applications fail closed for manual inspection.
 */
final class EraporMigrationRunner
{
    public const WALI_KELAS_CHECK="ALTER TABLE erapor_alur_penyetuju ADD CONSTRAINT erapor_alur_kode_v2 CHECK (kode IN ('KOORDINATOR_QURAN','KOORDINATOR_BING','WALI_KELAS','KEPALA_SEKOLAH'))";

    public static function plan(string $file): array
    {
        $sql = file_get_contents($file);
        if ($sql === false) throw new RuntimeException('Migration file unavailable.');
        $sql = str_replace("\r\n", "\n", $sql); // Stable across Git line endings.
        $clean = preg_replace('/^--[^\n]*$/m', '', $sql);
        $steps = [];
        foreach (explode(';', $clean) as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            if (preg_match('/^CREATE TABLE (erapor_[a-z_]+)\s*\(/', $statement, $match)) {
                $key=$match[1];
            } elseif (preg_match('/^ALTER TABLE erapor_sesi ADD COLUMN tanggal_pengesahan DATE NULL$/i', $statement)) {
                // Explicitly allow this one reviewed additive session field; reject arbitrary ALTER clauses.
                $key='erapor_sesi.tanggal_pengesahan';
            } elseif ($statement===self::WALI_KELAS_CHECK) {
                // Reviewed: widen the approval-code CHECK for WALI_KELAS. Target "table#constraint".
                $key='erapor_alur_penyetuju#erapor_alur_kode_v2';
            } else {
                throw new RuntimeException('Only additive eRapor tables and columns are allowed.');
            }
            if (isset($steps[$key])) throw new RuntimeException('Duplicate migration target.');
            $steps[$key] = $statement;
        }
        if (!$steps) throw new RuntimeException('Empty migration.');
        return ['id' => pathinfo($file, PATHINFO_FILENAME), 'sha256' => hash('sha256', $sql), 'steps' => $steps];
    }

    public static function apply(PDO $db, string $file): string
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
            throw new RuntimeException('Requires a dedicated MySQL connection outside a transaction.');
        }
        $plan = self::plan($file);
        $lock = substr('erapor_migration_' . hash('sha256', (string)$db->query('SELECT DATABASE()')->fetchColumn()), 0, 64);
        $get = $db->prepare('SELECT GET_LOCK(?, 0)'); $get->execute([$lock]);
        if ((int)$get->fetchColumn() !== 1) throw new RuntimeException('Another migration is running.');
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS erapor_migrations (
                id VARCHAR(150) PRIMARY KEY, sha256 CHAR(64) NOT NULL,
                status ENUM('applying','complete') NOT NULL,
                started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, completed_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $query = $db->prepare('SELECT sha256,status FROM erapor_migrations WHERE id=?');
            $query->execute([$plan['id']]); $previous = $query->fetch(PDO::FETCH_ASSOC);
            if ($previous) {
                if (!hash_equals($previous['sha256'], $plan['sha256'])) throw new RuntimeException('Migration checksum changed; create a new migration.');
                if ($previous['status'] !== 'complete') throw new RuntimeException('Partial migration requires manual inspection.');
                foreach ($plan['steps'] as $target => $_) {
                    if (!self::targetExists($db,$target) && str_contains($target,'#')) throw new RuntimeException('Completed migration constraint is missing.');
                    if (str_contains($target,'#')) continue;
                    if (str_contains($target,'.')) {
                        [$table,$column]=explode('.',$target,2);
                        if (!self::columnExists($db,$table,$column)) throw new RuntimeException('Completed migration column is missing.');
                    } elseif (!self::tableExists($db, $target)) {
                        throw new RuntimeException('Completed migration table is missing.');
                    }
                }
                return 'already_applied';
            }
            foreach ($plan['steps'] as $target => $_) {
                if (str_contains($target,'#')) {
                    if (self::targetExists($db,$target)) throw new RuntimeException('Untracked constraint collision: '.$target);
                    continue;
                }
                if (str_contains($target,'.')) {
                    [$table,$column]=explode('.',$target,2);
                    if (self::columnExists($db,$table,$column)) throw new RuntimeException('Untracked column collision: '.$target);
                } elseif (self::tableExists($db, $target)) {
                    throw new RuntimeException('Untracked table collision: ' . $target);
                }
            }
            $db->prepare("INSERT INTO erapor_migrations(id,sha256,status) VALUES(?,?,'applying')")
                ->execute([$plan['id'], $plan['sha256']]);
            foreach ($plan['steps'] as $target => $statement) {
                // CHECK lama tanpa nama (MySQL: *_chk_N, MariaDB: CONSTRAINT_N) dicari lewat isinya lalu dihapus.
                if ($target==='erapor_alur_penyetuju#erapor_alur_kode_v2') foreach (self::legacyCodeChecks($db) as $name) {
                    $db->exec('ALTER TABLE erapor_alur_penyetuju DROP CONSTRAINT `'.str_replace('`','',$name).'`');
                }
                $db->exec($statement);
            }
            $db->prepare("UPDATE erapor_migrations SET status='complete',completed_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$plan['id']]);
            return 'applied';
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lock]);
        }
    }

    /** Target "table", "table.column", atau "table#constraint". */
    public static function targetExists(PDO $db,string $target): bool
    {
        if (str_contains($target,'#')) {
            [$table,$name]=explode('#',$target,2);
            $q=$db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=? AND constraint_type='CHECK'");
            $q->execute([$table,$name]);
            return (int)$q->fetchColumn()>0;
        }
        if (str_contains($target,'.')) { [$table,$column]=explode('.',$target,2); return self::columnExists($db,$table,$column); }
        return self::tableExists($db,$target);
    }

    private static function legacyCodeChecks(PDO $db): array
    {
        $q=$db->query("SELECT t.constraint_name,c.check_clause FROM information_schema.table_constraints t
            JOIN information_schema.check_constraints c ON c.constraint_schema=t.constraint_schema AND c.constraint_name=t.constraint_name
            WHERE t.constraint_schema=DATABASE() AND t.table_name='erapor_alur_penyetuju' AND t.constraint_type='CHECK'");
        $names=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row=array_change_key_case($row,CASE_LOWER);
            if (str_contains($row['check_clause'],'KOORDINATOR_QURAN') && !str_contains($row['check_clause'],'WALI_KELAS')) $names[]=$row['constraint_name'];
        }
        return $names;
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $db,string $table,string $column): bool
    {
        $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    }
}
