<?php

class KelasController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'lihat');

        $this->view('admin.kelas.index', [
            'pageTitle' => 'Manajemen Kelas',
            'breadcrumb' => null,
            'activeNavItem' => 'manajemen-kelas',
            'kelasList' => (new Kelas())->withCounts(),
            'canEdit' => (new RoleMiddleware())->check('Murid', 'Manajemen Kelas', 'edit'),
        ]);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'edit');

        $levelKelas = trim((string) $this->input('level_kelas', ''));
        $namaKelas = trim((string) $this->input('nama_kelas', ''));
        $tahunAjaran = (new TahunAjaran())->whereFirst('is_active', 1);

        if ($levelKelas !== '' && $namaKelas !== '' && $tahunAjaran) {
            (new Kelas())->create([
                'tahun_ajaran_id' => $tahunAjaran['id'],
                'level_kelas' => $levelKelas,
                'nama_kelas' => $namaKelas,
            ]);
        }

        $this->redirect('/kelas');
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'edit');

        $levelKelas = trim((string) $this->input('level_kelas', ''));
        $namaKelas = trim((string) $this->input('nama_kelas', ''));

        if ($levelKelas !== '' && $namaKelas !== '') {
            (new Kelas())->update((int) $id, ['level_kelas' => $levelKelas, 'nama_kelas' => $namaKelas]);
        }

        $this->redirect('/kelas');
    }

    public function destroy(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'edit');

        // Hard delete (kelas TIDAK punya is_active) — FK murid.kelas_id
        // ON DELETE SET NULL otomatis mengosongkan relasi murid terkait,
        // FK kelas_guru_murid ON DELETE CASCADE membersihkan assignment guru.
        (new Kelas())->delete((int) $id);

        $this->redirect('/kelas');
    }

    public function show(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'lihat');

        $kelas = (new Kelas())->find((int) $id);

        if (!$kelas) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $canEdit = (new RoleMiddleware())->check('Murid', 'Manajemen Kelas', 'edit');

        // Guru yang bisa ditambahkan: semua karyawan aktif yang belum
        // jadi pengampu di kelas ini (dropdown "Tambah Guru").
        $sudahJadiGuru = array_column((new KelasGuruMurid())->forKelas((int) $id), 'guru_id');
        $guruTersedia = (new Karyawan())->allWithJabatan();
        $guruTersedia = array_filter($guruTersedia, fn($k) => !in_array((int) $k['id'], $sudahJadiGuru, true) && $k['is_active']);

        // Murid yang bisa di-assign: murid yang kelas_id-nya kelas ini.
        $muridDiKelasIni = (new Murid())->where('kelas_id', (int) $id);

        $guruMuridGroups = (new KelasGuruMurid())->forKelas((int) $id);

        // Peta murid_id => guru_id, untuk cegah 1 murid ke-assign ke
        // lebih dari 1 guru sekaligus di kelas yang sama.
        $assignedElsewhere = [];
        foreach ($guruMuridGroups as $group) {
            foreach ($group['murid'] as $m) {
                $assignedElsewhere[$m['id']] = $group['guru_id'];
            }
        }

        $this->view('admin.kelas.show', [
            'pageTitle' => 'Detail Kelas',
            'breadcrumb' => breadcrumb('Manajemen Kelas', 'Detail Kelas'),
            'activeNavItem' => 'manajemen-kelas',
            'kelas' => $kelas,
            'canEdit' => $canEdit,
            'guruMuridGroups' => $guruMuridGroups,
            'guruTersedia' => array_values($guruTersedia),
            'muridDiKelasIni' => $muridDiKelasIni,
            'assignedElsewhere' => $assignedElsewhere,
        ]);
    }

    /**
     * Simpan assignment guru-murid (dipakai untuk "+ Tambah Guru" DAN
     * "Atur Anak Murid" pada guru yang sudah ada — logikanya sama:
     * ganti seluruh murid ampuan guru tsb di kelas ini).
     */
    public function saveGuruMurid(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'edit');

        $guruId = (int) $this->input('guru_id', 0);
        $muridIds = $this->input('murid_ids', []);

        if ($guruId > 0 && is_array($muridIds)) {
            (new KelasGuruMurid())->replaceForGuruInKelas((int) $id, $guruId, $muridIds);
        }

        $this->redirect('/kelas/' . $id);
    }

    public function removeGuru(string $id, string $guruId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Kelas', 'edit');

        (new KelasGuruMurid())->removeGuruFromKelas((int) $id, (int) $guruId);

        $this->redirect('/kelas/' . $id);
    }
}
