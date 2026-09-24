<?php

/** Read-only preflight. Counts only: never expose pupil data or password hashes. */
final class EraporLegacyAudit
{
    public static function inspect(PDO $db): array
    {
        $queries = [
            'reports' => 'SELECT COUNT(*) FROM rapor',
            'grades' => 'SELECT COUNT(*) FROM rapor_nilai',
            'notes' => 'SELECT COUNT(*) FROM rapor_catatan_guru',
            'periods_missing_identity' => "SELECT COUNT(*) FROM periode_penilaian WHERE semester IS NULL OR semester NOT IN ('ganjil','genap') OR tipe NOT IN ('Tengah Semester','Akhir Semester') OR tipe IS NULL",
            'duplicate_period_slots' => 'SELECT COUNT(*) FROM (SELECT tahun_ajaran_id,semester,tipe FROM periode_penilaian GROUP BY tahun_ajaran_id,semester,tipe HAVING COUNT(*)>1) duplicate_slots',
            'invalid_period_dates' => 'SELECT COUNT(*) FROM periode_penilaian WHERE awal_periode IS NULL OR akhir_periode IS NULL OR awal_periode>akhir_periode',
            'reports_missing_period' => 'SELECT COUNT(*) FROM rapor r LEFT JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id LEFT JOIN periode_penilaian p ON p.id=s.periode_id WHERE p.id IS NULL',
            'duplicate_pupil_periods' => 'SELECT COUNT(*) FROM (SELECT r.murid_id,s.periode_id FROM rapor r JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id GROUP BY r.murid_id,s.periode_id HAVING COUNT(*)>1) duplicate_reports',
            'reports_without_teacher' => 'SELECT COUNT(*) FROM rapor WHERE guru_id IS NULL',
            'ambiguous_teacher_accounts' => 'SELECT COUNT(*) FROM rapor r WHERE r.guru_id IS NOT NULL AND (SELECT COUNT(*) FROM users u WHERE u.karyawan_id=r.guru_id)<>1',
            'grades_wrong_semester' => 'SELECT COUNT(*) FROM rapor_nilai n JOIN rapor r ON r.id=n.rapor_id JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id JOIN periode_penilaian p ON p.id=s.periode_id WHERE p.semester IS NULL OR n.semester<>p.semester OR n.semester IS NULL',
            'grades_wrong_template' => 'SELECT COUNT(*) FROM rapor_nilai n JOIN rapor r ON r.id=n.rapor_id LEFT JOIN template_rapor_item i ON i.id=n.item_id LEFT JOIN template_rapor_subkategori sc ON sc.id=i.subkategori_id LEFT JOIN template_rapor_area a ON a.id=sc.area_id WHERE a.template_id IS NULL OR a.template_id<>r.template_id',
            'grades_wrong_scale' => 'SELECT COUNT(*) FROM rapor_nilai n LEFT JOIN template_rapor_item i ON i.id=n.item_id LEFT JOIN skala_nilai_opsi o ON o.id=n.skala_nilai_opsi_id WHERE n.skala_nilai_opsi_id IS NOT NULL AND (o.id IS NULL OR i.id IS NULL OR o.skala_id<>i.skala_nilai_id)',
            'empty_templates' => 'SELECT COUNT(*) FROM template_rapor t WHERE NOT EXISTS (SELECT 1 FROM template_rapor_area a JOIN template_rapor_subkategori s ON s.area_id=a.id JOIN template_rapor_item i ON i.subkategori_id=s.id WHERE a.template_id=t.id)',
        ];
        $counts = [];
        foreach ($queries as $name => $sql) {
            $counts[$name] = (int) $db->query($sql)->fetchColumn();
        }
        $statuses = $db->query('SELECT status, COUNT(*) AS total FROM rapor GROUP BY status ORDER BY status')->fetchAll(PDO::FETCH_ASSOC);
        $unassigned = $db->query("SELECT r.status, COUNT(*) AS reports,
            SUM(CASE WHEN EXISTS (SELECT 1 FROM rapor_nilai n WHERE n.rapor_id=r.id) THEN 1 ELSE 0 END) AS with_grades,
            SUM(CASE WHEN EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id) THEN 1 ELSE 0 END) AS with_current_assignment
            FROM rapor r WHERE r.guru_id IS NULL GROUP BY r.status ORDER BY r.status")->fetchAll(PDO::FETCH_ASSOC);
        $empty = $db->query('SELECT t.id, t.tipe,
            (SELECT COUNT(*) FROM rapor r WHERE r.template_id=t.id) AS reports
            FROM template_rapor t WHERE NOT EXISTS (SELECT 1 FROM template_rapor_area a
            JOIN template_rapor_subkategori s ON s.area_id=a.id JOIN template_rapor_item i ON i.subkategori_id=s.id
            WHERE a.template_id=t.id) ORDER BY t.id')->fetchAll(PDO::FETCH_ASSOC);
        return ['counts' => $counts, 'legacy_statuses' => $statuses,
            'unassigned_breakdown' => $unassigned, 'empty_template_usage' => $empty,
            'automatic_conversion_allowed' => false,
            'notice' => 'Audit only. Even zero anomalies does not authorize mapping legacy grades or approvals to official rubrics.'];
    }
}
