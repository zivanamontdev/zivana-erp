<?php

class Murid extends Model
{
    protected string $table = 'murid';

    public function create(array $data)
    {
        $this->db->beginTransaction();
        try {
            $id = parent::create($data);
            StudentReportSync::sync((int)$id);
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Keep a pupil's existing teacher when moving class; clear assignment if unclassified. */
    public function update($id, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare('SELECT id FROM murid WHERE id = ?' . $lock);
            $stmt->execute([$id]);
            $result = parent::update($id, $data);
            if (array_key_exists('kelas_id', $data)) {
                if ($data['kelas_id']) {
                    $stmt = $this->db->prepare('UPDATE kelas_guru_murid SET kelas_id = ? WHERE murid_id = ?');
                    $stmt->execute([$data['kelas_id'], $id]);
                } else {
                    $stmt = $this->db->prepare('DELETE FROM kelas_guru_murid WHERE murid_id = ?');
                    $stmt->execute([$id]);
                }
            }
            StudentReportSync::sync((int)$id);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Daftar murid + info kelas & guru kelas (untuk halaman daftar). */
    public function allWithRelasi(): array
    {
        $sql = "SELECT mu.*, k.level_kelas, k.nama_kelas,
                (SELECT ka.nama FROM kelas_guru_murid kgm
                 JOIN karyawan ka ON ka.id = kgm.guru_id
                 WHERE kgm.murid_id = mu.id LIMIT 1) AS nama_guru_kelas
                FROM murid mu
                LEFT JOIN kelas k ON k.id = mu.kelas_id
                ORDER BY mu.nama_lengkap ASC";

        return $this->db->query($sql)->fetchAll();
    }
}
