<?php

class Karyawan extends Model
{
    protected string $table = 'karyawan';

    /** Daftar karyawan + nama jabatan, untuk halaman daftar. */
    public function allWithJabatan(): array
    {
        $stmt = $this->db->query(
            'SELECT k.*, j.nama AS nama_jabatan
             FROM karyawan k
             JOIN jabatan j ON j.id = k.jabatan_id
             ORDER BY k.nama ASC'
        );

        return $stmt->fetchAll();
    }
}
