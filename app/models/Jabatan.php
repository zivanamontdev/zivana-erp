<?php

class Jabatan extends Model
{
    public const NAMES = ['Kepala Sekolah', 'Admin', 'Guru Kelas', 'Guru Shadow'];
    public const TEACHER_NAMES = ['Guru Kelas', 'Guru Shadow'];
    protected string $table = 'jabatan';

    public function hasEmployees(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM karyawan WHERE jabatan_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    public function nameAvailable(string $name, int $exceptId = 0): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM jabatan WHERE nama = ? AND id <> ? LIMIT 1');
        $stmt->execute([$name, $exceptId]);
        return !$stmt->fetchColumn();
    }
}
