<?php

class MuridController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'lihat');

        $search = trim((string) $this->input('q', ''));
        $kelasId = (string) $this->input('kelas_id', '');
        $guruId = (string) $this->input('guru_id', '');
        $status = (string) $this->input('status', '');

        $sql = "SELECT mu.*, k.level_kelas, k.nama_kelas,
                (SELECT ka.nama FROM kelas_guru_murid kgm
                 JOIN karyawan ka ON ka.id = kgm.guru_id
                 WHERE kgm.murid_id = mu.id LIMIT 1) AS nama_guru_kelas
                FROM murid mu
                LEFT JOIN kelas k ON k.id = mu.kelas_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND mu.nama_lengkap LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        if ($kelasId !== '') {
            $sql .= ' AND mu.kelas_id = :kelas_id';
            $params['kelas_id'] = $kelasId;
        }
        if ($status !== '') {
            $sql .= ' AND mu.status = :status';
            $params['status'] = $status;
        }
        if ($guruId !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM kelas_guru_murid filter_guru WHERE filter_guru.murid_id = mu.id AND filter_guru.guru_id = :guru_id)';
            $params['guru_id'] = $guruId;
        }

        $sql .= ' ORDER BY mu.nama_lengkap ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $this->view('admin.murid.index', [
            'pageTitle' => 'Manajemen Murid',
            'breadcrumb' => null,
            'activeNavItem' => 'manajemen-murid',
            'muridList' => $stmt->fetchAll(),
            'kelasOptions' => (new Kelas())->all('nama_kelas ASC'),
            'guruOptions' => Database::getInstance()->query(
                'SELECT DISTINCT ka.id, ka.nama FROM karyawan ka
                 JOIN kelas_guru_murid kgm ON kgm.guru_id = ka.id ORDER BY ka.nama ASC'
            )->fetchAll(),
            'canEdit' => (new RoleMiddleware())->check('Murid', 'Manajemen Murid', 'edit'),
            'search' => $search,
            'kelasId' => $kelasId,
            'guruId' => $guruId,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'edit');

        $this->renderForm('tambah', [], $_SESSION['murid_old'] ?? [], $_SESSION['murid_errors'] ?? []);
        unset($_SESSION['murid_old'], $_SESSION['murid_errors']);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'edit');

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['murid_old'] = $data;
            $_SESSION['murid_errors'] = $errors;
            $this->redirect('/murid/tambah');
            return;
        }

        $data['status'] = 'bersekolah';
        $muridId = (new Murid())->create($data);

        $this->redirect('/murid/' . $muridId);
    }

    public function edit(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'edit');

        $murid = (new Murid())->find((int) $id);

        if (!$murid) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        $old = $_SESSION['murid_old'] ?? [];
        $errors = $_SESSION['murid_errors'] ?? [];
        unset($_SESSION['murid_old'], $_SESSION['murid_errors']);

        $this->renderForm('ubah', $murid, $old, $errors, (int) $id);
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'edit');

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['murid_old'] = $data;
            $_SESSION['murid_errors'] = $errors;
            $this->redirect('/murid/' . $id . '/ubah');
            return;
        }

        (new Murid())->update((int) $id, $data);

        $this->redirect('/murid/' . $id);
    }

    public function show(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Murid', 'Manajemen Murid', 'lihat');

        $murid = (new Murid())->find((int) $id);

        if (!$murid) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }

        if ($murid['kelas_id']) {
            $kelas = (new Kelas())->find((int) $murid['kelas_id']);
            $murid['level_kelas'] = $kelas['level_kelas'] ?? null;
            $murid['nama_kelas'] = $kelas['nama_kelas'] ?? null;
        }

        $this->renderForm('detail', $murid, [], [], (int) $id);
    }

    private function renderForm(string $mode, array $murid, array $old, array $errors, ?int $id = null): void
    {
        $this->view('admin.murid.form', [
            'pageTitle' => $mode === 'tambah' ? 'Tambah Murid' : ($mode === 'ubah' ? 'Ubah Data Murid' : 'Detail Murid'),
            'breadcrumb' => breadcrumb('Manajemen Murid', $mode === 'tambah' ? 'Tambah Murid' : ($mode === 'ubah' ? 'Ubah Data Murid' : 'Detail Murid')),
            'activeNavItem' => 'manajemen-murid',
            'mode' => $mode,
            'kelasOptions' => (new Kelas())->all('level_kelas ASC, nama_kelas ASC'),
            'muridId' => $id,
            'murid' => $murid,
            'old' => $old,
            'errors' => $errors,
            'canEdit' => (new RoleMiddleware())->check('Murid', 'Manajemen Murid', 'edit'),
        ]);
    }

    private function collectInput(): array
    {
        return [
            'kelas_id' => (int) $this->input('kelas_id', 0) ?: null,
            'nama_lengkap' => trim((string) $this->input('nama_lengkap', '')),
            'nama_panggilan' => trim((string) $this->input('nama_panggilan', '')),
            'nisn' => trim((string) $this->input('nisn', '')) ?: null,
            'agama' => trim((string) $this->input('agama', '')),
            'nik' => trim((string) $this->input('nik', '')),
            'no_registrasi_akte' => trim((string) $this->input('no_registrasi_akte', '')),
            'jenis_kelamin' => trim((string) $this->input('jenis_kelamin', '')),
            'tempat_lahir' => trim((string) $this->input('tempat_lahir', '')),
            'tanggal_lahir' => trim((string) $this->input('tanggal_lahir', '')),
            'alamat' => trim((string) $this->input('alamat', '')),
            'tanggal_masuk_sekolah' => trim((string) $this->input('tanggal_masuk_sekolah', '')),
            'status_kondisi' => trim((string) $this->input('status_kondisi', '')),
            'jenis_kebutuhan' => trim((string) $this->input('jenis_kebutuhan', '')) ?: null,
            'kelengkapan_berkas' => trim((string) $this->input('kelengkapan_berkas', '')) ?: null,
            'alamat_domisili' => trim((string) $this->input('alamat_domisili', '')),
            'anak_ke' => $this->input('anak_ke', '') !== '' ? (int) $this->input('anak_ke') : null,
            'jumlah_saudara' => (int) $this->input('jumlah_saudara', 0),
            'nama_ayah' => trim((string) $this->input('nama_ayah', '')),
            'pendidikan_ayah' => trim((string) $this->input('pendidikan_ayah', '')),
            'pekerjaan_ayah' => trim((string) $this->input('pekerjaan_ayah', '')),
            'telp_ayah' => trim((string) $this->input('telp_ayah', '')),
            'nama_ibu' => trim((string) $this->input('nama_ibu', '')),
            'pendidikan_ibu' => trim((string) $this->input('pendidikan_ibu', '')),
            'pekerjaan_ibu' => trim((string) $this->input('pekerjaan_ibu', '')),
            'telp_ibu' => trim((string) $this->input('telp_ibu', '')),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $kelas = !empty($data['kelas_id']) ? (new Kelas())->find($data['kelas_id']) : null;
        if (!$kelas) {
            $errors['kelas_id'] = 'Pilih kelas yang tersedia.';
        } elseif ($this->input('level_kelas', '') !== $kelas['level_kelas']) {
            $errors['kelas_id'] = 'Kelas tidak sesuai dengan level yang dipilih.';
        }
        $required = [
            'nama_lengkap', 'nama_panggilan', 'agama', 'nik', 'no_registrasi_akte',
            'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'alamat',
            'tanggal_masuk_sekolah', 'status_kondisi',
            'alamat_domisili', 'nama_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'telp_ayah',
            'nama_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'telp_ibu',
        ];

        foreach ($required as $field) {
            if (($data[$field] ?? '') === '') {
                $errors[$field] = 'Wajib Diisi';
            }
        }

        return $errors;
    }
}
