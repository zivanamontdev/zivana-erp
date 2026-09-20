<?php

class Murid extends Model
{
    protected string $table = 'murid';

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
