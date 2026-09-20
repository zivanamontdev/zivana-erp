<?php

class JabatanController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'lihat');

        $search = trim((string) $this->input('q', ''));
        $status = (string) $this->input('status', '');

        $sql = 'SELECT * FROM jabatan WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND nama LIKE :q';
            $params['q'] = '%' . $search . '%';
        }

        if ($status === 'aktif') {
            $sql .= ' AND is_active = 1';
        } elseif ($status === 'nonaktif') {
            $sql .= ' AND is_active = 0';
        }

        $sql .= ' ORDER BY nama ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $this->view('admin.jabatan.index', [
            'pageTitle' => 'Jabatan',
            'breadcrumb' => 'Karyawan &gt; Jabatan',
            'activeNavItem' => 'jabatan',
            'jabatanList' => $stmt->fetchAll(),
            'canEdit' => (new RoleMiddleware())->check('Human Capital', 'Jabatan', 'edit'),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'edit');

        $nama = trim((string) $this->input('nama', ''));

        if ($nama !== '') {
            (new Jabatan())->create(['nama' => $nama, 'is_active' => 1]);
        }

        $this->redirect('/jabatan');
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'edit');

        $nama = trim((string) $this->input('nama', ''));

        if ($nama !== '') {
            (new Jabatan())->update((int) $id, ['nama' => $nama]);
        }

        $this->redirect('/jabatan');
    }

    public function destroy(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'edit');

        // Soft-delete (is_active = 0), bukan DELETE FROM — konsisten
        // dengan pola is_active di cookbook/schema.md, bukan deleted_at.
        (new Jabatan())->update((int) $id, ['is_active' => 0]);

        $this->redirect('/jabatan');
    }
}
