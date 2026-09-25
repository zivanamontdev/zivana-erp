<?php

class TemplateRaporController extends Controller
{
    /** Urutan tahapan paket rapor (SPEK_ALUR_PENGISIAN bagian 6). */
    private const ERAPOR_ORDER = ['RTS' => 1, 'AGAMA' => 2, 'UMMI' => 3, 'BING' => 4, 'PPI' => 5];

    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');

        $rubrics = $this->eraporRubrics();
        if ($rubrics) {
            $this->view('admin.template-rapor.erapor-index', [
                'pageTitle' => 'Manajemen Rapor',
                'breadcrumb' => breadcrumb('Kurikulum', 'Manajemen Rapor'),
                'activeNavItem' => 'manajemen-template',
                'rubrics' => $rubrics,
                'canPdf' => (new RoleMiddleware())->check('Sekolah', 'Manajemen Template', 'pdf'),
            ]);
            return;
        }

        $tipe = (string) $this->input('tipe', '');
        $kategori = (string) $this->input('kategori', '');

        $sql = 'SELECT * FROM template_rapor WHERE 1=1';
        $params = [];

        if ($tipe !== '') {
            $sql .= ' AND tipe = :tipe';
            $params['tipe'] = $tipe;
        }
        if ($kategori !== '') {
            $sql .= ' AND kategori = :kategori';
            $params['kategori'] = $kategori;
        }

        $sql .= ' ORDER BY nama ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $this->view('admin.template-rapor.index', [
            'pageTitle' => 'Manajemen Rapor',
            'breadcrumb' => breadcrumb('Kurikulum', 'Manajemen Rapor'),
            'search' => trim((string) $this->input('q', '')),
            'tipe' => $tipe,
            'kategori' => $kategori,
            'activeNavItem' => 'manajemen-template',
            'templateList' => $stmt->fetchAll(),
        ]);
    }

    public function previewSemester(string $semester): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');
        $names = ['tengah' => 'Rapor Montessori Tengah Semester', 'akhir' => 'Rapor Montessori Akhir Semester'];
        $template = isset($names[$semester]) ? (new TemplateRapor())->whereFirst('nama', $names[$semester]) : null;
        if (!$template) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }
        $this->show((string) $template['id']);
    }

    public function show(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');

        $template = (new TemplateRapor())->find((int) $id);

        if (!$template) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $this->view('admin.template-rapor.show', [
            // [FIX] Judul H1 dikonfirmasi dari assets/ss/Sekolah - menu_
            // kurikulum - submenu_manajemen_template - halaman_detail_
            // pratinjau_template.svg cuma "Pratinjau" (bukan "Pratinjau
            // Template") — breadcrumb-nya sendiri yang menyebut "Pratinjau
            // Template" sebagai penanda halaman.
            'pageTitle' => 'Pratinjau ' . $template['nama'],
            'breadcrumb' => breadcrumb('Kurikulum', 'Manajemen Rapor', 'Pratinjau'),
            'activeNavItem' => 'manajemen-template',
            'template' => $template,
            'areas' => $this->buildStructure((int) $id),
            'legenda' => (new SkalaNilai())->opsi(1), // [ASUMSI] halaman 1 dokumen selalu pakai skala id=1 (Montessori 4 Simbol)
        ]);
    }

    public function downloadPdf(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'pdf');

        $template = (new TemplateRapor())->find((int) $id);

        if (!$template) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $areas = $this->buildStructure((int) $id);
        $legenda = (new SkalaNilai())->opsi(1);
        $forPdf = true;

        ob_start();
        require VIEW_PATH . '/admin/template-rapor/_document.php';
        $documentHtml = ob_get_clean();

        $css = file_get_contents(ROOT_PATH . '/public/assets/css/rapor-document-pdf.css');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . $documentHtml . '</body></html>';

        $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'pratinjau-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($template['nama'])) . '.pdf';

        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /** Pratinjau template satu rubrik eRapor (PDF yang sama dengan rapor terbit, identitas placeholder). */
    public function eraporPreview(string $jenis): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');
        $rubric = $this->eraporRubric($jenis);
        if (!$rubric) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $semester = $this->semester();
        $this->view('admin.template-rapor.erapor-preview', [
            'pageTitle' => 'Pratinjau ' . $rubric['nama'],
            'breadcrumb' => breadcrumb('Kurikulum', ['Manajemen Rapor', '/kurikulum/manajemen-template'], 'Pratinjau'),
            'activeNavItem' => 'manajemen-template',
            'rubric' => $rubric,
            'semester' => $semester,
            'canPdf' => (new RoleMiddleware())->check('Sekolah', 'Manajemen Template', 'pdf'),
        ]);
    }

    public function eraporPdf(string $jenis): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');
        $download = (string) $this->input('unduh', '') === '1';
        if ($download) $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'pdf');
        $rubric = $this->eraporRubric($jenis);
        if (!$rubric) { http_response_code(404); require VIEW_PATH . '/errors/404.php'; return; }
        $type = $rubric['jenis_dokumen'];
        $semester = $this->semester();
        $package = EraporPublication::templatePackage(Database::getInstance(), $type === 'PPI' ? 'ABK' : 'Regular', $semester, $type);
        $pdf = EraporPackagePdfRenderer::render($package);
        $filename = 'template-' . strtolower($rubric['kode']) . '-' . strtolower($semester) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    private function semester(): string
    {
        return strtolower((string) $this->input('semester', 'ganjil')) === 'genap' ? 'GENAP' : 'GANJIL';
    }

    /** Rubrik eRapor terkunci hasil seed spesifikasi; kosong bila migrasi eRapor belum dijalankan. */
    private function eraporRubrics(): array
    {
        try {
            $rows = Database::getInstance()->query("SELECT r.id,r.kode,r.nama,r.jenis_dokumen,r.versi,h.seeded_at
                FROM erapor_rubrik r LEFT JOIN erapor_seed_history h ON h.rubrik_id=r.id WHERE r.status='terkunci'")->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
        $rows = array_values(array_filter($rows, fn($r) => isset(self::ERAPOR_ORDER[$r['jenis_dokumen']])));
        usort($rows, fn($a, $b) => self::ERAPOR_ORDER[$a['jenis_dokumen']] <=> self::ERAPOR_ORDER[$b['jenis_dokumen']]);
        return $rows;
    }

    private function eraporRubric(string $jenis): ?array
    {
        foreach ($this->eraporRubrics() as $rubric) if (strtolower($rubric['jenis_dokumen']) === strtolower($jenis)) return $rubric;
        return null;
    }

    /**
     * Susun struktur area -> subkategori -> item lengkap dengan opsi
     * skala nilainya masing-masing (dipakai untuk render dokumen).
     */
    private function buildStructure(int $templateId): array
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
            }
            unset($sub);
        }
        unset($area);

        return $areas;
    }
}
