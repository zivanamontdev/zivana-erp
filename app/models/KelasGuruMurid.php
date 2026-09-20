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
        $db = $this->db;
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('DELETE FROM kelas_guru_murid WHERE kelas_id = :kelas_id AND guru_id = :guru_id');
            $stmt->execute(['kelas_id' => $kelasId, 'guru_id' => $guruId]);

            $insertStmt = $db->prepare(
                'INSERT INTO kelas_guru_murid (kelas_id, guru_id, murid_id) VALUES (:kelas_id, :guru_id, :murid_id)'
            );

            foreach (array_unique(array_map('intval', $muridIds)) as $muridId) {
                if ($muridId > 0) {
                    $insertStmt->execute(['kelas_id' => $kelasId, 'guru_id' => $guruId, 'murid_id' => $muridId]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function removeGuruFromKelas(int $kelasId, int $guruId): void
    {
        $stmt = $this->db->prepare('DELETE FROM kelas_guru_murid WHERE kelas_id = :kelas_id AND guru_id = :guru_id');
        $stmt->execute(['kelas_id' => $kelasId, 'guru_id' => $guruId]);
    }
}
