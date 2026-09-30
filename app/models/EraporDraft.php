<?php

/**
 * Simpan Draft: penanda bahwa guru sudah menyimpan isian sementara (cadangan manual di samping autosave).
 * Nilainya sendiri tersimpan lewat endpoint simpan per dokumen; di sini hanya dicatat waktunya di erapor_sesi_log.
 */
final class EraporDraft
{
    /** Klik beruntun dalam jeda ini tidak menambah baris log baru. */
    private const THROTTLE_SECONDS = 20;

    public static function save(PDO $db, int $sessionId, int $actorId): array
    {
        $form = EraporTeacherForm::read($db, $sessionId, $actorId);
        if (empty($form['capabilities']['can_edit'])) throw new DomainException('Sesi tidak dapat diubah.');
        // Bandingkan waktu di MySQL (zona waktu PHP dan MySQL bisa berbeda).
        $q = $db->prepare("SELECT 1 FROM erapor_sesi_log WHERE sesi_id=? AND aksi='DRAFT_DISIMPAN' AND created_at > NOW() - INTERVAL " . self::THROTTLE_SECONDS . " SECOND LIMIT 1");
        $q->execute([$sessionId]);
        if (!$q->fetchColumn()) {
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'DRAFT_DISIMPAN',?,?)")
                ->execute([$sessionId, $actorId, $form['session']['status'], $form['session']['status']]);
        }
        return ['draft_at' => self::last($db, $sessionId), 'completion' => $form['completion']];
    }

    /** Waktu draft terakhir (Y-m-d H:i:s) atau null. */
    public static function last(PDO $db, int $sessionId): ?string
    {
        $q = $db->prepare("SELECT MAX(created_at) FROM erapor_sesi_log WHERE sesi_id=? AND aksi='DRAFT_DISIMPAN'");
        $q->execute([$sessionId]);
        $value = $q->fetchColumn();
        return $value ? (string) $value : null;
    }
}
