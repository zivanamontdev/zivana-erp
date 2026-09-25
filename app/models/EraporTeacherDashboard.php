<?php

/** Read-only new e-rapor dashboard. It never provisions sessions on GET. */
final class EraporTeacherDashboard
{
    public static function read(PDO $db, int $actorId, ?int $requestedPeriodId = null, ?DateTimeImmutable $clock = null): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
            throw new RuntimeException('Dedicated MySQL connection required.');
        }
        if ($actorId < 1) throw new DomainException('Identitas guru tidak valid.');

        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');
        $db->beginTransaction();
        try {
            $account = self::one($db, "SELECT u.id,k.id AS karyawan_id FROM users u
                JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow')", [$actorId]);
            if (!$account) throw new DomainException('Akun guru tidak aktif.');

            $periodRows = self::all($db, "SELECT p.id,p.tahun_ajaran_id,p.semester,p.nama,p.tipe,p.awal_periode,p.akhir_periode,
                    t.tahun_awal,t.tahun_akhir,t.is_active
                FROM periode_penilaian p JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id
                WHERE p.semester IN ('ganjil','genap') AND p.tipe IN ('Tengah Semester','Akhir Semester')
                ORDER BY t.tahun_awal DESC, CASE p.semester WHEN 'ganjil' THEN 1 ELSE 2 END,
                    CASE p.tipe WHEN 'Tengah Semester' THEN 1 ELSE 2 END,p.id DESC");
            $periods = [];
            $yearValidity = [];
            foreach ($periodRows as $row) {
                $yearId = (int)$row['tahun_ajaran_id'];
                if (!array_key_exists($yearId,$yearValidity)) {
                    try { EraporCalendar::year($db,$yearId); $yearValidity[$yearId]=true; }
                    catch (DomainException) { $yearValidity[$yearId]=false; }
                }
                if (!$yearValidity[$yearId]) continue;
                try {
                    $period = EraporCalendar::normalize($row);
                } catch (DomainException) {
                    // A malformed legacy calendar is not guessed into a new report period.
                    continue;
                }
                $period['nama'] = (string)$row['nama'];
                $period['tahun_label'] = (int)$row['tahun_awal'].'/'.(int)$row['tahun_akhir'];
                $period['label'] = $period['nama'].' ('.$period['tahun_label'].')';
                $period['tahun_aktif'] = (bool)$row['is_active'];
                $periods[] = $period;
            }

            $now = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar')))
                ->setTimezone(new DateTimeZone('Asia/Makassar'));
            $today = $now->format('Y-m-d');
            $selected = null;
            if ($requestedPeriodId !== null && $requestedPeriodId > 0) {
                foreach ($periods as $period) if ($period['id'] === $requestedPeriodId) { $selected = $period; break; }
            }
            if ($selected === null) {
                foreach ($periods as $period) {
                    if ($period['awal_periode'] <= $today && $period['akhir_periode'] >= $today) { $selected = $period; break; }
                }
            }
            if ($selected === null) {
                $upcoming = array_values(array_filter($periods, fn($period) => $period['awal_periode'] >= $today));
                usort($upcoming, fn($a,$b) => [$a['awal_periode'],$a['id']] <=> [$b['awal_periode'],$b['id']]);
                $selected = $upcoming[0] ?? null;
            }
            if ($selected === null) {
                foreach ($periods as $period) if ($period['tahun_aktif']) $selected = $period;
            }
            $selected ??= $periods[0] ?? null;

            $agendaCurrent = null; $agendaNext = null;
            foreach ($periods as $period) {
                if ($period['awal_periode'] <= $today && $period['akhir_periode'] >= $today && $agendaCurrent === null) {
                    $end = new DateTimeImmutable($period['akhir_periode'], new DateTimeZone('Asia/Makassar'));
                    $agendaCurrent = $period + ['sisa_hari'=>(int)$now->diff($end)->format('%r%a')];
                }
                if ($period['awal_periode'] > $today && ($agendaNext === null || $period['awal_periode'] < $agendaNext['awal_periode'])) $agendaNext = $period;
            }

            $students = [];
            if ($selected !== null) {
                $students = self::all($db, "SELECT m.id,m.nama_lengkap,m.status_kondisi,m.status AS status_murid,
                        k.level_kelas,k.nama_kelas,s.id AS sesi_id,s.guru_user_id AS sesi_guru_id,
                        s.tahun_ajaran_id AS sesi_tahun_ajaran_id,s.semester AS sesi_semester,s.jenis AS sesi_jenis,
                        s.status AS sesi_status,s.paket_sha256
                    FROM kelas_guru_murid a JOIN murid m ON m.id=a.murid_id AND m.kelas_id=a.kelas_id
                    JOIN kelas k ON k.id=a.kelas_id
                    LEFT JOIN erapor_sesi s ON s.murid_id=m.id AND s.periode_id=?
                    WHERE a.guru_id=? ORDER BY m.nama_lengkap,m.id", [$selected['id'],(int)$account['karyawan_id']]);

                $catalog = self::all($db, 'SELECT r.* FROM erapor_rubrik r JOIN erapor_seed_history h ON h.rubrik_id=r.id ORDER BY r.id');
                $composition = require ROOT_PATH.'/config/erapor-package.php';
                $packagePeriod = [
                    'id'=>$selected['id'], 'tahun_ajaran_id'=>$selected['tahun_ajaran_id'],
                    'semester'=>strtolower($selected['semester']),
                    'tipe'=>$selected['jenis']==='TENGAH'?'Tengah Semester':'Akhir Semester',
                    'awal_periode'=>$selected['awal_periode'], 'akhir_periode'=>$selected['akhir_periode'],
                ];
                foreach ($students as &$student) {
                    $condition = self::condition((string)$student['status_kondisi']);
                    $student['kondisi'] = $condition;
                    $student['action'] = 'blocked';
                    $student['action_label'] = 'Rapor belum tersedia';
                    $student['reason'] = 'KONDISI_TIDAK_VALID';

                    if ($student['sesi_id'] !== null) {
                        if ((int)$student['sesi_guru_id'] !== $actorId
                            || (int)$student['sesi_tahun_ajaran_id'] !== $selected['tahun_ajaran_id']
                            || $student['sesi_semester'] !== $selected['semester']
                            || $student['sesi_jenis'] !== $selected['jenis']) {
                            $student['reason'] = 'REKONSILIASI_DIBUTUHKAN';
                            $student['action_label'] = 'Perlu pemeriksaan';
                        } else {
                            try {
                                EraporSessionFactory::assertPackage($db, ['id'=>(int)$student['sesi_id'],'paket_sha256'=>$student['paket_sha256']]);
                                $student['action'] = 'open';
                                $student['reason'] = null;
                                $student['action_label'] = match ($student['sesi_status']) {
                                    'BELUM_DIISI' => 'Isi Rapor',
                                    'TELAH_DIISI','SELESAI' => 'Lihat Rapor',
                                    'MENUNGGU_TTD' => 'Menunggu Persetujuan',
                                    default => 'Perlu pemeriksaan',
                                };
                                if (!in_array($student['sesi_status'], EraporSessionPolicy::STATES, true)) {
                                    $student['action']='blocked'; $student['reason']='STATUS_TIDAK_VALID';
                                }
                            } catch (DomainException) {
                                $student['reason'] = 'PAKET_BERUBAH';
                                $student['action_label'] = 'Perlu pemeriksaan';
                            }
                        }
                    } elseif ($condition !== null) {
                        try {
                            $plan = EraporPackagePlan::build((int)$student['id'],$condition,$packagePeriod,$catalog,$composition);
                            if ($plan['dapat_dibentuk']) {
                                $student['action']='create'; $student['action_label']='Isi Rapor'; $student['reason']=null;
                            } else {
                                $student['reason']='RUBRIK_BELUM_TERSEDIA';
                                $student['rubrik_belum_tersedia']=$plan['rubrik_belum_tersedia'];
                                $student['action_label']='Paket rapor belum siap';
                            }
                        } catch (DomainException) {
                            $student['reason']='PAKET_TIDAK_VALID';
                            $student['action_label']='Perlu pemeriksaan';
                        }
                    }
                }
                unset($student);
            }

            $db->commit();
            return ['periodOptions'=>$periods,'selectedPeriod'=>$selected,'students'=>$students,
                'agendaCurrent'=>$agendaCurrent,'agendaNext'=>$agendaNext,'today'=>$today];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private static function condition(string $value): ?string
    {
        return EraporPackagePlan::normalizeCondition($value);
    }

    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
    private static function all(PDO $db,string $sql,array $args=[]): array
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
