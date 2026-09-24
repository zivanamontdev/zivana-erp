<?php

/** Pure rules for the new workflow. Not connected to legacy routes yet.
 * Persistence services must load trusted context, lock rows, apply permissions,
 * and write the result + audit log in one transaction; never trust request flags.
 */
final class EraporSessionPolicy
{
    public const STATES = ['BELUM_DIISI', 'TELAH_DIISI', 'MENUNGGU_TTD', 'SELESAI'];

    public static function isBlank(?string $value): bool
    {
        return $value === null || preg_match('/^[\s\p{Z}]*$/u', $value) === 1;
    }

    /** Null means delete the stored row. Otherwise preserve the teacher's text. */
    public static function textForStorage(?string $value): ?string
    {
        if ($value !== null && preg_match('//u', $value) !== 1) {
            throw new DomainException('Teks harus berupa UTF-8 yang valid.');
        }
        return self::isBlank($value) ? null : $value;
    }

    public static function periodOrder(string $semester, string $type): int
    {
        return match ($semester . ':' . $type) {
            'GANJIL:TENGAH' => 1, 'GANJIL:AKHIR' => 2,
            'GENAP:TENGAH' => 3, 'GENAP:AKHIR' => 4,
            default => throw new DomainException('Identitas periode tidak valid.'),
        };
    }

    /** Calendar dates in the application's timezone, injected for deterministic tests. */
    public static function assertWritable(string $status, string $deadline, string $today): void
    {
        self::assertState($status);
        self::assertDate($deadline);
        self::assertDate($today);
        if (!in_array($status, ['BELUM_DIISI', 'TELAH_DIISI'], true)) {
            throw new DomainException('Isian rapor sudah terkunci dan tidak dapat dibuka kembali.');
        }
        if ($today > $deadline) throw new DomainException('Batas waktu pengisian telah berakhir.');
    }

    /** Deadline intentionally absent: complete sessions may advance after it. */
    public static function confirmFilled(string $status, bool $complete): string
    {
        self::assertTransition($status, 'BELUM_DIISI', $complete);
        return 'TELAH_DIISI';
    }

    public static function confirmReception(string $status, bool $complete): string
    {
        self::assertTransition($status, 'TELAH_DIISI', $complete);
        return 'MENUNGGU_TTD';
    }

    public static function assertExtension(string $oldDate, string $newDate, string $today, string $reason): void
    {
        foreach ([$oldDate, $newDate, $today] as $date) self::assertDate($date);
        if ($newDate <= max($oldDate, $today) || self::isBlank($reason)) {
            throw new DomainException('Perpanjangan memerlukan alasan dan tanggal setelah tenggat lama serta hari ini.');
        }
    }

    /** Rows are snapshots from the session, not the current rubric configuration.
     * Each row: id positive integer, urutan positive integer, status MENUNGGU/DISETUJUI.
     * Cakupan dokumen and actor permissions must additionally be checked by the service.
     */
    public static function assertApprovalOrder(string $status, array $rows, int $targetId): void
    {
        self::assertState($status);
        self::assertApprovals($rows);
        if ($status !== 'MENUNGGU_TTD') throw new DomainException('Sesi belum menunggu persetujuan.');
        $target = null;
        foreach ($rows as $row) if ($row['id'] === $targetId) $target = $row;
        if ($target === null || $target['status'] !== 'MENUNGGU') {
            throw new DomainException('Persetujuan tidak tersedia atau sudah diberikan.');
        }
        foreach ($rows as $row) {
            if ($row['urutan'] < $target['urutan'] && $row['status'] !== 'DISETUJUI') {
                throw new DomainException('Persetujuan tahap sebelumnya belum lengkap.');
            }
        }
    }

    /** Call only after all PDF artifacts are ready, inside publication transaction. */
    public static function finalize(string $status, array $rows, bool $allDocumentsReady): string
    {
        self::assertState($status);
        self::assertApprovals($rows);
        if ($status !== 'MENUNGGU_TTD' || !$allDocumentsReady) {
            throw new DomainException('Paket rapor belum siap diterbitkan.');
        }
        foreach ($rows as $row) {
            if ($row['status'] !== 'DISETUJUI') throw new DomainException('Persetujuan belum lengkap.');
        }
        return 'SELESAI';
    }

    private static function assertState(string $status): void
    {
        if (!in_array($status, self::STATES, true)) throw new DomainException('Status sesi tidak valid.');
    }

    private static function assertTransition(string $status, string $expected, bool $complete): void
    {
        self::assertState($status);
        if ($status !== $expected) throw new DomainException('Perpindahan status tidak diizinkan.');
        if (!$complete) throw new DomainException('Lengkapi seluruh isian wajib terlebih dahulu.');
    }

    private static function assertDate(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new DomainException('Tanggal tidak valid.');
    }

    private static function assertApprovals(array $rows): void
    {
        if ($rows === []) throw new DomainException('Alur persetujuan belum tersedia.');
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['id'] ?? null) || $row['id'] < 1
                || isset($ids[$row['id']]) || !is_int($row['urutan'] ?? null) || $row['urutan'] < 1
                || !in_array($row['status'] ?? null, ['MENUNGGU', 'DISETUJUI'], true)) {
                throw new DomainException('Data persetujuan tidak valid.');
            }
            $ids[$row['id']] = true;
        }
    }
}
