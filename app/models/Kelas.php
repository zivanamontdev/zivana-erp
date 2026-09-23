<?php

class Kelas extends Model
{
    public const LEVELS = ['Akar', 'Batang', 'Ranting', 'Daun'];
    protected string $table = 'kelas';

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
