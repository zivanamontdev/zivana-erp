<?php

class KaryawanController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'lihat');

        $search = trim((string) $this->input('q', ''));
        $jabatanId = (string) $this->input('jabatan_id', '');
        $status = (string) $this->input('status', '');

        $sql = 'SELECT k.*, j.nama AS nama_jabatan, u.email
                FROM karyawan k
                JOIN jabatan j ON j.id = k.jabatan_id
                LEFT JOIN users u ON u.karyawan_id = k.id
                WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND k.nama LIKE :q';
            $params['q'] = '%' . $search . '%';
        }
        if ($jabatanId !== '') {
            $sql .= ' AND k.jabatan_id = :jabatan_id';
            $params['jabatan_id'] = $jabatanId;
        }
        if ($status === 'aktif') {
            $sql .= ' AND k.is_active = 1';
        } elseif ($status === 'nonaktif') {
            $sql .= ' AND k.is_active = 0';
        }

        $sql .= ' ORDER BY k.nama ASC';

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);

        $this->view('admin.karyawan.index', [
            'pageTitle' => 'Daftar Karyawan',
            'breadcrumb' => null,
            'activeNavItem' => 'daftar-karyawan',
            'karyawanList' => $stmt->fetchAll(),
            'jabatanOptions' => (new Jabatan())->where('is_active', 1),
            'canEdit' => (new RoleMiddleware())->check('Human Capital', 'Daftar Karyawan', 'edit'),
            'search' => $search,
            'jabatanId' => $jabatanId,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'edit');

        $nama = trim((string) $this->input('nama', ''));
        $jabatanId = (int) $this->input('jabatan_id', 0);
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $passwordConfirmation = (string) $this->input('password_confirmation', '');

        if ($nama === '' || $jabatanId === 0 || $email === '' || strlen($password) < 8 || $password !== $passwordConfirmation) {
            $this->redirect('/karyawan');
            return;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $karyawanId = (new Karyawan())->create(['jabatan_id' => $jabatanId, 'nama' => $nama, 'is_active' => 1]);

            $roleId = $this->roleIdForJabatan($jabatanId);

            (new User())->create([
                'karyawan_id' => $karyawanId,
                'role_id' => $roleId,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_active' => 1,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->redirect('/karyawan');
    }

    public function update(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'edit');

        $nama = trim((string) $this->input('nama', ''));
        $jabatanId = (int) $this->input('jabatan_id', 0);
        $email = trim((string) $this->input('email', ''));

        if ($nama === '' || $jabatanId === 0 || $email === '') {
            $this->redirect('/karyawan');
            return;
        }

        (new Karyawan())->update((int) $id, ['jabatan_id' => $jabatanId, 'nama' => $nama]);

        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET email = :email, role_id = :role_id WHERE karyawan_id = :karyawan_id');
        $stmt->execute([
            'email' => $email,
            'role_id' => $this->roleIdForJabatan($jabatanId),
            'karyawan_id' => (int) $id,
        ]);

        $this->redirect('/karyawan');
    }

    public function updatePassword(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'edit');

        $password = (string) $this->input('password', '');
        $passwordConfirmation = (string) $this->input('password_confirmation', '');

        if (strlen($password) >= 8 && $password === $passwordConfirmation) {
            $stmt = Database::getInstance()->prepare(
                'UPDATE users SET password_hash = :hash, remember_token = NULL WHERE karyawan_id = :karyawan_id'
            );
            $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'karyawan_id' => (int) $id]);
        }

        $this->redirect('/karyawan');
    }

    public function destroy(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'edit');

        // Soft-delete karyawan DAN nonaktifkan akun login-nya sekaligus,
        // supaya karyawan yang dihapus tidak bisa login lagi.
        (new Karyawan())->update((int) $id, ['is_active' => 0]);

        $stmt = Database::getInstance()->prepare('UPDATE users SET is_active = 0 WHERE karyawan_id = :karyawan_id');
        $stmt->execute(['karyawan_id' => (int) $id]);

        $this->redirect('/karyawan');
    }

    /**
     * [ASUMSI] Pemetaan jabatan -> role RBAC tidak eksplisit
     * didokumentasikan di manapun (RBAC pakai 4 role tetap, jabatan
     * bebas macam-macam seperti "Guru Kelas", "Guru Shadow", "Admin",
     * "Kepala Sekolah"). Aturan sementara: jabatan yang namanya
     * mengandung kata "guru" (case-insensitive) di-assign role Guru,
     * sisanya di-assign role Admin. Superadmin dan Koordinator Guru
     * tidak bisa didapat lewat jalur ini — perlu di-set manual di DB
     * atau lewat mekanisme lain, karena tidak ada UI untuk memilih
     * role secara langsung saat tambah karyawan (sesuai desain Figma
     * yang hanya punya field "Jabatan", bukan field "Role").
     */
    private function roleIdForJabatan(int $jabatanId): int
    {
        $jabatan = (new Jabatan())->find($jabatanId);
        $namaJabatan = $jabatan['nama'] ?? '';

        $roleModel = new Role();
        $roleName = stripos($namaJabatan, 'guru') !== false ? 'Guru' : 'Admin';
        $role = $roleModel->findByName($roleName);

        return $role ? (int) $role['id'] : (int) $roleModel->findByName('Admin')['id'];
    }
}
