<?php

/** Explicit approval assignments. No user or position is inferred as an approver. */
final class EraporApprovalAssignment
{
    private const FLOW_RULES = [
        'KOORDINATOR_QURAN' => ['urutan'=>1, 'cakupan'=>'TERBATAS', 'rubric'=>'UMMI'],
        'KOORDINATOR_BING' => ['urutan'=>1, 'cakupan'=>'TERBATAS', 'rubric'=>'BING'],
        'KEPALA_SEKOLAH' => ['urutan'=>2, 'cakupan'=>'SEMUA', 'rubric'=>null],
    ];

    public static function read(PDO $db): array
    {
        self::requireMysql($db);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');
        $db->beginTransaction();
        try {
            $flows=self::flows($db);
            self::assertConfiguration($db,$flows);
            $candidates=self::candidates($db);
            $assignments=self::assignments($db);
            $pending=self::pendingCounts($db);
            $result=[];
            foreach ($flows as $flow) {
                $code=$flow['kode'];
                $flowUsers=[];
                foreach ($candidates as $candidate) {
                    $assigned=!empty($assignments[$code][(int)$candidate['id']]);
                    if (!$candidate['eligible'] && !$assigned) continue;
                    if ($code==='KEPALA_SEKOLAH' && $candidate['jabatan']!=='Kepala Sekolah') $candidate['eligible']=false;
                    $candidate['assigned']=$assigned;
                    $candidate['ineligible_reason']=self::ineligibleReason($candidate,$code);
                    $flowUsers[]=$candidate;
                }
                $result[]=[
                    'id'=>(int)$flow['id'],'code'=>$code,'label'=>$flow['label'],
                    'rank'=>(int)$flow['urutan'],'scope'=>$flow['cakupan'],
                    'pending'=>(int)($pending[(int)$flow['id']] ?? 0),'users'=>$flowUsers,
                ];
            }
            $db->commit();
            return ['ready'=>true,'flows'=>$result];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** @param array<string,list<int|string>> $selectedByFlow */
    public static function save(PDO $db,int $actorId,array $selectedByFlow,string $reason): array
    {
        self::requireMysql($db);
        if ($db->inTransaction() || $actorId<1) throw new DomainException('Konteks penugasan tidak valid.');
        $reason=trim($reason);
        $length=preg_match_all('/./us',$reason,$unused);
        if ($reason==='' || !preg_match('//u',$reason) || $length===false || $length<10 || $length>500) {
            throw new DomainException('Alasan perubahan wajib diisi 10–500 karakter.');
        }
        if (array_diff(array_keys($selectedByFlow),array_keys(self::FLOW_RULES))) {
            throw new DomainException('Tahap persetujuan tidak dikenal.');
        }
        foreach (self::FLOW_RULES as $code=>$_) {
            $raw=$selectedByFlow[$code] ?? [];
            if (!is_array($raw) || !array_is_list($raw)) throw new DomainException('Pilihan akun tidak valid.');
            $ids=[];
            foreach ($raw as $value) {
                if (filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) throw new DomainException('Akun penyetuju tidak valid.');
                $id=(int)$value;
                if (isset($ids[$id])) throw new DomainException('Akun penyetuju terpilih lebih dari sekali.');
                $ids[$id]=true;
            }
            $selectedByFlow[$code]=array_keys($ids);
            if (!$selectedByFlow[$code]) throw new DomainException('Setiap tahap wajib memiliki minimal satu akun penyetuju aktif.');
        }

        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $lockQuery=$db->prepare('SELECT GET_LOCK(?,0)'); $lockQuery->execute([$lock]);
        if ((int)$lockQuery->fetchColumn()!==1) throw new RuntimeException('Perubahan eRapor lain sedang berjalan. Coba lagi.');
        try {
            $db->beginTransaction();
            self::lockEraporRolePermissions($db);
            self::assertActor($db,$actorId);
            $flows=self::flows($db,true);
            self::assertConfiguration($db,$flows,true);
            $flowIds=[]; foreach ($flows as $flow) $flowIds[$flow['kode']]=(int)$flow['id'];
            $candidates=self::candidates($db,true);
            $candidateById=[]; foreach ($candidates as $candidate) $candidateById[(int)$candidate['id']]=$candidate;

            foreach (self::FLOW_RULES as $code=>$_) {
                foreach ($selectedByFlow[$code] as $userId) {
                    $candidate=$candidateById[$userId] ?? null;
                    if (!$candidate || !$candidate['eligible'] || ($code==='KEPALA_SEKOLAH' && $candidate['jabatan']!=='Kepala Sekolah')) {
                        throw new DomainException('Akun terpilih tidak aktif atau tidak memenuhi syarat tahap '.$code.'.');
                    }
                }
            }

            $current=self::assignments($db,true);
            $audit=$db->prepare('INSERT INTO erapor_penugasan_penyetuju_audit
                (penyetuju_id,user_id,actor_id,aktif_sebelum,aktif_sesudah,alasan) VALUES(?,?,?,?,?,?)');
            $insert=$db->prepare('INSERT INTO erapor_penyetuju_user(penyetuju_id,user_id,aktif) VALUES(?,?,1)');
            $activate=$db->prepare('UPDATE erapor_penyetuju_user SET aktif=1 WHERE penyetuju_id=? AND user_id=? AND aktif=0');
            $deactivate=$db->prepare('UPDATE erapor_penyetuju_user SET aktif=0 WHERE penyetuju_id=? AND user_id=? AND aktif=1');
            $changed=0;
            foreach (self::FLOW_RULES as $code=>$_) {
                $flowId=$flowIds[$code];
                $selected=array_fill_keys($selectedByFlow[$code],true);
                foreach (($current[$code] ?? []) as $userId=>$isActive) {
                    if ($isActive && !isset($selected[$userId])) {
                        $deactivate->execute([$flowId,$userId]);
                        $audit->execute([$flowId,$userId,$actorId,1,0,$reason]);
                        $changed++;
                    }
                }
                foreach ($selected as $userId=>$_selected) {
                    $before=!empty($current[$code][$userId]);
                    if ($before) continue;
                    if (array_key_exists($userId,$current[$code] ?? [])) $activate->execute([$flowId,$userId]);
                    else $insert->execute([$flowId,$userId]);
                    $audit->execute([$flowId,$userId,$actorId,0,1,$reason]);
                    $changed++;
                }
            }
            $db->commit();
            return ['changed'=>$changed];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        } finally {
            $release=$db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lock]);
        }
    }

    private static function assertActor(PDO $db,int $actorId): void
    {
        $q=$db->prepare("SELECT 1 FROM users u JOIN roles ro ON ro.id=u.role_id
            LEFT JOIN karyawan k ON k.id=u.karyawan_id LEFT JOIN jabatan j ON j.id=k.jabatan_id
            WHERE u.id=? AND u.is_active=1
            AND ((u.karyawan_id IS NULL AND ro.nama='Superadmin') OR (k.is_active=1 AND j.is_active=1
                AND j.nama NOT IN ('Guru Kelas','Guru Shadow')))
            AND EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
                WHERE rp.role_id=u.role_id AND p.modul='eRapor' AND p.section='Penugasan Penyetuju' AND p.aksi='edit')
            FOR UPDATE");
        $q->execute([$actorId]);
        if (!$q->fetchColumn()) throw new DomainException('Akun tidak berwenang mengubah penugasan penyetuju.');
    }

    private static function lockEraporRolePermissions(PDO $db): void
    {
        $db->query("SELECT rp.id FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id
            WHERE p.modul='eRapor' ORDER BY rp.id FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function flows(PDO $db,bool $lock=false): array
    {
        return $db->query('SELECT id,kode,label,urutan,cakupan,aktif FROM erapor_alur_penyetuju ORDER BY kode'.($lock?' FOR UPDATE':''))->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function assertConfiguration(PDO $db,array $flows,bool $lock=false): void
    {
        if (count($flows)!==count(self::FLOW_RULES)) throw new DomainException('Konfigurasi alur persetujuan belum lengkap.');
        $byCode=[];
        foreach ($flows as $flow) {
            $code=(string)$flow['kode']; $rule=self::FLOW_RULES[$code] ?? null;
            if (!$rule || isset($byCode[$code]) || !(bool)$flow['aktif'] || (int)$flow['urutan']!==$rule['urutan']
                || $flow['cakupan']!==$rule['cakupan'] || trim((string)$flow['label'])==='') {
                throw new DomainException('Konfigurasi alur persetujuan tidak sesuai spesifikasi.');
            }
            $byCode[$code]=$flow;
        }
        $rubrics=$db->query('SELECT id,jenis_dokumen FROM erapor_rubrik WHERE jenis_dokumen IN (\'UMMI\',\'BING\') ORDER BY id'.($lock?' FOR UPDATE':''))->fetchAll(PDO::FETCH_ASSOC);
        $expected=[];
        foreach ($rubrics as $rubric) {
            $code=$rubric['jenis_dokumen']==='UMMI'?'KOORDINATOR_QURAN':'KOORDINATOR_BING';
            $expected[(int)$byCode[$code]['id']][(int)$rubric['id']]=true;
        }
        if (empty($expected[(int)$byCode['KOORDINATOR_QURAN']['id']]) || empty($expected[(int)$byCode['KOORDINATOR_BING']['id']])) {
            throw new DomainException('Katalog dokumen Quran/Bahasa Inggris belum tersedia.');
        }
        $scopeQuery=$db->query('SELECT penyetuju_id,rubrik_id FROM erapor_alur_dokumen ORDER BY penyetuju_id,rubrik_id'.($lock?' FOR UPDATE':''));
        $actual=[];
        foreach ($scopeQuery->fetchAll(PDO::FETCH_ASSOC) as $scope) $actual[(int)$scope['penyetuju_id']][(int)$scope['rubrik_id']]=true;
        foreach ($byCode as $code=>$flow) {
            $id=(int)$flow['id']; $actualRows=$actual[$id] ?? []; $expectedRows=$expected[$id] ?? [];
            ksort($actualRows); ksort($expectedRows);
            if ($actualRows!==$expectedRows) throw new DomainException('Cakupan dokumen penyetuju belum lengkap atau menyimpang.');
        }
        $knownIds=array_fill_keys(array_map(static fn($flow)=>(int)$flow['id'],$flows),true);
        foreach ($actual as $flowId=>$_rows) if (!isset($knownIds[(int)$flowId])) {
            throw new DomainException('Mapping cakupan merujuk alur yang tidak dikenal.');
        }
    }

    private static function candidates(PDO $db,bool $lock=false): array
    {
        $sql="SELECT u.id,u.email,k.nama,j.nama AS jabatan,u.is_active AS user_active,k.is_active AS employee_active,j.is_active AS position_active,
            EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=u.role_id
                AND p.modul='eRapor' AND p.section='Persetujuan' AND p.aksi='lihat') AS approval_view,
            EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=u.role_id
                AND p.modul='eRapor' AND p.section='Persetujuan' AND p.aksi='edit') AS approval_edit
            FROM users u LEFT JOIN karyawan k ON k.id=u.karyawan_id LEFT JOIN jabatan j ON j.id=k.jabatan_id
            WHERE (u.is_active=1 AND k.is_active=1 AND j.is_active=1 AND EXISTS (
                SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=u.role_id
                AND p.modul='eRapor' AND p.section='Persetujuan' AND p.aksi='lihat' AND EXISTS (
                    SELECT 1 FROM role_permissions rpe JOIN permissions pe ON pe.id=rpe.permission_id
                    WHERE rpe.role_id=u.role_id AND pe.modul='eRapor' AND pe.section='Persetujuan' AND pe.aksi='edit')))
                OR EXISTS (SELECT 1 FROM erapor_penyetuju_user au WHERE au.user_id=u.id)
            ORDER BY k.nama,u.email".($lock?' FOR UPDATE':'');
        $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id']=(int)$row['id'];
            $row['eligible']=(bool)$row['user_active'] && (bool)$row['employee_active'] && (bool)$row['position_active']
                && (bool)$row['approval_view'] && (bool)$row['approval_edit'];
        }
        unset($row);
        return $rows;
    }

    private static function ineligibleReason(array $user,string $code): string
    {
        if (!(bool)$user['user_active'] || !(bool)$user['employee_active'] || !(bool)$user['position_active']) return 'akun/pegawai/jabatan nonaktif';
        if (!(bool)$user['approval_view'] || !(bool)$user['approval_edit']) return 'izin role Persetujuan belum lengkap';
        if ($code==='KEPALA_SEKOLAH' && $user['jabatan']!=='Kepala Sekolah') return 'tahap ini khusus jabatan Kepala Sekolah';
        return '';
    }

    private static function assignments(PDO $db,bool $lock=false): array
    {
        $rows=$db->query('SELECT f.kode,a.user_id,a.aktif FROM erapor_penyetuju_user a
            JOIN erapor_alur_penyetuju f ON f.id=a.penyetuju_id ORDER BY f.kode,a.user_id'.($lock?' FOR UPDATE':''))->fetchAll(PDO::FETCH_ASSOC);
        $result=[]; foreach ($rows as $row) $result[$row['kode']][(int)$row['user_id']]=(bool)$row['aktif'];
        return $result;
    }

    private static function pendingCounts(PDO $db): array
    {
        $rows=$db->query("SELECT a.penyetuju_id,COUNT(*) AS jumlah FROM erapor_sesi_penyetuju a
            JOIN erapor_sesi s ON s.id=a.sesi_id WHERE a.status='MENUNGGU' AND s.status='MENUNGGU_TTD'
            GROUP BY a.penyetuju_id")->fetchAll(PDO::FETCH_ASSOC);
        $counts=[]; foreach ($rows as $row) $counts[(int)$row['penyetuju_id']]=(int)$row['jumlah'];
        return $counts;
    }

    private static function requireMysql(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
    }
}
