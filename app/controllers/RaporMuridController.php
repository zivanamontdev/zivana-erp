<?php

class RaporMuridController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'lihat');

        $db = Database::getInstance();

        $years = (new TahunAjaran())->all('tahun_awal DESC');
        $active = array_values(array_filter($years, static fn($y) => $y['is_active']));
        $yearId = (int) $this->input('tahun_ajaran_id', $active[0]['id'] ?? ($years[0]['id'] ?? 0));
        $periods = $db->prepare('SELECT * FROM periode_penilaian WHERE tahun_ajaran_id=? ORDER BY awal_periode DESC');
        $periods->execute([$yearId]);
        $periodeList = $periods->fetchAll();

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
            'tahunOptions' => $years,
            'tahunId' => $yearId,
            'canEdit' => (new RoleMiddleware())->check('Murid', 'Rapor Murid', 'edit'),
        ]);
    }

    public function approve(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'edit');

        $rapor = (new Rapor())->find((int) $raporId);

        if ($rapor && $rapor['status'] === 'menunggu_persetujuan') {
            $stmt = Database::getInstance()->prepare("UPDATE rapor SET status='disetujui', disetujui_oleh=?, disetujui_at=? WHERE id=? AND status='menunggu_persetujuan'");
            $stmt->execute([$_SESSION['user_id'], date('Y-m-d H:i:s'), (int) $raporId]);
        }

        $this->redirect('/rapor-murid');
    }

    public function show(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'lihat');

        $db = Database::getInstance();

        $stmt = $db->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas,
             pp.semester, pp.tipe AS periode_tipe, ta.tahun_awal, ta.tahun_akhir
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             JOIN sesi_pembagian_rapor sp ON sp.id=r.sesi_pembagian_id
             JOIN periode_penilaian pp ON pp.id=sp.periode_id
             JOIN tahun_ajaran ta ON ta.id=pp.tahun_ajaran_id
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
        if ($rapor['status'] === 'belum_diisi') {
            $this->redirect('/rapor-murid');
            return;
        }

        $siblingStmt = $db->prepare(
            'SELECT r.id, mu.nama_lengkap
             FROM rapor r JOIN murid mu ON mu.id = r.murid_id
             WHERE r.sesi_pembagian_id = :sesi_id AND r.id != :current_id AND r.status <> \'belum_diisi\'
             ORDER BY mu.nama_lengkap ASC'
        );
        $siblingStmt->execute(['sesi_id' => $rapor['sesi_pembagian_id'], 'current_id' => $rapor['id']]);

        $this->view('admin.rapor-murid.show', [
            'pageTitle' => 'Pratinjau Rapor Murid',
            'pageTitleHtml' => muridSwitcherTitle($rapor, $siblingStmt->fetchAll(), '/rapor-murid/{id}'),
            'breadcrumb' => breadcrumb('Rapor Murid', 'Pratinjau Rapor Murid'),
            'activeNavItem' => 'rapor-murid',
            'rapor' => $rapor,
            'canApprove' => (new RoleMiddleware())->check('Murid', 'Rapor Murid', 'edit'),
            'areas' => $this->buildStructureWithNilai((int) $rapor['template_id'], (int) $rapor['id']),
            'legenda' => (new SkalaNilai())->opsi(1),
        ]);
    }

    public function downloadPdf(string $raporId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Rapor Murid', 'pdf');

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT r.*, mu.nama_lengkap, mu.nisn, k.level_kelas, k.nama_kelas,
             pp.semester, pp.tipe AS periode_tipe, ta.tahun_awal, ta.tahun_akhir
             FROM rapor r
             JOIN murid mu ON mu.id = r.murid_id
             JOIN sesi_pembagian_rapor sp ON sp.id=r.sesi_pembagian_id
             JOIN periode_penilaian pp ON pp.id=sp.periode_id
             JOIN tahun_ajaran ta ON ta.id=pp.tahun_ajaran_id
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
        if ($rapor['status'] === 'belum_diisi') {
            $this->redirect('/rapor-murid');
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
