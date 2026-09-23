<?php

class JabatanController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'lihat');

        $search = trim((string) $this->input('q', ''));
        $status = (string) $this->input('status', '');

        $sql = 'SELECT j.*, r.nama AS nama_role FROM jabatan j LEFT JOIN roles r ON r.id = j.role_id WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND j.nama LIKE :q';
            $params['q'] = '%' . $search . '%';
        }

        if ($status === 'aktif') {
            $sql .= ' AND j.is_active = 1';
        } elseif ($status === 'nonaktif') {
            $sql .= ' AND j.is_active = 0';
        }

        $sql .= ' ORDER BY j.nama ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $deleteError = $_SESSION['jabatan_delete_error'] ?? null;
        unset($_SESSION['jabatan_delete_error']);

        $this->view('admin.jabatan.index', [
            'deleteError' => $deleteError,
            'pageTitle' => 'Jabatan',
            'breadcrumb' => breadcrumb('Karyawan', 'Jabatan'),
            'activeNavItem' => 'jabatan',
            'jabatanList' => $stmt->fetchAll(),
            'roleOptions' => (new Role())->all('id ASC'),
            'canEdit' => (new RoleMiddleware())->check('Human Capital', 'Jabatan', 'edit'),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'tambah');

        $nama = trim((string) $this->input('nama', ''));
        $roleId = (int) $this->input('role_id', 0);

        if (in_array($nama, Jabatan::NAMES, true) && (new Role())->find($roleId) && (new Jabatan())->nameAvailable($nama)) {
            (new Jabatan())->create(['nama' => $nama, 'role_id' => $roleId, 'is_active' => 1]);
        } else {
            $_SESSION['jabatan_delete_error'] = 'Pilih salah satu dari empat jabatan yang diizinkan, pastikan belum terdaftar, dan pilih role sistem.';
        }

        $this->redirect('/jabatan');
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'edit');

        $nama = trim((string) $this->input('nama', ''));
        $roleId = (int) $this->input('role_id', 0);

        if (in_array($nama, Jabatan::NAMES, true) && (new Role())->find($roleId) && (new Jabatan())->nameAvailable($nama, (int) $id)) {
            $db = Database::getInstance();
            $db->beginTransaction();
            try {
                (new Jabatan())->update((int) $id, ['nama' => $nama, 'role_id' => $roleId]);
                $db->prepare('UPDATE users SET role_id=?, remember_token=NULL WHERE karyawan_id IN (SELECT id FROM karyawan WHERE jabatan_id=?)')->execute([$roleId, (int) $id]);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            $_SESSION['jabatan_delete_error'] = 'Pilih salah satu dari empat jabatan yang diizinkan, pastikan belum terdaftar, dan pilih role sistem.';
        }

        $this->redirect('/jabatan');
    }

    public function destroy(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'hapus');

        unset($_SESSION['jabatan_delete_error']);
        if ((new Jabatan())->hasEmployees((int) $id)) {
            $_SESSION['jabatan_delete_error'] = 'Jabatan masih digunakan oleh karyawan dan tidak dapat dihapus. Pindahkan jabatan karyawan terkait terlebih dahulu.';
            $this->redirect('/jabatan');
            return;
        }
        try {
            (new Jabatan())->delete((int) $id);
        } catch (PDOException $e) {
            // MySQL rejects deletion while employees still reference this position.
            if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
                throw $e;
            }
            $_SESSION['jabatan_delete_error'] = 'Jabatan masih digunakan oleh karyawan dan tidak dapat dihapus. Ubah jabatan karyawan terkait terlebih dahulu, atau gunakan Nonaktifkan Jabatan.';
        }

        $this->redirect('/jabatan');
    }

    public function deactivate(string $id): void
    {
        $this->setActive($id, false);
    }

    public function activate(string $id): void
    {
        $this->setActive($id, true);
    }

    private function setActive(string $id, bool $active): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Jabatan', 'status');
        (new Jabatan())->update((int) $id, ['is_active' => $active ? 1 : 0]);
        $this->redirect('/jabatan');
    }
}
