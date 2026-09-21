<?php

class RaporMuridController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'lihat');

        $db = Database::getInstance();

        $periodeList = $db->query('SELECT * FROM periode_penilaian ORDER BY awal_periode DESC')->fetchAll();

        foreach ($periodeList as &$periode) {
            $sesiStmt = $db->prepare('SELECT * FROM sesi_pembagian_rapor WHERE periode_id = :id ORDER BY tanggal_mulai DESC');
            $sesiStmt->execute(['id' => $periode['id']]);
            $periode['sesi'] = $sesiStmt->fetchAll();

            $periode['jumlah_murid'] = 0;

            foreach ($periode['sesi'] as &$sesi) {
                $raporStmt = $db->prepare(
                    'SELECT r.*, mu.nama_lengkap
                     FROM rapor r JOIN murid mu ON mu.id = r.murid_id
                     WHERE r.sesi_pembagian_id = :id
                     ORDER BY mu.nama_lengkap ASC'
                );
                $raporStmt->execute(['id' => $sesi['id']]);
                $sesi['rapor'] = $raporStmt->fetchAll();
                $periode['jumlah_murid'] += count($sesi['rapor']);
            }
            unset($sesi);
        }
        unset($periode);

        $this->view('admin.rapor-murid.index', [
            'pageTitle' => 'Rapor Murid',
            'breadcrumb' => null,
            'activeNavItem' => 'rapor-murid',
            'periodeList' => $periodeList,
            'periodeOptions' => (new PeriodePenilaian())->all('nama ASC'),
            'templateOptions' => (new TemplateRapor())->where('kategori', 'rapor_murid'),
            'canEdit' => (new RoleMiddleware())->check('Murid', 'Rapor Murid', 'edit'),
        ]);
    }

    /**
     * Buat Sesi Pembagian Rapor baru DAN sekaligus generate baris
     * `rapor` untuk semua murid aktif (status=bersekolah), meng-assign
     * guru_id dari kelas_guru_murid (guru pertama yang ditemukan).
     */
    public function storeSesi(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'edit');

        $periodeId = (int) $this->input('periode_id', 0);
        $templateId = (int) $this->input('template_id', 0);
        $nama = trim((string) $this->input('nama', ''));
        $tanggalMulai = trim((string) $this->input('tanggal_mulai', ''));
        $tanggalSelesai = trim((string) $this->input('tanggal_selesai', ''));

        if ($periodeId === 0 || $templateId === 0 || $nama === '' || $tanggalMulai === '' || $tanggalSelesai === '') {
            $this->redirect('/rapor-murid');
            return;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $sesiId = (new SesiPembagianRapor())->create([
                'periode_id' => $periodeId,
                'template_id' => $templateId,
                'nama' => $nama,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ]);

            $muridList = $db->query("SELECT id FROM murid WHERE status = 'bersekolah'")->fetchAll();
            $guruStmt = $db->prepare('SELECT guru_id FROM kelas_guru_murid WHERE murid_id = :murid_id LIMIT 1');
            $raporModel = new Rapor();

            foreach ($muridList as $murid) {
                $guruStmt->execute(['murid_id' => $murid['id']]);
                $guruRow = $guruStmt->fetch();

                $raporModel->create([
                    'murid_id' => $murid['id'],
                    'sesi_pembagian_id' => $sesiId,
                    'template_id' => $templateId,
                    'guru_id' => $guruRow['guru_id'] ?? null,
                    'status' => 'belum_diisi',
                ]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->redirect('/rapor-murid');
    }

    /**
     * Setujui rapor (ubah status menjadi 'disetujui'). Siapa yang
     * boleh akses ini diatur lewat RBAC permission 'edit' pada Murid >
     * Rapor Murid — TIDAK di-hardcode ke role tertentu (lihat
     * cookbook/todo.md Fase 7 soal keputusan role approver).
     */
    public function approve(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'edit');

        $rapor = (new Rapor())->find((int) $raporId);

        if ($rapor && $rapor['status'] === 'menunggu_persetujuan') {
            (new Rapor())->update((int) $raporId, [
                'status' => 'disetujui',
                'disetujui_oleh' => $_SESSION['user_id'],
                'disetujui_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->redirect('/rapor-murid');
    }

    public function show(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'lihat');

        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             LEFT JOIN kelas k ON k.id = mu.kelas_id
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $raporId]);
        $rapor = $stmt->fetch();

        if (!$rapor) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $siblingStmt = $db->prepare(
            'SELECT r.id, mu.nama_lengkap
             FROM rapor r JOIN murid mu ON mu.id = r.murid_id
             WHERE r.sesi_pembagian_id = :sesi_id AND r.id != :current_id
             ORDER BY mu.nama_lengkap ASC'
        );
        $siblingStmt->execute(['sesi_id' => $rapor['sesi_pembagian_id'], 'current_id' => $rapor['id']]);

        $this->view('admin.rapor-murid.show', [
            'pageTitle' => 'Pratinjau Rapor Murid',
            'pageTitleHtml' => muridSwitcherTitle($rapor, $siblingStmt->fetchAll(), '/rapor-murid/{id}'),
            'breadcrumb' => breadcrumb('Rapor Murid', 'Pratinjau Rapor Murid'),
            'activeNavItem' => 'rapor-murid',
            'rapor' => $rapor,
            'areas' => $this->buildStructureWithNilai((int) $rapor['template_id'], (int) $rapor['id']),
            'legenda' => (new SkalaNilai())->opsi(1),
        ]);
    }

    public function downloadPdf(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'lihat');

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             LEFT JOIN kelas k ON k.id = mu.kelas_id
             WHERE r.id = :id'
        );
        $stmt->execute(['id' => $raporId]);
        $rapor = $stmt->fetch();

        if (!$rapor) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $areas = $this->buildStructureWithNilai((int) $rapor['template_id'], (int) $rapor['id']);
        $legenda = (new SkalaNilai())->opsi(1);
        $forPdf = true;

        ob_start();
        require VIEW_PATH . '/admin/rapor-murid/_document.php';
        $documentHtml = ob_get_clean();

        $css = file_get_contents(ROOT_PATH . '/public/assets/css/rapor-document-pdf.css');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . $documentHtml . '</body></html>';

        $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'rapor-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($rapor['nama_lengkap'])) . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /**
     * Sama seperti TemplateRaporController::buildStructure() tapi
     * setiap item juga dilengkapi nilai yang sudah diisi guru (kalau
     * ada) untuk semester ganjil/genap.
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
                    $nilaiStmt = $db->prepare(
                        'SELECT rn.semester, sno.simbol
                         FROM rapor_nilai rn
                         JOIN skala_nilai_opsi sno ON sno.id = rn.skala_nilai_opsi_id
                         WHERE rn.rapor_id = :rapor_id AND rn.item_id = :item_id'
                    );
                    $nilaiStmt->execute(['rapor_id' => $raporId, 'item_id' => $item['id']]);

                    $item['nilai_ganjil'] = null;
                    $item['nilai_genap'] = null;

                    foreach ($nilaiStmt->fetchAll() as $n) {
                        if ($n['semester'] === 'ganjil') {
                            $item['nilai_ganjil'] = $n['simbol'];
                        } else {
                            $item['nilai_genap'] = $n['simbol'];
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
