<?php

/**
 * Kosongkan data operasional lalu isi master data realistis (tanpa tag [DEMO]) untuk uji coba eRapor.
 * Dipertahankan: akun Superadmin non-demo, RBAC, jabatan, template rapor lama, dan seluruh katalog/rubrik eRapor.
 * Setiap tabel wajib terklasifikasi; tabel yang tidak dikenal memblokir proses sebelum ada penghapusan.
 * Lihat cookbook/data-reset.md.
 */
final class DataResetSeeder
{
    /** Definisi/konfigurasi yang tidak disentuh. */
    public const KEEP_TABLES = [
        'roles', 'permissions', 'role_permissions', 'jabatan',
        'template_rapor', 'template_rapor_area', 'template_rapor_item', 'template_rapor_subkategori', 'skala_nilai', 'skala_nilai_opsi',
        'erapor_migrations', 'erapor_seed_history', 'erapor_rubrik', 'erapor_rubrik_area', 'erapor_rubrik_bagian', 'erapor_rubrik_grup',
        'erapor_rubrik_indikator', 'erapor_rubrik_penandatangan', 'erapor_rubrik_periode', 'erapor_rubrik_sub_area', 'erapor_rubrik_sumber',
        'erapor_skala_nilai', 'erapor_skala_huruf', 'erapor_agama_item', 'erapor_agama_item_nama', 'erapor_agama_lingkup', 'erapor_agama_pilihan',
        'erapor_agama_sub', 'erapor_agama_subtingkat', 'erapor_agama_tahapan', 'erapor_bing_indikator', 'erapor_bing_komentar', 'erapor_bing_skala',
        'erapor_ppi_aspek', 'erapor_ppi_kolom', 'erapor_ummi_jilid', 'erapor_ummi_materi', 'erapor_alur_penyetuju', 'erapor_alur_dokumen',
    ];

    /** Data operasional yang dikosongkan seluruhnya. `users` dikosongkan kecuali Superadmin yang dipertahankan. */
    public const CLEAR_TABLES = [
        'users', 'password_resets', 'karyawan', 'sekolah', 'sekolah_media', 'tahun_ajaran', 'periode_penilaian', 'kelas', 'murid', 'kelas_guru_murid',
        'sesi_pembagian_rapor', 'rapor', 'rapor_nilai', 'rapor_catatan_guru',
        'erapor_dokumen', 'erapor_sesi', 'erapor_sesi_dokumen', 'erapor_sesi_log', 'erapor_sesi_penerimaan', 'erapor_sesi_penyetuju',
        'erapor_sesi_penyetuju_dokumen', 'erapor_persetujuan_snapshot', 'erapor_publikasi_pdf', 'erapor_isian_log', 'erapor_periode_perpanjangan',
        'erapor_rts_nilai', 'erapor_rts_belum_dikenalkan', 'erapor_agama_nilai', 'erapor_agama_catatan', 'erapor_bing_nilai', 'erapor_bing_isian', 'erapor_ppi_isian',
        'erapor_ummi_bacaan', 'erapor_ummi_catatan', 'erapor_ummi_periode', 'erapor_ummi_tes',
        'erapor_penyetuju_user', 'erapor_penugasan_penyetuju_audit', 'erapor_profil_penandatangan', 'erapor_profil_penandatangan_audit',
    ];

    public const EMAIL_DOMAIN = 'sekolahzivanamontessori.sch.id';
    /** Awal tahun ajaran aktif seed; dipakai sebagai tanggal masuk murid dari Excel yang tidak mencantumkannya. */
    public const SCHOOL_YEAR_START = '2026-07-01';

    /** Read-only: apa yang akan dihapus, dipertahankan, dan dibuat. */
    public static function plan(PDO $db, int $actorId, ?array $pilot = null): array
    {
        $tables = self::tables($db);
        $unknown = array_values(array_diff($tables, self::KEEP_TABLES, self::CLEAR_TABLES));
        $missing = array_values(array_diff(self::CLEAR_TABLES, $tables));
        $kept = self::keptUsers($db);
        $blockers = [];
        if ($unknown) $blockers[] = 'Tabel belum terklasifikasi (tidak akan dihapus/dipertahankan tanpa ditinjau): ' . implode(', ', $unknown);
        if ($missing) $blockers[] = 'Tabel yang diharapkan tidak ada: ' . implode(', ', $missing);
        // Driver MariaDB/PDO di hosting mengembalikan ID sebagai string; bandingkan sebagai angka.
        if (!in_array($actorId, array_map('intval', array_column($kept, 'id')), true)) $blockers[] = 'Akun yang menjalankan reset bukan Superadmin non-demo tanpa data pegawai, sehingga ikut terhapus. Login dengan akun Superadmin utama.';
        $order = $blockers ? [] : self::deleteOrder($db, $tables);
        $counts = [];
        foreach (array_intersect(self::CLEAR_TABLES, $tables) as $table) {
            $counts[$table] = $table === 'users'
                ? (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() - count($kept)
                : (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        }
        arsort($counts);
        $seed = self::seedData($pilot);
        return [
            'blockers' => $blockers,
            'delete_counts' => $counts,
            'delete_order' => $order,
            'kept_users' => array_column($kept, 'email'),
            'seed' => [
                'tahun_ajaran' => count($seed['years']), 'periode' => count($seed['periods']), 'kelas' => count($seed['classes']),
                'karyawan' => count($seed['staff']), 'murid' => count($seed['students']),
                'murid_regular' => count(array_filter($seed['students'], fn($s) => $s['kondisi'] === 'Reguler')),
                'murid_abk' => count(array_filter($seed['students'], fn($s) => $s['kondisi'] === 'Berkebutuhan Khusus')),
            ],
            'staff' => array_map(fn($s) => $s['nama'] . ' — ' . $s['jabatan'] . ' — ' . $s['email'], $seed['staff']),
            'pilot' => $pilot !== null,
            'classes' => array_map(fn($c) => $c['level'] . ' ' . $c['nama'], array_values($seed['classes'])),
        ];
    }

    /** Jalankan dalam satu transaksi; penugasan penyetuju dibuat sesudahnya lewat EraporApprovalAssignment (beraudit). */
    public static function run(PDO $db, int $actorId, string $password, callable $log, ?array $pilot = null): array
    {
        if (!employeePasswordIsValid($password)) throw new DomainException('Password awal minimal 8 karakter dengan huruf kapital, angka, dan simbol.');
        $plan = self::plan($db, $actorId, $pilot);
        if ($plan['blockers']) throw new DomainException(implode(' ', $plan['blockers']));
        $keptIds = array_map('intval', array_column(self::keptUsers($db), 'id'));
        $seed = self::seedData($pilot);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $lock = substr('erapor_migration_' . hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn()), 0, 64);
        $q = $db->prepare('SELECT GET_LOCK(?,10)'); $q->execute([$lock]);
        if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Proses eRapor lain sedang berjalan. Coba lagi beberapa saat.');
        $ids = [];
        try {
            $db->beginTransaction();
            foreach ($plan['delete_order'] as $table) {
                if ($table === 'users') {
                    $in = implode(',', array_fill(0, count($keptIds), '?'));
                    $del = $db->prepare("DELETE FROM users WHERE id NOT IN ($in)"); $del->execute($keptIds);
                } else {
                    $del = $db->prepare('DELETE FROM `' . $table . '`'); $del->execute();
                }
                $log('Dikosongkan ' . $table . ': ' . $del->rowCount() . ' baris');
            }
            $ids = self::insertSeed($db, $seed, $hash, $log);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        } finally {
            $r = $db->prepare('SELECT RELEASE_LOCK(?)'); $r->execute([$lock]);
        }
        $log('Transaksi reset + seed berhasil di-commit.');

        // Penugasan: kepala sekolah untuk ketiga tahap; koordinator dapat diganti lewat menu Penugasan Penyetuju.
        $warnings = [];
        try {
            $kepala = $ids['users']['kepala'];
            EraporApprovalAssignment::save($db, $actorId, ['KEPALA_SEKOLAH' => [$kepala], 'KOORDINATOR_BING' => [$kepala], 'KOORDINATOR_QURAN' => [$kepala]],
                'Seed awal uji coba: kepala sekolah pada semua tahap');
            $log('Penugasan penyetuju: Kepala Sekolah pada tahap Koordinator Bahasa Inggris, Koordinator Al-Qur\'an, dan Kepala Sekolah.');
        } catch (Throwable $e) {
            $warnings[] = 'Penugasan penyetuju belum dibuat (' . $e->getMessage() . '). Atur manual di menu Penugasan Penyetuju.';
        }
        // Dua rapor contoh terisi penuh (Regular + ABK) lewat layanan simpan yang sama dengan editor guru.
        // Tidak untuk data murid asli dari Excel: nilai rekaan tidak boleh melekat pada anak sungguhan.
        foreach ($pilot === null ? self::EXAMPLES : [] as $example) {
            try {
                $filled = self::fillExample($db, $ids['students'][$example['murid']], $ids['periods'][$example['periode']], $ids['users'][$example['guru']]);
                $log('Rapor contoh ' . $example['murid'] . ': ' . $filled['filled'] . '/' . $filled['required'] . ' isian wajib terisi (status BELUM DIISI, siap diselesaikan guru).');
            } catch (Throwable $e) {
                $warnings[] = 'Rapor contoh ' . $example['murid'] . ' belum terisi (' . $e->getMessage() . ').';
            }
        }
        foreach ($warnings as $w) $log('PERINGATAN: ' . $w);
        return ['warnings' => $warnings];
    }

    /** Rapor contoh: satu Regular, satu ABK, di periode Tengah Ganjil 2026/2027. */
    public const EXAMPLES = [
        ['murid' => 'Fathan Al Ghifari', 'guru' => 'guru_ranting2', 'periode' => 'Rapor Tengah Semester Ganjil 2026/2027'],
        ['murid' => 'Zahra Aulia Kirana', 'guru' => 'shadow_2', 'periode' => 'Rapor Tengah Semester Ganjil 2026/2027'],
    ];

    private static function fillExample(PDO $db, int $studentId, int $periodId, int $teacherId): array
    {
        $session = EraporSessionFactory::create($db, $studentId, $periodId, $teacherId);
        $sid = (int) $session['id'];
        foreach (EraporTeacherForm::read($db, $sid, $teacherId)['documents'] as $doc) {
            if ($doc['jenis_dokumen'] === 'UMMI' && !empty($doc['form']['definitions']['initialization_required'])) {
                EraporUmmiEntry::save($db, $sid, (int) $doc['id'], $teacherId, []); // sama dengan tombol "Mulai pengisian Ummi"
            }
        }
        foreach (EraporTeacherForm::read($db, $sid, $teacherId)['documents'] as $doc) {
            $changes = self::exampleChanges($doc['jenis_dokumen'], $doc['form']['definitions'], $doc['form']['values']);
            foreach (array_chunk($changes, 200) as $chunk) {
                match ($doc['jenis_dokumen']) {
                    'RTS' => EraporRtsEntry::save($db, $sid, (int) $doc['id'], $teacherId, $chunk),
                    'BING', 'PPI' => EraporStructuredEntry::save($db, $doc['jenis_dokumen'], $sid, (int) $doc['id'], $teacherId, $chunk),
                    'AGAMA' => EraporAgamaEntry::save($db, $sid, (int) $doc['id'], $teacherId, $chunk),
                    'UMMI' => EraporUmmiEntry::save($db, $sid, (int) $doc['id'], $teacherId, $chunk),
                };
            }
        }
        $completion = EraporTeacherForm::read($db, $sid, $teacherId)['completion'];
        return ['filled' => array_sum(array_column($completion['documents'], 'filled')), 'required' => array_sum(array_column($completion['documents'], 'required'))];
    }

    /** Isian realistis yang bervariasi; kunci/nilai persis seperti yang dikirim editor guru. */
    private static function exampleChanges(string $type, array $defs, array $values): array
    {
        $changes = [];
        $n = 0;
        $pick = static function (array $options) use (&$n) { $options = array_values($options); return $options[($n++ * 7 + 3) % count($options)]; };
        switch ($type) {
            case 'RTS':
                $grades = array_map(fn($s) => (int) $s['nilai'], $defs['scale']);
                sort($grades);
                $weighted = array_merge($grades, array_slice($grades, 1), array_slice($grades, 2)); // lebih banyak "berkembang"
                foreach ($defs['items'] as $item) $changes[] = ['indikator_id' => (int) $item['id'], 'nilai' => $pick($weighted), 'expected' => null];
                break;
            case 'AGAMA':
                $choices = array_column($defs['scale'], 'kolom_cetak');
                foreach ($defs['items'] as $item) $changes[] = ['key' => 'nilai:' . $item['id'], 'value' => $pick($choices), 'expected' => null];
                foreach ($defs['scopes'] as $scope) {
                    $changes[] = ['key' => 'catatan:' . $scope['id'], 'value' => 'Ananda menunjukkan perkembangan yang baik pada ' . mb_strtolower($scope['nama']) . ', perlu terus dibiasakan di rumah dan di sekolah.', 'expected' => $values['catatan:' . $scope['id']] ?? null];
                }
                break;
            case 'UMMI':
                $letters = array_column($defs['scale'], 'kode');
                foreach ($defs['items'] as $item) {
                    if (!empty($item['hanya_pra_tk']) && empty($values['mulai_pra_tk'])) continue;
                    $changes[] = ['key' => 'bacaan:' . $item['id'], 'value' => $pick(array_slice($letters, 0, 6)), 'expected' => $values['bacaan:' . $item['id']] ?? null];
                }
                $changes[] = ['key' => 'catatan', 'value' => 'Ananda sudah lancar mengenal huruf hijaiyah dan mulai tartil pada bacaan bersambung. Perlu murojaah rutin di rumah.', 'expected' => $values['catatan'] ?? null];
                break;
            case 'BING':
                $codes = array_column($defs['scale'], 'kode');
                foreach ($defs['items'] as $item) $changes[] = ['key' => 'nilai:' . $item['id'], 'value' => $pick($codes), 'expected' => null];
                $comments = ['Speaks in short sentences and answers simple questions confidently.', 'Recognises familiar words and enjoys picture books.',
                    'Follows two-step instructions and responds well to songs.', 'Traces letters neatly and is starting to write simple words.'];
                foreach ($defs['comments'] as $i => $comment) $changes[] = ['key' => 'komentar:' . $comment['id'], 'value' => $comments[$i % count($comments)], 'expected' => $values['komentar:' . $comment['id']] ?? null];
                break;
            case 'PPI':
                $byColumn = [
                    'kekuatan' => 'Mampu mengikuti rutinitas kelas dengan jadwal visual; senang kegiatan sensorial.',
                    'tantangan' => 'Masih kesulitan menunggu giliran dan beralih kegiatan tanpa pendampingan.',
                    'tujuan_jangka_panjang' => 'Mandiri mengikuti kegiatan kelompok kecil selama 15 menit.',
                    'tujuan_jangka_pendek' => 'Menunggu giliran dengan bantuan isyarat visual pada 3 dari 5 kesempatan.',
                    'strategi' => 'Jadwal visual, penguatan positif, pemodelan oleh guru pendamping.',
                    'media' => 'Kartu giliran, timer visual, papan token.',
                ];
                foreach ($defs['aspects'] as $aspect) foreach ($defs['columns'] as $column) {
                    $key = $aspect['id'] . ':' . $column['id'];
                    $changes[] = ['key' => $key, 'value' => ($byColumn[$column['kode']] ?? 'Catatan guru pendamping.') . ' (' . $aspect['nama'] . ')', 'expected' => $values[$key] ?? null];
                }
                break;
        }
        return $changes;
    }

    private static function insertSeed(PDO $db, array $seed, string $hash, callable $log): array
    {
        $ids = [];
        $ins = static function (string $table, array $row) use ($db): int {
            $cols = array_keys($row);
            $q = $db->prepare('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
            $q->execute(array_values($row));
            return (int) $db->lastInsertId();
        };
        // SekolahController membaca baris tunggal ber-id 1 (SEKOLAH_ID); setelah DELETE auto-increment tidak kembali ke 1.
        $ins('sekolah', ['id' => 1] + $seed['school']);
        $log('Data sekolah dibuat.');

        foreach ($seed['years'] as $key => $year) $ids['years'][$key] = $ins('tahun_ajaran', $year);
        $log('Tahun ajaran: 2025/2026 (tidak aktif), 2026/2027 (aktif).');

        DataBaseline::ensureJabatan($db);
        $positions = [];
        foreach ($db->query('SELECT id,nama,role_id,is_active FROM jabatan')->fetchAll(PDO::FETCH_ASSOC) as $p) $positions[$p['nama']] = $p;
        foreach ($seed['staff'] as $key => $staff) {
            $position = $positions[$staff['jabatan']] ?? null;
            if (!$position || !(int) $position['is_active']) throw new DomainException('Jabatan "' . $staff['jabatan'] . '" tidak tersedia atau nonaktif.');
            $employeeId = $ins('karyawan', ['jabatan_id' => $position['id'], 'nama' => $staff['nama'], 'is_active' => 1]);
            $ids['employees'][$key] = $employeeId;
            $ids['users'][$key] = $ins('users', ['karyawan_id' => $employeeId, 'role_id' => $position['role_id'], 'email' => $staff['email'], 'password_hash' => $hash, 'is_active' => 1]);
        }
        $log('Karyawan dan akun: ' . count($seed['staff']) . ' (password awal sesuai isian form).');

        foreach ($seed['classes'] as $key => $class) $ids['classes'][$key] = $ins('kelas', ['tahun_ajaran_id' => $ids['years']['2026'], 'level_kelas' => $class['level'], 'nama_kelas' => $class['nama']]);
        $log('Kelas 2026/2027: ' . count($seed['classes']) . '.');

        $abk = 0;
        foreach ($seed['students'] as $student) {
            $row = $student['row'];
            $row['kelas_id'] = $student['kelas'] === null ? null : $ids['classes'][$student['kelas']];
            $row['status_kondisi'] = $student['kondisi'];
            $studentId = $ins('murid', $row);
            $ids['students'][$row['nama_lengkap']] = $studentId;
            if ($student['kondisi'] === 'Berkebutuhan Khusus') $abk++;
            if ($student['guru'] !== null) {
                $ins('kelas_guru_murid', ['kelas_id' => $row['kelas_id'], 'guru_id' => $ids['employees'][$student['guru']], 'murid_id' => $studentId]);
            }
        }
        $log('Murid: ' . count($seed['students']) . ' (' . (count($seed['students']) - $abk) . ' Regular, ' . $abk . ' ABK), kelompok guru-murid dibuat.');

        foreach ($seed['periods'] as $period) {
            $period['tahun_ajaran_id'] = $ids['years'][$period['tahun']]; unset($period['tahun']);
            $periodId = $ins('periode_penilaian', $period);
            $ids['periods'][$period['nama']] = $periodId;
            ReportWorkflow::syncPeriod($periodId); // sama seperti periode yang dibuat lewat UI
        }
        $log('Periode penilaian: ' . count($seed['periods']) . ' (2025/2026 dan 2026/2027).');
        return $ids;
    }

    /** Superadmin non-demo tanpa data pegawai. */
    private static function keptUsers(PDO $db): array
    {
        return $db->query("SELECT u.id,u.email FROM users u JOIN roles r ON r.id=u.role_id
            WHERE r.nama='Superadmin' AND u.karyawan_id IS NULL AND u.email NOT LIKE '%@demo.zivana.test' ORDER BY u.id")->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function tables(PDO $db): array
    {
        return $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Anak sebelum induk, dihitung dari foreign key di database yang sedang dipakai. */
    private static function deleteOrder(PDO $db, array $tables): array
    {
        $clear = array_values(array_intersect(self::CLEAR_TABLES, $tables));
        $edges = $db->query("SELECT DISTINCT table_name AS child, referenced_table_name AS parent FROM information_schema.key_column_usage
            WHERE table_schema=DATABASE() AND referenced_table_name IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $children = array_fill_keys($clear, []);
        foreach ($edges as $e) {
            if ($e['child'] === $e['parent']) continue;
            if (in_array($e['parent'], $clear, true) && !in_array($e['child'], $clear, true)) {
                throw new DomainException('Tabel dipertahankan ' . $e['child'] . ' merujuk tabel yang akan dikosongkan ' . $e['parent'] . '.');
            }
            if (isset($children[$e['parent']]) && in_array($e['child'], $clear, true)) $children[$e['parent']][$e['child']] = true;
        }
        $order = [];
        while ($children) {
            $ready = array_keys(array_filter($children, fn($c) => !$c));
            if (!$ready) throw new DomainException('Urutan penghapusan tidak dapat ditentukan (siklus foreign key).');
            sort($ready);
            foreach ($ready as $table) {
                $order[] = $table; unset($children[$table]);
                foreach ($children as &$c) unset($c[$table]);
                unset($c);
            }
        }
        return $order;
    }

    /**
     * Data fiktif yang realistis. Nomor telepon memakai pola 0811-0000-xxxx agar tidak menjangkau nomor orang sungguhan.
     * Dengan $pilot (PilotDataImport::fromXlsx): guru dan murid diganti data file; Kepala Sekolah, Admin, dan kelas seed tetap,
     * kelas dari file yang belum ada ditambahkan.
     */
    public static function seedData(?array $pilot = null): array
    {
        $d = '@' . self::EMAIL_DOMAIN;
        $staff = [
            'kepala' => ['nama' => 'Andi Nurul Fadhilah, S.Pd.', 'jabatan' => 'Kepala Sekolah', 'email' => 'nurul.fadhilah' . $d],
            'admin' => ['nama' => 'Rahmawati Syam', 'jabatan' => 'Admin', 'email' => 'rahmawati.syam' . $d],
            'guru_akar' => ['nama' => 'Siti Aisyah Rahman, S.Pd.', 'jabatan' => 'Guru Kelas', 'email' => 'aisyah.rahman' . $d],
            'guru_batang' => ['nama' => 'Nurhaliza Putri, S.Pd.', 'jabatan' => 'Guru Kelas', 'email' => 'nurhaliza.putri' . $d],
            'guru_ranting' => ['nama' => 'Fitriani Ramadhani, S.Pd.', 'jabatan' => 'Guru Kelas', 'email' => 'fitriani.ramadhani' . $d],
            'guru_ranting2' => ['nama' => 'Muh. Rizal Hakim, S.Pd.', 'jabatan' => 'Guru Kelas', 'email' => 'rizal.hakim' . $d],
            'guru_daun' => ['nama' => 'Dewi Kartika Sari, S.Pd.', 'jabatan' => 'Guru Kelas', 'email' => 'dewi.kartika' . $d],
            'shadow_1' => ['nama' => 'Andi Rezky Amalia, S.Psi.', 'jabatan' => 'Guru Shadow', 'email' => 'rezky.amalia' . $d],
            'shadow_2' => ['nama' => 'Nur Afni Hasanuddin', 'jabatan' => 'Guru Shadow', 'email' => 'afni.hasanuddin' . $d],
        ];
        $classes = [
            'akar' => ['level' => 'Akar', 'nama' => 'Melati'],
            'batang' => ['level' => 'Batang', 'nama' => 'Kenanga'],
            'ranting' => ['level' => 'Ranting', 'nama' => 'Akasia'],
            'daun' => ['level' => 'Daun', 'nama' => 'Mahoni'],
        ];
        $student = static function (int $n, string $nama, string $panggilan, string $jk, string $lahir, string $masuk, string $ayah, string $ibu, array $extra = []): array {
            return array_merge([
                'nama_lengkap' => $nama, 'nama_panggilan' => $panggilan, 'nisn' => sprintf('31%08d', 45120 + $n * 37), 'agama' => 'Islam',
                'nik' => sprintf('737101%s%04d', date('dmy', strtotime($lahir)), $n), 'no_registrasi_akte' => sprintf('7371-LT-%s-%04d', date('dmY', strtotime($lahir)), 120 + $n),
                'jenis_kelamin' => $jk, 'tempat_lahir' => 'Makassar', 'tanggal_lahir' => $lahir, 'alamat' => 'Jl. Perintis Kemerdekaan, Kota Makassar',
                'tanggal_masuk_sekolah' => $masuk, 'jenis_kebutuhan' => null, 'kelengkapan_berkas' => 'Akta kelahiran, KK, pas foto',
                'alamat_domisili' => 'Kota Makassar', 'anak_ke' => 1, 'jumlah_saudara' => 1,
                'nama_ayah' => $ayah, 'pendidikan_ayah' => 'S1', 'pekerjaan_ayah' => 'Karyawan Swasta', 'telp_ayah' => sprintf('08110000%04d', $n * 2),
                'nama_ibu' => $ibu, 'pendidikan_ibu' => 'S1', 'pekerjaan_ibu' => 'Ibu Rumah Tangga', 'telp_ibu' => sprintf('08110000%04d', $n * 2 + 1),
                'status' => 'bersekolah',
            ], $extra);
        };
        $students = [
            // Aktif: satu murid per guru, setiap level, Regular dan ABK.
            ['kelas' => 'akar', 'guru' => 'guru_akar', 'kondisi' => 'Reguler', 'row' => $student(1, 'Aisyah Humaira Putri', 'Aisyah', 'P', '2022-05-14', '2025-07-14', 'Muhammad Ilham', 'Nur Aulia')],
            ['kelas' => 'batang', 'guru' => 'guru_batang', 'kondisi' => 'Reguler', 'row' => $student(2, 'Muhammad Alif Pratama', 'Alif', 'L', '2021-08-02', '2024-07-15', 'Andi Pratama', 'Siti Rahmah')],
            ['kelas' => 'ranting', 'guru' => 'guru_ranting', 'kondisi' => 'Reguler', 'row' => $student(3, 'Nayla Azzahra Ramadhani', 'Nayla', 'P', '2020-11-21', '2023-07-17', 'Ahmad Fauzi', 'Rini Ramadhani')],
            ['kelas' => 'ranting', 'guru' => 'guru_ranting2', 'kondisi' => 'Reguler', 'row' => $student(4, 'Fathan Al Ghifari', 'Fathan', 'L', '2020-06-09', '2023-07-17', 'Irfan Hidayat', 'Dian Lestari')],
            ['kelas' => 'daun', 'guru' => 'guru_daun', 'kondisi' => 'Reguler', 'row' => $student(5, 'Khadijah Zahira Amani', 'Dijah', 'P', '2019-12-03', '2022-07-18', 'Syamsul Bahri', 'Hasnah Amani')],
            ['kelas' => 'batang', 'guru' => 'shadow_1', 'kondisi' => 'Berkebutuhan Khusus', 'row' => $student(6, 'Muhammad Rafif Hidayat', 'Rafif', 'L', '2021-03-17', '2024-07-15', 'Hasan Basri', 'Nurhayati', ['jenis_kebutuhan' => 'Speech Delay', 'anak_ke' => 2, 'jumlah_saudara' => 2])],
            ['kelas' => 'daun', 'guru' => 'shadow_2', 'kondisi' => 'Berkebutuhan Khusus', 'row' => $student(7, 'Zahra Aulia Kirana', 'Zahra', 'P', '2019-09-25', '2022-07-18', 'Rahmat Hidayat', 'Wulandari', ['jenis_kebutuhan' => 'Autism Spectrum Disorder (ASD)'])],
            // Status murid lain: tidak muncul di rapor, tetap ada di Manajemen Murid.
            ['kelas' => 'akar', 'guru' => null, 'kondisi' => 'Reguler', 'row' => $student(8, 'Raffasya Putra Mahendra', 'Raffa', 'L', '2022-02-10', '2025-07-14', 'Mahendra Putra', 'Ayu Lestari', ['status' => 'tanpa_keterangan'])],
            ['kelas' => 'batang', 'guru' => null, 'kondisi' => 'Berkebutuhan Khusus', 'row' => $student(9, 'Alya Safira Maharani', 'Alya', 'P', '2021-10-30', '2024-07-15', 'Yusuf Maharani', 'Indah Sari', ['status' => 'berhenti', 'jenis_kebutuhan' => 'ADHD'])],
            ['kelas' => null, 'guru' => null, 'kondisi' => 'Reguler', 'row' => $student(10, 'Arkan Maulana Yusuf', 'Arkan', 'L', '2019-04-12', '2022-07-18', 'Yusuf Maulana', 'Fitri Handayani', ['status' => 'tamat'])],
        ];
        $periods = [];
        foreach (['2025' => '2025/2026', '2026' => '2026/2027'] as $tahun => $label) {
            $y = (int) $tahun;
            foreach ([
                ['ganjil', 'Tengah Semester', "$y-09-15", "$y-10-10"],
                ['ganjil', 'Akhir Semester', "$y-12-01", "$y-12-19"],
                ['genap', 'Tengah Semester', ($y + 1) . '-03-02', ($y + 1) . '-03-27'],
                ['genap', 'Akhir Semester', ($y + 1) . '-06-01', ($y + 1) . '-06-19'],
            ] as [$semester, $tipe, $awal, $akhir]) {
                $periods[] = ['tahun' => $tahun, 'nama' => 'Rapor ' . $tipe . ' ' . ucfirst($semester) . ' ' . $label, 'semester' => $semester,
                    'tipe' => $tipe, 'kategori' => 'Rapor Murid', 'awal_periode' => $awal, 'akhir_periode' => $akhir];
            }
        }
        if ($pilot !== null) {
            $staff = ['kepala' => $staff['kepala'], 'admin' => $staff['admin']];
            $teacherKeys = [];
            foreach (array_values($pilot['teachers']) as $i => $teacher) {
                $teacherKeys[$teacher['nama']] = 'guru_' . ($i + 1);
                $staff['guru_' . ($i + 1)] = $teacher;
            }
            $classKeys = [];
            foreach ($classes as $key => $class) $classKeys[$class['level'] . ' ' . $class['nama']] = $key;
            foreach ($pilot['classes'] as $classKey => $class) {
                if (!isset($classKeys[$classKey])) { $classes[$classKey] = $class; $classKeys[$classKey] = $classKey; }
            }
            $students = array_map(fn($s) => ['kelas' => $classKeys[$s['kelas']], 'guru' => $teacherKeys[$s['guru']]] + $s, $pilot['students']);
        }
        return [
            'school' => ['nama_legal' => 'Yayasan Zivana Insan Mandiri', 'nama_komersial' => 'TK Zivana Montessori Makassar', 'bentuk_pendidikan' => 'TK',
                'npsn' => '70015857', 'alamat' => 'Kota Makassar, Sulawesi Selatan', 'no_telepon' => '081100000000', 'email' => 'info' . $d],
            'years' => ['2025' => ['tahun_awal' => 2025, 'tahun_akhir' => 2026, 'is_active' => 0], '2026' => ['tahun_awal' => 2026, 'tahun_akhir' => 2027, 'is_active' => 1]],
            'staff' => $staff, 'classes' => $classes, 'students' => $students, 'periods' => $periods,
        ];
    }
}
