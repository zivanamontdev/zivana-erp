<?php

class TemplateRaporController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Manajemen Template', 'lihat');

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
            'pageTitle' => 'Manajemen Template',
            'breadcrumb' => 'Kurikulum &gt; Manajemen Template',
            'activeNavItem' => 'manajemen-template',
            'templateList' => $stmt->fetchAll(),
        ]);
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
            'pageTitle' => 'Pratinjau Template',
            'breadcrumb' => 'Kurikulum &gt; Pratinjau Template',
            'activeNavItem' => 'manajemen-template',
            'template' => $template,
            'areas' => $this->buildStructure((int) $id),
            'legenda' => (new SkalaNilai())->opsi(1), // [ASUMSI] halaman 1 dokumen selalu pakai skala id=1 (Montessori 4 Simbol)
        ]);
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
