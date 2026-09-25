<?php

/** Atomic draft save/submission, with ownership and template-bound validation. */
class ReportEntry
{
    public static function save(int $reportId, int $teacherId, mixed $values, mixed $notes, bool $submit = false, bool $archive = false): int
    {
        if (!is_array($values) || !is_array($notes)) throw new DomainException('Format nilai atau catatan tidak valid.');
        if ($submit && $archive) throw new DomainException('Rapor tidak dapat diarsipkan dan dikirim sekaligus.');
        $db=Database::getInstance();
        $db->beginTransaction();
        try {
            $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $query=$db->prepare("SELECT r.*,p.semester FROM rapor r JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id
                JOIN periode_penilaian p ON p.id=s.periode_id WHERE r.id=? AND r.guru_id=?
                AND EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id AND kg.guru_id=r.guru_id)" . $lock);
            $query->execute([$reportId,$teacherId]); $report=$query->fetch();
            if (!$report || $report['status']!=='belum_diisi') throw new DomainException('Rapor tidak tersedia untuk diubah atau sudah dikirim.');
            $semester=$report['semester'];
            if (!isset(ReportWorkflow::SEMESTERS[$semester ?? ''])) throw new DomainException('Semester periode belum ditentukan. Hubungi admin.');
            $query=$db->prepare('SELECT i.id,i.skala_nilai_id FROM template_rapor_item i JOIN template_rapor_subkategori s ON s.id=i.subkategori_id JOIN template_rapor_area a ON a.id=s.area_id WHERE a.template_id=?');
            $query->execute([$report['template_id']]); $items=array_column($query->fetchAll(),'skala_nilai_id','id');
            $query=$db->prepare('SELECT id FROM template_rapor_area WHERE template_id=?');
            $query->execute([$report['template_id']]); $areas=$query->fetchAll(PDO::FETCH_COLUMN);
            $option=$db->prepare('SELECT id FROM skala_nilai_opsi WHERE id=? AND skala_id=?');
            foreach($values as $item=>$value) {
                if (!ctype_digit((string)$item) || !isset($items[$item]) || !is_scalar($value)) throw new DomainException('Item penilaian tidak sesuai template rapor.');
                if ($value==='') continue;
                if (!ctype_digit((string)$value)) throw new DomainException('Pilih nilai yang tersedia.');
                $option->execute([(int)$value,$items[$item]]);
                if (!$option->fetchColumn()) throw new DomainException('Nilai tidak sesuai skala penilaian item.');
            }
            foreach($notes as $area=>$note) {
                if (!ctype_digit((string)$area) || !in_array((int)$area,array_map('intval',$areas),true) || !is_string($note)) throw new DomainException('Catatan tidak sesuai area rapor.');
            }
            foreach($values as $item=>$value) {
                $delete=$db->prepare('DELETE FROM rapor_nilai WHERE rapor_id=? AND item_id=? AND semester=?');
                if ($value==='') { $delete->execute([$reportId,$item,$semester]); continue; }
                $exists=$db->prepare('SELECT id FROM rapor_nilai WHERE rapor_id=? AND item_id=? AND semester=?');
                $exists->execute([$reportId,$item,$semester]); $id=$exists->fetchColumn();
                if ($id) $db->prepare('UPDATE rapor_nilai SET skala_nilai_opsi_id=? WHERE id=?')->execute([$value,$id]);
                else $db->prepare('INSERT INTO rapor_nilai(rapor_id,item_id,semester,skala_nilai_opsi_id) VALUES(?,?,?,?)')->execute([$reportId,$item,$semester,$value]);
            }
            foreach($notes as $area=>$note) {
                $exists=$db->prepare('SELECT id FROM rapor_catatan_guru WHERE rapor_id=? AND area_id=?');
                $exists->execute([$reportId,$area]); $id=$exists->fetchColumn();
                if ($id) $db->prepare('UPDATE rapor_catatan_guru SET catatan=? WHERE id=?')->execute([trim($note),$id]);
                else $db->prepare('INSERT INTO rapor_catatan_guru(rapor_id,area_id,catatan) VALUES(?,?,?)')->execute([$reportId,$area,trim($note)]);
            }
            if ($submit) {
                $query=$db->prepare('SELECT COUNT(DISTINCT n.item_id) FROM rapor_nilai n
                    JOIN template_rapor_item i ON i.id=n.item_id
                    JOIN template_rapor_subkategori s ON s.id=i.subkategori_id
                    JOIN template_rapor_area a ON a.id=s.area_id
                    JOIN skala_nilai_opsi o ON o.id=n.skala_nilai_opsi_id AND o.skala_id=i.skala_nilai_id
                    WHERE n.rapor_id=? AND n.semester=? AND a.template_id=?');
                $query->execute([$reportId,$semester,$report['template_id']]);
                if (!$items || (int)$query->fetchColumn()!==count($items)) throw new DomainException('Lengkapi semua nilai sebelum mengirim rapor. Jika template kosong, hubungi admin.');
                $db->prepare("UPDATE rapor SET status='menunggu_persetujuan',diarsipkan_at=NULL WHERE id=? AND status='belum_diisi'")->execute([$reportId]);
            } elseif ($archive) {
                $db->prepare("UPDATE rapor SET diarsipkan_at=CURRENT_TIMESTAMP WHERE id=? AND status='belum_diisi'")->execute([$reportId]);
            }
            $sessionId=(int)$report['sesi_pembagian_id'];
            $db->commit();
            return $sessionId;
        } catch(Throwable $error) {
            $db->rollBack(); throw $error;
        }
    }
}
