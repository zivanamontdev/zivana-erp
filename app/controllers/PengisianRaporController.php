<?php

class PengisianRaporController extends Controller
{
    public function show(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');

        $rapor = $this->findOwnRapor((int) $raporId);

        if (!$rapor) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        // Sesuai cookbook/todo.md Fase 8: begitu status bukan lagi
        // 'belum_diisi', form terkunci dari edit lebih lanjut.
        if ($rapor['status'] !== 'belum_diisi') {
            $this->redirect('/portal-guru/rapor/' . $raporId . '/pratinjau');
            return;
        }

        $semester = $this->semesterUntukSesi((int) $rapor['sesi_pembagian_id']);
        $areas = $this->buildStructureForPengisian((int) $rapor['template_id'], (int) $rapor['id'], $semester);
        $catatanList = $this->getCatatanMap((int) $rapor['id']);

        [$totalItem, $terisiItem] = $this->hitungProgress($areas);

        $this->view('portal-guru.pengisian-rapor', [
            'pageTitle' => 'Pengisian Rapor',
            'breadcrumb' => null,
            'activeNavItem' => 'portal-daftar-murid',
            'rapor' => $rapor,
            'areas' => $areas,
            'semester' => $semester,
            'catatanList' => $catatanList,
            'daftarMuridLain' => $this->daftarMuridLainDiSesi((int) $rapor['sesi_pembagian_id'], (int) $rapor['id']),
            'totalItem' => $totalItem,
            'terisiItem' => $terisiItem,
        ]);
    }

    public function simpan(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'edit');

        $rapor = $this->findOwnRapor((int) $raporId);

        if (!$rapor || $rapor['status'] !== 'belum_diisi') {
            $this->redirect('/portal-guru/rapor/' . $raporId);
            return;
        }

        $semester = $this->semesterUntukSesi((int) $rapor['sesi_pembagian_id']);
        $this->simpanNilaiDanCatatan((int) $raporId, $semester);

        $this->redirect('/portal-guru/rapor/' . $raporId);
    }

    public function selesaikan(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'edit');

        $rapor = $this->findOwnRapor((int) $raporId);

        if (!$rapor || $rapor['status'] !== 'belum_diisi') {
            $this->redirect('/portal-guru/rapor/' . $raporId);
            return;
        }

        $semester = $this->semesterUntukSesi((int) $rapor['sesi_pembagian_id']);
        $this->simpanNilaiDanCatatan((int) $raporId, $semester);

        (new Rapor())->update((int) $raporId, ['status' => 'menunggu_persetujuan']);

        $this->redirect('/portal-guru/rapor/' . $raporId . '/pratinjau');
    }

    public function pratinjau(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');

        $rapor = $this->findOwnRapor((int) $raporId);

        if (!$rapor) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $this->view('portal-guru.pratinjau-rapor', [
            'pageTitle' => 'Pratinjau Rapor Murid',
            'breadcrumb' => null,
            'activeNavItem' => 'portal-daftar-murid',
            'rapor' => $rapor,
            'areas' => $this->buildStructureWithNilai((int) $rapor['template_id'], (int) $rapor['id']),
            'legenda' => (new SkalaNilai())->opsi(1),
        ]);
    }

    /**
     * Ambil rapor HANYA kalau memang milik guru yang sedang login —
     * mencegah guru A membuka/mengubah rapor guru B lewat tebak ID
     * (IDOR). Ini di LUAR RoleMiddleware karena RoleMiddleware cuma
     * cek role Guru secara umum, bukan kepemilikan baris spesifik.
     */
    private function findOwnRapor(int $raporId): ?array
    {
        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);

        $stmt = Database::getInstance()->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             LEFT JOIN kelas k ON k.id = mu.kelas_id
             WHERE r.id = :id AND r.guru_id = :guru_id'
        );
        $stmt->execute(['id' => $raporId, 'guru_id' => $guruId]);

        return $stmt->fetch() ?: null;
    }

    private function daftarMuridLainDiSesi(int $sesiId, int $currentRaporId): array
    {
        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);

        $stmt = Database::getInstance()->prepare(
            'SELECT r.id, mu.nama_lengkap
             FROM rapor r JOIN murid mu ON mu.id = r.murid_id
             WHERE r.sesi_pembagian_id = :sesi_id AND r.guru_id = :guru_id
             ORDER BY mu.nama_lengkap ASC'
        );
        $stmt->execute(['sesi_id' => $sesiId, 'guru_id' => $guruId]);

        return $stmt->fetchAll();
    }

    private function getCatatanMap(int $raporId): array
    {
        $stmt = Database::getInstance()->prepare('SELECT area_id, catatan FROM rapor_catatan_guru WHERE rapor_id = :id');
        $stmt->execute(['id' => $raporId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['area_id']] = $row['catatan'];
        }

        return $map;
    }

    private function simpanNilaiDanCatatan(int $raporId, string $semester): void
    {
        $db = Database::getInstance();
        $nilaiInput = $this->input('nilai', []); // ['item_id' => opsiId] — satu dropdown per item (lihat buildStructureForPengisian)
        $catatanInput = $this->input('catatan', []); // ['area_id' => teks]

        if (!is_array($nilaiInput)) {
            $nilaiInput = [];
        }
        if (!is_array($catatanInput)) {
            $catatanInput = [];
        }

        $db->beginTransaction();

        try {
            $upsertNilai = $db->prepare(
                'INSERT INTO rapor_nilai (rapor_id, item_id, semester, skala_nilai_opsi_id)
                 VALUES (:rapor_id, :item_id, :semester, :opsi_id)
                 ON DUPLICATE KEY UPDATE skala_nilai_opsi_id = VALUES(skala_nilai_opsi_id)'
            );

            foreach ($nilaiInput as $itemId => $opsiId) {
                if ($opsiId === '' || $opsiId === null) {
                    continue;
                }
                $upsertNilai->execute([
                    'rapor_id' => $raporId,
                    'item_id' => (int) $itemId,
                    'semester' => $semester,
                    'opsi_id' => (int) $opsiId,
                ]);
            }

            $upsertCatatan = $db->prepare(
                'INSERT INTO rapor_catatan_guru (rapor_id, area_id, catatan)
                 VALUES (:rapor_id, :area_id, :catatan)
                 ON DUPLICATE KEY UPDATE catatan = VALUES(catatan)'
            );

            foreach ($catatanInput as $areaId => $teks) {
                $upsertCatatan->execute([
                    'rapor_id' => $raporId,
                    'area_id' => (int) $areaId,
                    'catatan' => trim((string) $teks),
                ]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function hitungProgress(array $areas): array
    {
        $total = 0;
        $terisi = 0;

        foreach ($areas as $area) {
            foreach ($area['subkategori'] as $sub) {
                foreach ($sub['item'] as $item) {
                    $total++;
                    if ($item['nilai_opsi_id']) {
                        $terisi++;
                    }
                }
            }
        }

        return [$total, $terisi];
    }

    /**
     * [ASUMSI] Crawl form Pengisian Rapor hanya menunjukkan SATU
     * dropdown per item (bukan Ganjil+Genap sekaligus seperti di
     * dokumen/pratinjau final). Semester yang aktif diisi ditentukan
     * otomatis dari tipe periode sesi pembagian rapor saat ini —
     * 'Tengah Semester' → ganjil, 'Akhir Semester' → genap (nilai tipe
     * ini persis sesuai ASUMSI di PeriodePenilaianController::TIPE_OPTIONS).
     * Fallback 'ganjil' kalau tipe periode belum sesuai pola yang dikenal.
     */
    private function semesterUntukSesi(int $sesiId): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT pp.tipe
             FROM sesi_pembagian_rapor s
             JOIN periode_penilaian pp ON pp.id = s.periode_id
             WHERE s.id = :id'
        );
        $stmt->execute(['id' => $sesiId]);
        $tipe = $stmt->fetchColumn();

        return $tipe === 'Akhir Semester' ? 'genap' : 'ganjil';
    }

    /**
     * Struktur khusus form Pengisian Rapor — satu nilai (`nilai`/
     * `nilai_opsi_id`) per item untuk semester yang sedang aktif,
     * plus daftar `opsi` (skala_nilai_opsi) untuk isi dropdown.
     * Beda dengan buildStructureWithNilai() di bawah yang menampilkan
     * DUA kolom (Ganjil+Genap) sekaligus untuk dokumen/pratinjau final.
     */
    private function buildStructureForPengisian(int $templateId, int $raporId, string $semester): array
    {
        $db = Database::getInstance();

        $areaStmt = $db->prepare('SELECT * FROM template_rapor_area WHERE template_id = :id ORDER BY display_order ASC');
        $areaStmt->execute(['id' => $templateId]);
        $areas = $areaStmt->fetchAll();

        foreach ($areas as &$area) {
            $subStmt = $db->prepare('SELECT * FROM template_rapor_subkategori WHERE area_id = :id ORDER BY display_order ASC');
            $subStmt->execute(['id' => $area['id']]);
            $area['subkategori'] = $subStmt->fetchAll();

            foreach ($area['subkategori'] as &$sub) {
                $itemStmt = $db->prepare('SELECT * FROM template_rapor_item WHERE subkategori_id = :id ORDER BY display_order ASC');
                $itemStmt->execute(['id' => $sub['id']]);
                $sub['item'] = $itemStmt->fetchAll();

                foreach ($sub['item'] as &$item) {
                    $item['opsi'] = (new SkalaNilai())->opsi((int) $item['skala_nilai_id']);

                    $nilaiStmt = $db->prepare(
                        'SELECT skala_nilai_opsi_id
                         FROM rapor_nilai
                         WHERE rapor_id = :rapor_id AND item_id = :item_id AND semester = :semester'
                    );
                    $nilaiStmt->execute(['rapor_id' => $raporId, 'item_id' => $item['id'], 'semester' => $semester]);

                    $item['nilai_opsi_id'] = $nilaiStmt->fetchColumn() ?: null;
                }
                unset($item);
            }
            unset($sub);
        }
        unset($area);

        return $areas;
    }

    /**
     * Sama seperti RaporMuridController::buildStructureWithNilai() —
     * duplikasi kecil disengaja daripada bikin base class prematur
     * untuk 2 pemakaian (lihat prinsip "jangan abstraksi dini").
     * Dipakai untuk Pratinjau (versi Guru) — menampilkan DUA kolom
     * (Ganjil+Genap) sekaligus persis seperti dokumen rapor final,
     * beda dengan buildStructureForPengisian() di atas yang cuma satu
     * nilai per item untuk form isi.
     */
    private function buildStructureWithNilai(int $templateId, int $raporId): array
    {
        $db = Database::getInstance();

        $areaStmt = $db->prepare('SELECT * FROM template_rapor_area WHERE template_id = :id ORDER BY display_order ASC');
        $areaStmt->execute(['id' => $templateId]);
        $areas = $areaStmt->fetchAll();

        foreach ($areas as &$area) {
            $subStmt = $db->prepare('SELECT * FROM template_rapor_subkategori WHERE area_id = :id ORDER BY display_order ASC');
            $subStmt->execute(['id' => $area['id']]);
            $area['subkategori'] = $subStmt->fetchAll();

            foreach ($area['subkategori'] as &$sub) {
                $itemStmt = $db->prepare('SELECT * FROM template_rapor_item WHERE subkategori_id = :id ORDER BY display_order ASC');
                $itemStmt->execute(['id' => $sub['id']]);
                $sub['item'] = $itemStmt->fetchAll();

                foreach ($sub['item'] as &$item) {
                    $item['opsi'] = (new SkalaNilai())->opsi((int) $item['skala_nilai_id']);

                    $nilaiStmt = $db->prepare(
                        'SELECT rn.semester, rn.skala_nilai_opsi_id, sno.simbol
                         FROM rapor_nilai rn
                         JOIN skala_nilai_opsi sno ON sno.id = rn.skala_nilai_opsi_id
                         WHERE rn.rapor_id = :rapor_id AND rn.item_id = :item_id'
                    );
                    $nilaiStmt->execute(['rapor_id' => $raporId, 'item_id' => $item['id']]);

                    $item['nilai_ganjil'] = null;
                    $item['nilai_ganjil_opsi_id'] = null;
                    $item['nilai_genap'] = null;
                    $item['nilai_genap_opsi_id'] = null;

                    foreach ($nilaiStmt->fetchAll() as $n) {
                        if ($n['semester'] === 'ganjil') {
                            $item['nilai_ganjil'] = $n['simbol'];
                            $item['nilai_ganjil_opsi_id'] = $n['skala_nilai_opsi_id'];
                        } else {
                            $item['nilai_genap'] = $n['simbol'];
                            $item['nilai_genap_opsi_id'] = $n['skala_nilai_opsi_id'];
                        }
                    }
                }
                unset($item);
            }
            unset($sub);
        }
        unset($area);

        return $areas;
    }
}
