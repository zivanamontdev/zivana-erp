<?php

/** Period identity and report creation are shared by admin and teacher workflows. */
class ReportWorkflow
{
    public const SEMESTERS = ['ganjil' => 'Ganjil', 'genap' => 'Genap'];
    public const TYPES = ['Tengah Semester', 'Akhir Semester'];

    public static function reviewer(): bool
    {
        $stmt = Database::getInstance()->prepare('SELECT j.nama AS jabatan, ro.nama AS role_name FROM users u LEFT JOIN karyawan k ON k.id=u.karyawan_id LEFT JOIN jabatan j ON j.id=k.jabatan_id JOIN roles ro ON ro.id=u.role_id WHERE u.id=? AND u.is_active=1');
        $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        $user = $stmt->fetch();
        return $user && (in_array($user['jabatan'], ['Kepala Sekolah', 'Admin'], true)
            || (!$user['jabatan'] && $user['role_name'] === 'Superadmin'));
    }

    public static function savePeriod(array $data, ?int $id = null): int
    {
        if (!isset(self::SEMESTERS[$data['semester'] ?? '']) || !in_array($data['tipe'] ?? '', self::TYPES, true)) {
            throw new DomainException('Pilih Semester dan Tipe Periode yang valid.');
        }
        foreach (['awal_periode', 'akhir_periode'] as $field) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$field]);
            if (!$date || $date->format('Y-m-d') !== $data[$field]) throw new DomainException('Tanggal periode tidak valid.');
        }
        if ($data['awal_periode'] > $data['akhir_periode']) throw new DomainException('Akhir periode tidak boleh sebelum awal periode.');
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $year = $db->prepare('SELECT id FROM tahun_ajaran WHERE id=?' . $lock);
            $year->execute([$data['tahun_ajaran_id']]);
            if (!$year->fetch()) throw new DomainException('Tahun ajaran tidak tersedia.');
            $duplicate = $db->prepare('SELECT id FROM periode_penilaian WHERE tahun_ajaran_id=? AND semester=? AND tipe=? AND id<>?');
            $duplicate->execute([$data['tahun_ajaran_id'], $data['semester'], $data['tipe'], $id ?? 0]);
            if ($duplicate->fetch()) throw new DomainException('Periode untuk semester dan tipe ini sudah ada pada tahun ajaran tersebut.');
            $model = new PeriodePenilaian();
            if ($id !== null) {
                $previous = $model->find($id);
                if (!$previous) throw new DomainException('Periode tidak ditemukan.');
                $hasReports = $db->prepare('SELECT COUNT(*) FROM rapor r JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id WHERE s.periode_id=?');
                $hasReports->execute([$id]);
                if ($hasReports->fetchColumn() && ($previous['tipe'] !== $data['tipe'] || (!empty($previous['semester']) && $previous['semester'] !== $data['semester']))) {
                    throw new DomainException('Semester/tipe periode yang sudah memiliki rapor tidak dapat diganti.');
                }
                $values = $db->prepare('SELECT COUNT(*) FROM rapor_nilai n JOIN rapor r ON r.id=n.rapor_id JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id WHERE s.periode_id=? AND n.semester<>?');
                $values->execute([$id, $data['semester']]);
                if ($values->fetchColumn()) throw new DomainException('Semester tidak sesuai nilai rapor lama. Periksa data terlebih dahulu.');
                $model->update($id, $data);
            } else {
                $id = (int) $model->create($data);
            }
            self::syncPeriod($id);
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Called only from explicit writes, never from page GET requests. */
    public static function syncPeriod(int $periodId): void
    {
        $db = Database::getInstance();
        $period = (new PeriodePenilaian())->find($periodId);
        if (!$period || !isset(self::SEMESTERS[$period['semester'] ?? ''])) return;
        $template = $db->prepare('SELECT id FROM template_rapor WHERE nama=?');
        $template->execute(['Rapor Montessori ' . $period['tipe']]);
        $templateId = $template->fetchColumn();
        if (!$templateId) throw new DomainException('Template rapor untuk tipe periode belum tersedia.');
        $sessions = $db->prepare('SELECT * FROM sesi_pembagian_rapor WHERE periode_id=? ORDER BY id');
        $sessions->execute([$periodId]);
        $rows = $sessions->fetchAll();
        if (!$rows) {
            $sessionId = (new SesiPembagianRapor())->create(['periode_id'=>$periodId,'template_id'=>$templateId,'nama'=>$period['nama'],'tanggal_mulai'=>$period['awal_periode'],'tanggal_selesai'=>$period['akhir_periode']]);
            $rows = [['id'=>$sessionId,'template_id'=>$templateId]];
        }
        // Preserve historical multi-session data instead of merging/deleting it.
        foreach ($rows as $session) {
            $students = $db->prepare("SELECT m.id, (SELECT kg.guru_id FROM kelas_guru_murid kg WHERE kg.murid_id=m.id LIMIT 1) AS guru_id FROM murid m JOIN kelas k ON k.id=m.kelas_id WHERE m.status='bersekolah' AND k.tahun_ajaran_id=?");
            $students->execute([$period['tahun_ajaran_id']]);
            foreach ($students->fetchAll() as $student) {
                $exists = $db->prepare('SELECT id FROM rapor WHERE murid_id=? AND sesi_pembagian_id=?');
                $exists->execute([$student['id'], $session['id']]);
                if (!$exists->fetch()) {
                    (new Rapor())->create(['murid_id'=>$student['id'],'sesi_pembagian_id'=>$session['id'],'template_id'=>$session['template_id'],'guru_id'=>$student['guru_id'],'status'=>'belum_diisi']);
                } else {
                    $update = $db->prepare("UPDATE rapor SET guru_id=? WHERE murid_id=? AND sesi_pembagian_id=? AND status='belum_diisi'");
                    $update->execute([$student['guru_id'], $student['id'], $session['id']]);
                }
            }
        }
        if (count($rows) === 1) {
            $update = $db->prepare('UPDATE sesi_pembagian_rapor SET nama=?,tanggal_mulai=?,tanggal_selesai=? WHERE id=?');
            $update->execute([$period['nama'],$period['awal_periode'],$period['akhir_periode'],$rows[0]['id']]);
        }
    }
}
