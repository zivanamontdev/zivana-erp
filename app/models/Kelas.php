<?php

class Kelas extends Model
{
    public const LEVELS = ['Akar', 'Batang', 'Ranting', 'Daun'];
    protected string $table = 'kelas';

    public function delete($id): bool
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $query = $this->db->prepare('SELECT id FROM murid WHERE kelas_id=? ORDER BY id' . $lock);
            $query->execute([$id]);
            $studentIds = $query->fetchAll(PDO::FETCH_COLUMN);
            $result = parent::delete($id);
            foreach ($studentIds as $studentId) StudentReportSync::sync((int)$studentId);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function withCounts(): array
    {
        $sql = "SELECT k.*,
                (SELECT COUNT(*) FROM murid m WHERE m.kelas_id = k.id) AS jumlah_murid,
                (SELECT COUNT(DISTINCT kgm.guru_id) FROM kelas_guru_murid kgm WHERE kgm.kelas_id = k.id) AS jumlah_guru
                FROM kelas k
                ORDER BY k.level_kelas ASC, k.nama_kelas ASC";

        return $this->db->query($sql)->fetchAll();
    }
}
