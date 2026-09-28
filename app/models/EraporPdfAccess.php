<?php

/**
 * Siapa yang boleh membuka PDF paket eRapor satu sesi. PDF memuat seluruh dokumen, jadi hanya pihak yang
 * berwenang atas seluruh paket: guru pemilik sesi, penyetuju tahap cakupan SEMUA (Kepala Sekolah), dan Superadmin.
 * Koordinator bercakupan TERBATAS hanya melihat pratinjau dokumen dalam cakupannya (lihat scopedDocumentIds).
 */
final class EraporPdfAccess
{
    public static function canView(PDO $db, int $sessionId, int $userId): bool
    {
        if (min($sessionId, $userId) < 1) return false;
        $q = $db->prepare("SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1");
        $q->execute([$userId]);
        $role = $q->fetchColumn();
        if ($role === false) return false;
        if ($role === 'Superadmin') return true;
        $q = $db->prepare('SELECT 1 FROM erapor_sesi WHERE id=? AND guru_user_id=?');
        $q->execute([$sessionId, $userId]);
        if ($q->fetchColumn()) return true;
        $q = $db->prepare("SELECT 1 FROM erapor_sesi_penyetuju sp
            JOIN erapor_penyetuju_user a ON a.penyetuju_id=sp.penyetuju_id AND a.user_id=? AND a.aktif=1
            JOIN users u ON u.id=a.user_id AND u.is_active=1
            JOIN karyawan k ON k.id=u.karyawan_id AND k.is_active=1 JOIN jabatan j ON j.id=k.jabatan_id AND j.is_active=1
            WHERE sp.sesi_id=? AND sp.cakupan='SEMUA' AND (sp.kode<>'KEPALA_SEKOLAH' OR j.nama='Kepala Sekolah') LIMIT 1");
        $q->execute([$userId, $sessionId]);
        if ($q->fetchColumn()) return true;
        // Wali kelas memegang tahap WALI_KELAS (cakupan SEMUA) untuk sesi di kelasnya.
        $q = $db->prepare("SELECT 1 FROM erapor_sesi s JOIN erapor_sesi_penyetuju sp ON sp.sesi_id=s.id AND sp.kode='WALI_KELAS'
            JOIN erapor_wali_kelas w ON w.kelas_id=s.kelas_id AND w.user_id=? JOIN users u ON u.id=w.user_id AND u.is_active=1
            WHERE s.id=? LIMIT 1");
        $q->execute([$userId, $sessionId]);
        return (bool) $q->fetchColumn();
    }

    /**
     * Dokumen yang boleh dipratinjau koordinator bercakupan TERBATAS (mis. Koordinator Agama: Agama + Ummi).
     * Array kosong bila pengguna bukan penyetuju terbatas aktif pada sesi ini.
     * @return array<int,int>
     */
    public static function scopedDocumentIds(PDO $db, int $sessionId, int $userId): array
    {
        if (min($sessionId, $userId) < 1) return [];
        $q = $db->prepare("SELECT DISTINCT d.dokumen_id FROM erapor_sesi_penyetuju sp
            JOIN erapor_sesi_penyetuju_dokumen d ON d.sesi_penyetuju_id=sp.id AND d.sesi_id=sp.sesi_id
            JOIN erapor_penyetuju_user a ON a.penyetuju_id=sp.penyetuju_id AND a.user_id=? AND a.aktif=1
            JOIN users u ON u.id=a.user_id AND u.is_active=1
            WHERE sp.sesi_id=? AND sp.cakupan='TERBATAS'");
        $q->execute([$userId, $sessionId]);
        return array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Artefak resmi (setelah seluruh persetujuan) bila ada, sudah diverifikasi hash-nya. */
    public static function officialArtifact(PDO $db, int $sessionId): ?array
    {
        $q = $db->prepare('SELECT filename,ukuran_byte,pdf_sha256,pdf_bytes FROM erapor_publikasi_pdf WHERE sesi_id=?');
        $q->execute([$sessionId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $bytes = is_resource($row['pdf_bytes']) ? stream_get_contents($row['pdf_bytes']) : $row['pdf_bytes'];
        if (!is_string($bytes) || strlen($bytes) !== (int) $row['ukuran_byte'] || !hash_equals($row['pdf_sha256'], hash('sha256', $bytes))) {
            throw new DomainException('Artefak PDF tersimpan tidak lolos pemeriksaan integritas.');
        }
        return ['filename' => $row['filename'], 'bytes' => $bytes];
    }

    public static function hasOfficial(PDO $db, int $sessionId): bool
    {
        $q = $db->prepare('SELECT 1 FROM erapor_publikasi_pdf WHERE sesi_id=?');
        $q->execute([$sessionId]);
        return (bool) $q->fetchColumn();
    }
}
