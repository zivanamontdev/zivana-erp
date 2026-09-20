<?php

class SekolahController extends Controller
{
    /** Singleton — aplikasi tidak multi-tenant, lihat cookbook/schema.md bagian 2. */
    private const SEKOLAH_ID = 1;

    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Data Sekolah', 'lihat');

        $sekolah = (new Sekolah())->find(self::SEKOLAH_ID);
        $media = $sekolah ? (new SekolahMedia())->where('sekolah_id', $sekolah['id']) : [];
        $tahunAjaranAktif = (new TahunAjaran())->whereFirst('is_active', 1);

        $canEdit = (new RoleMiddleware())->check('Sekolah', 'Data Sekolah', 'edit');
        $requestedMode = (string) $this->input('mode', 'lihat');
        $mode = ($requestedMode === 'ubah' && $canEdit) ? 'ubah' : 'lihat';

        $errors = $_SESSION['sekolah_errors'] ?? [];
        $old = $_SESSION['sekolah_old'] ?? [];
        unset($_SESSION['sekolah_errors'], $_SESSION['sekolah_old']);

        $this->view('admin.sekolah.index', [
            'pageTitle' => 'Data Sekolah',
            'breadcrumb' => null,
            'activeNavItem' => 'data-sekolah',
            'sekolah' => $sekolah,
            'media' => $media,
            'tahunAjaranAktif' => $tahunAjaranAktif,
            'canEdit' => $canEdit,
            'mode' => $mode,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function update(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Data Sekolah', 'edit');

        $data = [
            'nama_legal' => trim((string) $this->input('nama_legal', '')),
            'nama_komersial' => trim((string) $this->input('nama_komersial', '')),
            'bentuk_pendidikan' => trim((string) $this->input('bentuk_pendidikan', '')),
            'npsn' => trim((string) $this->input('npsn', '')),
            'alamat' => trim((string) $this->input('alamat', '')),
            'no_telepon' => trim((string) $this->input('no_telepon', '')),
            'email' => trim((string) $this->input('email', '')),
        ];

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['sekolah_errors'] = $errors;
            $_SESSION['sekolah_old'] = $data;
            $this->redirect('/sekolah?mode=ubah');
            return;
        }

        $sekolahModel = new Sekolah();
        $existing = $sekolahModel->find(self::SEKOLAH_ID);
        $sekolahId = $existing ? self::SEKOLAH_ID : $sekolahModel->create($data);

        if ($existing) {
            $sekolahModel->update(self::SEKOLAH_ID, $data);
        }

        $this->syncMedia($sekolahId);

        $this->redirect('/sekolah');
    }

    public function updateTahunAjaran(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sekolah', 'Data Sekolah', 'edit');

        $tahunAwal = (int) $this->input('tahun_awal');
        $tahunAkhir = (int) $this->input('tahun_akhir');

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $db->exec('UPDATE tahun_ajaran SET is_active = 0');
            (new TahunAjaran())->create([
                'tahun_awal' => $tahunAwal,
                'tahun_akhir' => $tahunAkhir,
                'is_active' => 1,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->redirect('/sekolah');
    }

    /**
     * Sinkronisasi daftar media: hapus semua baris lama lalu insert
     * ulang dari input form. Sederhana dan cukup untuk jumlah baris
     * yang kecil (repeatable list media sosial).
     */
    private function syncMedia(int $sekolahId): void
    {
        $db = Database::getInstance();
        $db->prepare('DELETE FROM sekolah_media WHERE sekolah_id = :id')->execute(['id' => $sekolahId]);

        $jenisList = $this->input('media_jenis', []);
        $namaList = $this->input('media_nama', []);
        $urlList = $this->input('media_url', []);

        if (!is_array($jenisList)) {
            return;
        }

        $mediaModel = new SekolahMedia();

        foreach ($jenisList as $i => $jenis) {
            $jenis = trim((string) $jenis);
            $nama = trim((string) ($namaList[$i] ?? ''));
            $url = trim((string) ($urlList[$i] ?? ''));

            if ($jenis === '' && $nama === '' && $url === '') {
                continue;
            }

            $mediaModel->create([
                'sekolah_id' => $sekolahId,
                'jenis_media' => $jenis,
                'nama_akun' => $nama,
                'url' => $url,
                'display_order' => $i,
            ]);
        }
    }

    private function validate(array $data): array
    {
        $errors = [];
        $required = ['nama_legal', 'nama_komersial', 'bentuk_pendidikan', 'npsn', 'alamat', 'no_telepon', 'email'];

        foreach ($required as $field) {
            if ($data[$field] === '') {
                $errors[$field] = 'Wajib Diisi';
            }
        }

        return $errors;
    }
}
