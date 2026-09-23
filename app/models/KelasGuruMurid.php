<?php

class KelasGuruMurid extends Model
{
    protected string $table = 'kelas_guru_murid';

    /** Kelompokkan guru + murid ampuannya untuk satu kelas (Detail Kelas). */
    public function forKelas(int $kelasId): array
    {
        $sql = "SELECT kgm.guru_id, ka.nama AS nama_guru, j.nama AS nama_jabatan,
                       mu.id AS murid_id, mu.nama_lengkap
                FROM kelas_guru_murid kgm
                JOIN karyawan ka ON ka.id = kgm.guru_id
                JOIN jabatan j ON j.id = ka.jabatan_id
                JOIN murid mu ON mu.id = kgm.murid_id
                WHERE kgm.kelas_id = :kelas_id
                ORDER BY ka.nama ASC, mu.nama_lengkap ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['kelas_id' => $kelasId]);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $guruId = (int) $row['guru_id'];
            if (!isset($grouped[$guruId])) {
                $grouped[$guruId] = [
                    'guru_id' => $guruId,
                    'nama_guru' => $row['nama_guru'],
                    'nama_jabatan' => $row['nama_jabatan'],
                    'murid' => [],
                ];
            }
            $grouped[$guruId]['murid'][] = ['id' => $row['murid_id'], 'nama_lengkap' => $row['nama_lengkap']];
        }

        return array_values($grouped);
    }

    /** Ganti seluruh murid ampuan satu guru di satu kelas (dipakai tambah & ubah). */
    public function replaceForGuruInKelas(int $kelasId, int $guruId, array $muridIds): void
    {
        $this->replaceAssignments($guruId, $muridIds, $kelasId);
    }

    public function removeGuruFromKelas(int $kelasId, int $guruId): void
    {
        $stmt = $this->db->prepare('DELETE FROM kelas_guru_murid WHERE kelas_id = :kelas_id AND guru_id = :guru_id');
        $stmt->execute(['kelas_id' => $kelasId, 'guru_id' => $guruId]);
    }

    /** Semua murid ampuan satu guru, lintas kelas (untuk halaman Manajemen Guru). */
    public function forGuru(int $guruId): array
    {
        $sql = "SELECT mu.id, mu.nama_lengkap, k.level_kelas, k.nama_kelas
                FROM kelas_guru_murid kgm
                JOIN murid mu ON mu.id = kgm.murid_id
                LEFT JOIN kelas k ON k.id = kgm.kelas_id
                WHERE kgm.guru_id = :guru_id
                ORDER BY mu.nama_lengkap ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['guru_id' => $guruId]);

        return $stmt->fetchAll();
    }

    /**
     * Ganti seluruh murid ampuan satu guru LINTAS KELAS (dipakai dari
     * halaman Manajemen Guru, beda dengan replaceForGuruInKelas yang
     * scoped ke 1 kelas dari Detail Kelas). kelas_id per baris diambil
     * otomatis dari murid.kelas_id masing-masing.
     */
    public function replaceForGuru(int $guruId, array $muridIds): void
    {
        $this->replaceAssignments($guruId, $muridIds, null);
    }

    private function replaceAssignments(int $guruId, array $muridIds, ?int $kelasId): void
    {
        $db = $this->db;
        $ids = array_values(array_unique(array_filter(array_map('intval', $muridIds), fn($id) => $id > 0)));
        sort($ids);
        $db->beginTransaction();
        try {
            $teacher = $db->prepare("SELECT ka.id FROM karyawan ka JOIN jabatan j ON j.id = ka.jabatan_id WHERE ka.id = ? AND ka.is_active = 1 AND j.nama IN ('Guru Kelas', 'Guru Shadow')");
            $teacher->execute([$guruId]);
            if (!$teacher->fetch()) throw new DomainException('Guru tidak tersedia atau tidak aktif.');
            $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $students = [];
            foreach ($ids as $id) {
                $stmt = $db->prepare('SELECT id, kelas_id FROM murid WHERE id = ?' . $lock);
                $stmt->execute([$id]);
                $student = $stmt->fetch();
                if (!$student || !$student['kelas_id'] || ($kelasId !== null && (int) $student['kelas_id'] !== $kelasId)) {
                    throw new DomainException('Murid harus memiliki kelas yang sesuai sebelum ditugaskan.');
                }
                $assigned = $db->prepare('SELECT guru_id FROM kelas_guru_murid WHERE murid_id = ?' . $lock);
                $assigned->execute([$id]);
                foreach ($assigned->fetchAll() as $row) {
                    if ((int) $row['guru_id'] !== $guruId) throw new DomainException('Murid sudah memiliki guru. Lepaskan penugasan dari guru sebelumnya terlebih dahulu.');
                }
                $students[$id] = (int) $student['kelas_id'];
            }
            $delete = $db->prepare('DELETE FROM kelas_guru_murid WHERE guru_id = ?' . ($kelasId !== null ? ' AND kelas_id = ?' : ''));
            $delete->execute($kelasId !== null ? [$guruId, $kelasId] : [$guruId]);
            $insert = $db->prepare('INSERT INTO kelas_guru_murid (kelas_id, guru_id, murid_id) VALUES (?, ?, ?)');
            foreach ($students as $id => $classId) $insert->execute([$classId, $guruId, $id]);
            // Draft report ownership follows the current assignment; submitted reports stay historical.
            $refresh = $db->prepare("UPDATE rapor SET guru_id=(SELECT kg.guru_id FROM kelas_guru_murid kg WHERE kg.murid_id=rapor.murid_id LIMIT 1) WHERE status='belum_diisi' AND guru_id=?");
            $refresh->execute([$guruId]);
            foreach ($ids as $id) {
                $refresh = $db->prepare("UPDATE rapor SET guru_id=? WHERE murid_id=? AND status='belum_diisi'");
                $refresh->execute([$guruId, $id]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
