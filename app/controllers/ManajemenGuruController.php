<?php

class ManajemenGuruController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Manajemen Guru', 'lihat');

        $search = trim((string) $this->input('q', ''));
        $jabatanId = (string) $this->input('jabatan_id', '');

        // Teacher membership follows the employee's position, not the RBAC role.
        $sql = "SELECT k.id, k.nama, j.nama AS nama_jabatan
                FROM karyawan k
                JOIN jabatan j ON j.id = k.jabatan_id
                WHERE k.is_active = 1 AND j.nama IN ('Guru Kelas', 'Guru Shadow')";
        $params = [];

        if ($search !== '') {
            $sql .= ' AND k.nama LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        if ($jabatanId !== '') {
            $sql .= ' AND k.jabatan_id = :jabatan_id';
            $params['jabatan_id'] = $jabatanId;
        }

        $sql .= ' ORDER BY k.nama ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        $guruList = $stmt->fetchAll();

        $kgmModel = new KelasGuruMurid();
        foreach ($guruList as &$guru) {
            $guru['murid'] = $kgmModel->forGuru((int) $guru['id']);
        }
        unset($guru);

        $this->view('admin.manajemen-guru.index', [
            'pageTitle' => 'Manajemen Guru',
            'breadcrumb' => null,
            'activeNavItem' => 'manajemen-guru',
            'guruList' => $guruList,
            'jabatanOptions' => array_values(array_filter((new Jabatan())->all('nama ASC'), static fn($j) => in_array($j['nama'], Jabatan::TEACHER_NAMES, true))),
            // Hanya murid yang sudah punya kelas — kelas_id dibutuhkan
            // untuk mengisi kelas_guru_murid.kelas_id (lihat
            // KelasGuruMurid::replaceForGuru).
            'muridOptions' => Database::getInstance()->query(
                "SELECT mu.id, mu.nama_lengkap, k.level_kelas, k.nama_kelas,
                 (SELECT kgm.guru_id FROM kelas_guru_murid kgm WHERE kgm.murid_id = mu.id LIMIT 1) AS assigned_guru_id
                 FROM murid mu JOIN kelas k ON k.id = mu.kelas_id
                 ORDER BY mu.nama_lengkap ASC"
            )->fetchAll(),
            'canEdit' => (new RoleMiddleware())->check('Human Capital', 'Manajemen Guru', 'edit'),
            'search' => $search,
            'jabatanId' => $jabatanId,
        ]);
    }

    public function saveMurid(string $guruId): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Manajemen Guru', 'edit');

        $muridIds = $this->input('murid_ids', []);

        if (is_array($muridIds)) {
            try {
                (new KelasGuruMurid())->replaceForGuru((int) $guruId, $muridIds);
            } catch (DomainException $e) {
                $_SESSION['assignment_error'] = $e->getMessage();
            }
        }

        $this->redirect('/manajemen-guru');
    }
}
