<?php

/** Reviewed additive CREATE TABLE migrations only; never used by an HTTP route.
 * MySQL DDL auto-commits. Partial applications fail closed for manual inspection.
 */
final class EraporMigrationRunner
{
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
            if (!preg_match('/^CREATE TABLE (erapor_[a-z_]+)\s*\(/', $statement, $match)) {
                throw new RuntimeException('Only additive erapor CREATE TABLE statements are allowed.');
            }
            if (isset($steps[$match[1]])) throw new RuntimeException('Duplicate migration table.');
            $steps[$match[1]] = $statement;
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
                foreach ($plan['steps'] as $table => $_) {
                    if (!self::tableExists($db, $table)) throw new RuntimeException('Completed migration table is missing.');
                }
                return 'already_applied';
            }
            foreach ($plan['steps'] as $table => $_) {
                if (self::tableExists($db, $table)) throw new RuntimeException('Untracked table collision: ' . $table);
            }
            $db->prepare("INSERT INTO erapor_migrations(id,sha256,status) VALUES(?,?,'applying')")
                ->execute([$plan['id'], $plan['sha256']]);
            foreach ($plan['steps'] as $statement) $db->exec($statement);
            $db->prepare("UPDATE erapor_migrations SET status='complete',completed_at=CURRENT_TIMESTAMP WHERE id=?")
                ->execute([$plan['id']]);
            return 'applied';
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lock]);
        }
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
