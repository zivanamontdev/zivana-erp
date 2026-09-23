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
            // [FIX] Sebelumnya null — dikonfirmasi dari assets/ss/Human
            // Capital - menu_karyawan - submenu_daftar_karyawan(daftar).svg
            // bahwa breadcrumb "Karyawan > Daftar Karyawan" seharusnya ada.
            'breadcrumb' => breadcrumb('Karyawan', 'Daftar Karyawan'),
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
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'tambah');

        $nama = trim((string) $this->input('nama', ''));
        $jabatanId = (int) $this->input('jabatan_id', 0);
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $passwordConfirmation = (string) $this->input('password_confirmation', '');

        $roleId = $this->roleIdForJabatan($jabatanId);

        if ($nama === '' || $jabatanId === 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !employeePasswordIsValid($password) || $password !== $passwordConfirmation || $roleId === null) {
            $this->redirect('/karyawan');
            return;
        }

        $db = Database::getInstance();
        if (!$this->emailAvailable($email)) {
            $_SESSION['employee_error'] = 'Email sudah digunakan oleh akun lain.';
            $this->redirect('/karyawan');
            return;
        }
        $db->beginTransaction();

        try {
            $karyawanId = (new Karyawan())->create(['jabatan_id' => $jabatanId, 'nama' => $nama, 'is_active' => 1]);

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

        $roleId = $this->roleIdForJabatan($jabatanId);

        if ($nama === '' || $jabatanId === 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $roleId === null) {
            $this->redirect('/karyawan');
            return;
        }

        $db = Database::getInstance();
        if (!$this->emailAvailable($email, (int) $id)) {
            $_SESSION['employee_error'] = 'Email sudah digunakan oleh akun lain.';
            $this->redirect('/karyawan');
            return;
        }
        $db->beginTransaction();
        try {
            (new Karyawan())->update((int) $id, ['jabatan_id' => $jabatanId, 'nama' => $nama]);
            $stmt = $db->prepare('UPDATE users SET email = :email, role_id = :role_id, remember_token = NULL WHERE karyawan_id = :karyawan_id');
            $stmt->execute([
                'email' => $email, 'role_id' => $roleId,
                'karyawan_id' => (int) $id,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->redirect('/karyawan');
    }

    public function updatePassword(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'kata_sandi');

        $password = (string) $this->input('password', '');
        $passwordConfirmation = (string) $this->input('password_confirmation', '');

        if (employeePasswordIsValid($password) && $password === $passwordConfirmation) {
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
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'hapus');

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Delete the linked login first; FK rules preserve report records.
            $stmt = $db->prepare('DELETE FROM users WHERE karyawan_id = :id');
            $stmt->execute(['id' => (int) $id]);
            (new Karyawan())->delete((int) $id);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->redirect('/karyawan');
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
        $this->middleware(RoleMiddleware::class, 'Human Capital', 'Daftar Karyawan', 'status');

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            (new Karyawan())->update((int) $id, ['is_active' => (int) $active]);
            $stmt = $db->prepare('UPDATE users SET is_active = :is_active, remember_token = NULL WHERE karyawan_id = :id');
            $stmt->execute(['id' => (int) $id, 'is_active' => (int) $active]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->redirect('/karyawan');
    }

    /**
     * Role RBAC sekarang jadi properti Jabatan (keputusan bersama user,
     * lihat cookbook/todo.md Fase 4) — setiap karyawan otomatis
     * mewarisi role dari jabatan-nya, bukan dipilih manual per orang.
     * Jabatan tanpa role_id (data lama/belum dikonfigurasi) akan
     * membuat karyawan tanpa akses apapun sampai jabatan-nya diberi role.
     */
    private function roleIdForJabatan(int $jabatanId): ?int
    {
        $jabatan = (new Jabatan())->find($jabatanId);

        return !empty($jabatan['is_active']) && isset($jabatan['role_id']) && (new Role())->find((int) $jabatan['role_id'])
            ? (int) $jabatan['role_id'] : null;
    }

    private function emailAvailable(string $email, int $employeeId = 0): bool
    {
        $stmt = Database::getInstance()->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND (karyawan_id IS NULL OR karyawan_id<>?)');
        $stmt->execute([$email, $employeeId]);
        return !$stmt->fetchColumn();
    }
}
