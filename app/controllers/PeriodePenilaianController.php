<?php

class PeriodePenilaianController extends Controller
{
    // [ASUMSI] Nilai tipe/kategori tidak eksplisit terkonfirmasi dari
    // crawling (hanya nama filter "Semua Tipe"/"Semua Kategori" yang
    // terlihat). Diturunkan dari pola nama periode yang ada di
    // screenshot ("Penilaian Rapor Tengah Semester 25/26"). Perlu
    // dikonfirmasi ke user, lihat cookbook/schema.md poin asumsi #9.
    private const TIPE_OPTIONS = ['Tengah Semester' => 'Tengah Semester', 'Akhir Semester' => 'Akhir Semester'];
    private const KATEGORI_OPTIONS = ['Rapor Murid' => 'Rapor Murid', 'Rapor Sekolah' => 'Rapor Sekolah'];

    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Periode Penilaian', 'lihat');

        $tipe = (string) $this->input('tipe', '');
        $kategori = (string) $this->input('kategori', '');

        $sql = 'SELECT * FROM periode_penilaian WHERE 1=1';
        $params = [];

        if ($tipe !== '') {
            $sql .= ' AND tipe = :tipe';
            $params['tipe'] = $tipe;
        }
        if ($kategori !== '') {
            $sql .= ' AND kategori = :kategori';
            $params['kategori'] = $kategori;
        }

        $sql .= ' ORDER BY awal_periode DESC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $this->view('admin.periode-penilaian.index', [
            'pageTitle' => 'Periode Rapor',
            'breadcrumb' => breadcrumb('Kurikulum', 'Periode Rapor'),
            'activeNavItem' => 'periode-penilaian',
            'periodeList' => $stmt->fetchAll(),
            'tipeOptions' => self::TIPE_OPTIONS,
            'kategoriOptions' => self::KATEGORI_OPTIONS,
            'canEdit' => (new RoleMiddleware())->check('Sekolah', 'Periode Penilaian', 'edit'),
            'tipe' => $tipe,
            'kategori' => $kategori,
        ]);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Periode Penilaian', 'edit');

        $data = $this->collectInput();

        if ($data !== null) {
            try { ReportWorkflow::savePeriod($data); }
            catch (DomainException $e) { $_SESSION['period_error'] = $e->getMessage(); }
        }

        $this->redirect('/kurikulum/periode-penilaian');
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Periode Penilaian', 'edit');

        $data = $this->collectInput((int) $id);

        if ($data !== null) {
            try { ReportWorkflow::savePeriod($data, (int) $id); }
            catch (DomainException $e) { $_SESSION['period_error'] = $e->getMessage(); }
        }

        $this->redirect('/kurikulum/periode-penilaian');
    }

    public function destroy(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Periode Penilaian', 'edit');

        (new PeriodePenilaian())->delete((int) $id);

        $this->redirect('/kurikulum/periode-penilaian');
    }

    private function collectInput(?int $id = null): ?array
    {
        $tahunAjaran = (new TahunAjaran())->whereFirst('is_active', 1);
        $nama = trim((string) $this->input('nama', ''));
        $tipe = trim((string) $this->input('tipe', ''));
        $semester = trim((string) $this->input('semester', ''));
        $kategori = trim((string) $this->input('kategori', ''));
        $awal = trim((string) $this->input('awal_periode', ''));
        $akhir = trim((string) $this->input('akhir_periode', ''));

        if ($nama === '' || $tipe === '' || $kategori === '' || $awal === '' || $akhir === '' || !$tahunAjaran) {
            $_SESSION['period_error'] = 'Lengkapi semua field periode dan pastikan tahun ajaran aktif tersedia.';
            return null;
        }

        return [
            'tahun_ajaran_id' => $id ? ((new PeriodePenilaian())->find($id)['tahun_ajaran_id'] ?? $tahunAjaran['id']) : $tahunAjaran['id'],
            'semester' => $semester,
            'nama' => $nama,
            'tipe' => $tipe,
            'kategori' => $kategori,
            'awal_periode' => $awal,
            'akhir_periode' => $akhir,
        ];
    }
}
