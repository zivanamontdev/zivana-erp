<?php

/**
 * Tahap akhir rapor: Kepala Sekolah menerbitkan (PDF resmi diunggah ke R2 private, link unduh dibuat, sesi SELESAI),
 * guru PIC membagikan link ke orang tua lewat WhatsApp, dan orang tua mengunduh lewat link aplikasi.
 */
final class EraporDistribution
{
    /** Masa berlaku link unduh orang tua, dihitung dari saat terbit. */
    public const LINK_DAYS = 60;

    /** Terbitkan rapor: hanya Kepala Sekolah yang ditugaskan, setelah seluruh persetujuan dan PDF resmi siap. */
    public static function publish(PDO $db, int $sessionId, int $approvalId, int $actorId, ?R2Storage $r2 = null): array
    {
        $session = self::one($db, 'SELECT * FROM erapor_sesi WHERE id=?', [$sessionId]);
        $target = self::one($db, 'SELECT * FROM erapor_sesi_penyetuju WHERE id=? AND sesi_id=?', [$approvalId, $sessionId]);
        if (!$session || !$target || $target['kode'] !== 'KEPALA_SEKOLAH') throw new DomainException('Rapor tidak dapat diterbitkan dari tahap ini.');
        if (!EraporApprovalAccess::actor($db, $target, $session, $actorId)) throw new DomainException('Hanya Kepala Sekolah yang ditugaskan dapat menerbitkan rapor.');
        if ($session['status'] === 'SELESAI') throw new DomainException('Rapor sudah diterbitkan.');
        $approvals = array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'urutan' => (int) $r['urutan'], 'status' => $r['status']] + $r,
            self::all($db, 'SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id=? ORDER BY urutan,id', [$sessionId]));
        $artifact = EraporPdfAccess::officialArtifact($db, $sessionId);
        EraporSessionPolicy::finalize((string) $session['status'], $approvals, $artifact !== null);

        // Unggah ke R2 lebih dulu (di luar transaksi); bila pencatatan gagal, objek dihapus lagi.
        $r2 ??= new R2Storage();
        $year = self::one($db, 'SELECT t.tahun_awal FROM tahun_ajaran t WHERE t.id=?', [$session['tahun_ajaran_id']]);
        $key = 'erapor/rapor/' . (int) ($year['tahun_awal'] ?? 0) . '-' . strtolower((string) $session['semester']) . '/'
            . $sessionId . '-' . bin2hex(random_bytes(16)) . '.pdf';
        $r2->put($key, $artifact['bytes'], 'application/pdf', 'attachment; filename="' . self::downloadName($db, $session) . '"');
        $head = $r2->head($key);
        if (!$head || $head['size'] !== strlen($artifact['bytes'])) {
            $r2->delete($key);
            throw new RuntimeException('Verifikasi unggahan R2 gagal.');
        }

        $token = bin2hex(random_bytes(32));
        $url = rtrim(APP_URL, '/') . BASE_PATH . '/rapor/unduh/' . $token;
        $db->beginTransaction();
        try {
            $locked = self::one($db, 'SELECT status FROM erapor_sesi WHERE id=? FOR UPDATE', [$sessionId]);
            if (!$locked || $locked['status'] !== 'MENUNGGU_TTD') throw new DomainException('Status rapor berubah; muat ulang halaman.');
            $db->prepare("UPDATE erapor_sesi SET status='SELESAI' WHERE id=? AND status='MENUNGGU_TTD'")->execute([$sessionId]);
            $db->prepare('INSERT INTO erapor_publikasi_distribusi(sesi_id,r2_bucket,r2_key,unduh_token,unduh_url,unduh_kedaluwarsa,diterbitkan_oleh)
                VALUES(?,?,?,?,?,DATE_ADD(NOW(), INTERVAL ' . self::LINK_DAYS . ' DAY),?)')
                ->execute([$sessionId, R2_PRIVATE_BUCKET, $key, $token, $url, $actorId]);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'DITERBITKAN','MENUNGGU_TTD','SELESAI')")
                ->execute([$sessionId, $actorId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            try { $r2->delete($key); } catch (Throwable) {}
            throw $e;
        }
        return ['url' => $url];
    }

    /** Data tombol "Bagikan ke Orang Tua" untuk guru pemilik sesi; null bila belum terbit. */
    public static function shareState(PDO $db, int $sessionId): ?array
    {
        $row = self::one($db, "SELECT d.*,m.nama_lengkap,m.nama_ayah,m.telp_ayah,m.nama_ibu,m.telp_ibu,p.nama AS periode
            FROM erapor_publikasi_distribusi d JOIN erapor_sesi s ON s.id=d.sesi_id JOIN murid m ON m.id=s.murid_id
            JOIN periode_penilaian p ON p.id=s.periode_id WHERE d.sesi_id=? AND s.status='SELESAI'", [$sessionId]);
        if (!$row) return null;
        $contacts = [];
        foreach (['AYAH' => ['nama_ayah', 'telp_ayah', 'Ayah'], 'IBU' => ['nama_ibu', 'telp_ibu', 'Ibu']] as $code => [$nameKey, $phoneKey, $role]) {
            $phone = self::phone((string) ($row[$phoneKey] ?? ''));
            if ($phone === null) continue;
            $name = trim((string) ($row[$nameKey] ?? ''));
            $contacts[$code] = ['label' => $role . ($name !== '' && $name !== '-' ? ' · ' . $name : ''), 'phone' => $phone,
                'wa_url' => 'https://wa.me/' . $phone . '?text=' . rawurlencode(self::message($row, $name !== '' && $name !== '-' ? $name : $role))];
        }
        return ['contacts' => $contacts, 'shared_at' => $row['dibagikan_pada'], 'shared_to' => $row['dibagikan_ke'],
            'expires_at' => $row['unduh_kedaluwarsa'], 'downloads' => (int) $row['jumlah_unduh']];
    }

    /** Catat pembagian oleh guru pemilik sesi; status kirim menjadi TERKIRIM (Dibagikan ke Orang Tua). */
    public static function markShared(PDO $db, int $sessionId, int $actorId, string $target): void
    {
        if (!in_array($target, ['AYAH', 'IBU'], true)) throw new DomainException('Penerima tidak valid.');
        $owner = self::one($db, "SELECT 1 FROM erapor_sesi WHERE id=? AND guru_user_id=? AND status='SELESAI'", [$sessionId, $actorId]);
        if (!$owner) throw new DomainException('Rapor tidak dapat dibagikan dari akun ini.');
        $state = self::shareState($db, $sessionId);
        if (!$state || !isset($state['contacts'][$target])) throw new DomainException('Kontak orang tua belum tersedia.');
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE erapor_publikasi_distribusi SET dibagikan_oleh=?,dibagikan_pada=NOW(),dibagikan_ke=? WHERE sesi_id=?')->execute([$actorId, $target, $sessionId]);
            $db->prepare("UPDATE erapor_publikasi_pdf SET status_kirim='TERKIRIM' WHERE sesi_id=?")->execute([$sessionId]);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,?,'SELESAI','SELESAI')")
                ->execute([$sessionId, $actorId, 'DIBAGIKAN_' . $target]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** URL R2 bertanda tangan (5 menit) untuk token link orang tua; null bila token tidak dikenal atau kedaluwarsa. */
    public static function downloadUrl(PDO $db, string $token, bool $count, ?R2Storage $r2 = null): ?string
    {
        if (!preg_match('/^[0-9a-f]{64}$/D', $token)) return null;
        $row = self::one($db, "SELECT d.sesi_id,d.r2_key,s.murid_id,s.periode_id,s.semester,s.tahun_ajaran_id FROM erapor_publikasi_distribusi d
            JOIN erapor_sesi s ON s.id=d.sesi_id WHERE d.unduh_token=? AND d.unduh_kedaluwarsa>NOW() AND s.status='SELESAI'", [$token]);
        if (!$row) return null;
        if ($count) $db->prepare('UPDATE erapor_publikasi_distribusi SET jumlah_unduh=jumlah_unduh+1,terakhir_unduh=NOW() WHERE sesi_id=?')->execute([(int) $row['sesi_id']]);
        $session = self::one($db, 'SELECT * FROM erapor_sesi WHERE id=?', [(int) $row['sesi_id']]);
        return ($r2 ?? new R2Storage())->presignedGetUrl($row['r2_key'], 300, self::downloadName($db, $session));
    }

    /** Nomor WhatsApp format 62xxxxxxxxxx; null bila kosong/tidak valid (mis. "-"). */
    public static function phone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '0')) $digits = '62' . substr($digits, 1);
        elseif (str_starts_with($digits, '8')) $digits = '62' . $digits;
        return preg_match('/^62[0-9]{8,13}$/D', $digits) ? $digits : null;
    }

    private static function message(array $row, string $recipient): string
    {
        return "Assalamu'alaikum " . $recipient . ",\n\n"
            . 'Berikut ' . $row['periode'] . ' ananda ' . $row['nama_lengkap'] . ".\n"
            . 'Silakan unduh rapor melalui tautan berikut (berlaku sampai ' . date('d/m/Y', strtotime((string) $row['unduh_kedaluwarsa'])) . "):\n"
            . $row['unduh_url'] . "\n\nTerima kasih.";
    }

    private static function downloadName(PDO $db, array $session): string
    {
        $student = self::one($db, 'SELECT nama_lengkap FROM murid WHERE id=?', [(int) $session['murid_id']]);
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($student['nama_lengkap'] ?? 'Murid'));
        return 'Rapor-' . trim((string) $name, '-') . '-' . ($session['jenis'] === 'AKHIR' ? 'AS' : 'TS') . '-' . ucfirst(strtolower((string) $session['semester'])) . '.pdf';
    }

    private static function one(PDO $db, string $sql, array $args): array|false { $q = $db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC); }
    private static function all(PDO $db, string $sql, array $args): array { $q = $db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC); }
}
