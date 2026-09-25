<?php

class PengisianRaporController extends Controller
{
    public function show(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'edit');

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

        try { $semester = $this->semesterUntukSesi((int) $rapor['sesi_pembagian_id']); }
        catch (DomainException $e) {
            $_SESSION['report_error'] = $e->getMessage();
            $this->redirect('/portal-guru/dashboard');
            return;
        }
        $areas = $this->buildStructureForPengisian((int) $rapor['template_id'], (int) $rapor['id'], $semester);
        $catatanList = $this->getCatatanMap((int) $rapor['id']);

        [$totalItem, $terisiItem] = $this->hitungProgress($areas);

        $this->view('portal-guru.pengisian-rapor', [
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
        $this->saveEntry($raporId, false);
    }

    public function arsipkan(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'edit');
        $this->saveEntry($raporId, false, true);
    }

    public function selesaikan(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'kirim');
        $this->saveEntry($raporId, true);
    }

    private function saveEntry(string $raporId, bool $submit, bool $archive = false): void
    {
        try {
            $sessionId = ReportEntry::save(
                (int)$raporId,
                (int)($_SESSION['karyawan_id'] ?? 0),
                $this->input('nilai', []),
                $this->input('catatan', []),
                $submit,
                $archive
            );
        } catch (DomainException $e) {
            $_SESSION['report_error'] = $e->getMessage();
            $this->redirect('/portal-guru/rapor/' . (int)$raporId);
            return;
        }
        if ($archive) {
            $this->redirect('/portal-guru/dashboard?sesi_id=' . $sessionId);
            return;
        }
        $this->redirect('/portal-guru/rapor/' . (int)$raporId . ($submit ? '/pratinjau' : ''));
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
            // [FIX] H1 seharusnya nama murid + switcher (lihat catatan di
            // muridSwitcherTitle()), dan breadcrumb sebelumnya null padahal
            // dikonfirmasi ADA dari assets/ss/Portal Guru - menu_dashboard -
            // halaman_pratinjau_rapor_murid.svg.
            'pageTitleHtml' => muridSwitcherTitle(
                $rapor,
                $this->daftarMuridLainDiSesi((int) $rapor['sesi_pembagian_id'], (int) $rapor['id']),
                '/portal-guru/rapor/{id}/pratinjau'
            ),
            'breadcrumb' => breadcrumb(['Rapor Murid', '/portal-guru/murid'], 'Pratinjau Rapor Murid'),
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
    public function downloadPdf(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'pdf');
        $rapor = $this->findOwnRapor((int)$raporId);
        if (!$rapor) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }
        $areas = $this->buildStructureWithNilai((int)$rapor['template_id'], (int)$rapor['id']);
        $legenda = (new SkalaNilai())->opsi(1);
        $forPdf = true;
        ob_start();
        require VIEW_PATH . '/admin/rapor-murid/_document.php';
        $document = ob_get_clean();
        $css = file_get_contents(ROOT_PATH . '/public/assets/css/rapor-document-pdf.css');
        $pdf = new \Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>false]);
        $pdf->loadHtml('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . $document . '</body></html>');
        $pdf->setPaper('A4','portrait');
        $pdf->render();
        $pdf->stream('rapor-' . (int)$rapor['id'] . '.pdf', ['Attachment'=>true]);
        exit;
    }

    private function findOwnRapor(int $raporId): ?array
    {
        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);

        $stmt = Database::getInstance()->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas,
             pp.semester, pp.tipe AS periode_tipe, ta.tahun_awal, ta.tahun_akhir
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             JOIN sesi_pembagian_rapor sp ON sp.id=r.sesi_pembagian_id
             JOIN periode_penilaian pp ON pp.id=sp.periode_id
             JOIN tahun_ajaran ta ON ta.id=pp.tahun_ajaran_id
             LEFT JOIN kelas k ON k.id = mu.kelas_id
             WHERE r.id = :id AND r.guru_id = :guru_id
             AND (r.status<>\'belum_diisi\' OR EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id AND kg.guru_id=r.guru_id))'
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
             AND (r.status<>\'belum_diisi\' OR EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id AND kg.guru_id=r.guru_id))
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

    /** Semester comes exclusively from the selected period, not its middle/end type. */
    private function semesterUntukSesi(int $sesiId): string
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT pp.semester
             FROM sesi_pembagian_rapor s
             JOIN periode_penilaian pp ON pp.id = s.periode_id
             WHERE s.id = :id'
        );
        $stmt->execute(['id' => $sesiId]);
        $semester = $stmt->fetchColumn();
        if (!isset(ReportWorkflow::SEMESTERS[$semester ?: ''])) {
            throw new DomainException('Semester periode belum ditentukan. Hubungi admin untuk memperbarui periode rapor.');
        }
        return $semester;
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
