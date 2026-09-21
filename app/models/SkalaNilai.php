<?php

class SkalaNilai extends Model
{
    protected string $table = 'skala_nilai';

    public function opsi(int $skalaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM skala_nilai_opsi WHERE skala_id = :id ORDER BY display_order ASC');
        $stmt->execute(['id' => $skalaId]);

        return $stmt->fetchAll();
    }
}
