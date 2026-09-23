<?php
/** Local UI fixtures. --dry-run rolls back; --apply persists. Never resets existing rows. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');
require CONFIG_PATH.'/config.php';require ROOT_PATH.'/app/core/Database.php';
$apply=in_array('--apply',$argv,true);$dry=in_array('--dry-run',$argv,true);
if (!$apply && !$dry) { echo "Usage: php database/seed-ui-demo.php --dry-run|--apply\n";exit; }
if (!in_array(DB_HOST,['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local database only.');
$db=Database::getInstance();
function demoInsert(string $table,array $data): int {
    global $db;
    $columns=implode(',',array_keys($data));$marks=implode(',',array_fill(0,count($data),'?'));
    $db->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($data));
    return (int)$db->lastInsertId();
}
function demoFind(string $table,string $column,string $value): ?array {
    global $db;$q=$db->prepare("SELECT * FROM $table WHERE $column=?");$q->execute([$value]);return $q->fetch() ?: null;
}
$year=$db->query('SELECT * FROM tahun_ajaran WHERE is_active=1')->fetch();
if (!$year) throw new RuntimeException('An active school year is required.');
$yearId=(int)$year['id'];$yearLabel=$year['tahun_awal'].'/'.$year['tahun_akhir'];
$tables=['roles','permissions','role_permissions','jabatan','users','karyawan','tahun_ajaran','kelas','murid','kelas_guru_murid','template_rapor','template_rapor_area','template_rapor_subkategori','template_rapor_item','skala_nilai','skala_nilai_opsi','periode_penilaian','sesi_pembagian_rapor','rapor','rapor_nilai','rapor_catatan_guru','sekolah','sekolah_media'];
$before=[];
foreach($tables as $table){$max=(int)$db->query("SELECT COALESCE(MAX(id),0) FROM $table")->fetchColumn();$before[$table]=[$max,hash('sha256',json_encode($db->query("SELECT * FROM $table WHERE id<=$max ORDER BY id")->fetchAll()))];}
$summary=['year'=>$yearLabel,'accounts'=>[],'students'=>[],'sessions'=>[]];
$db->beginTransaction();
try {
    // 1. Existing positions/roles first; no new position names or global grants.
    foreach(['admin'=>'Admin','kepala'=>'Kepala Sekolah','guru-a'=>'Guru Kelas','guru-b'=>'Guru Shadow','guru-kosong'=>'Guru Kelas','nonaktif'=>'Guru Kelas'] as $key=>$positionName){
        $position=demoFind('jabatan','nama',$positionName);
        if (!$position || !$position['role_id'] || !$position['is_active']) throw new RuntimeException("Position unavailable: $positionName");
        $email="$key@demo.zivana.test";$user=demoFind('users','email',$email);
        if (!$user){
            $employeeId=demoInsert('karyawan',['jabatan_id'=>$position['id'],'nama'=>'[DEMO] '.ucwords(str_replace('-',' ',$key)),'is_active'=>$key==='nonaktif'?0:1]);
            $userId=demoInsert('users',['karyawan_id'=>$employeeId,'role_id'=>$position['role_id'],'email'=>$email,'password_hash'=>password_hash('DemoZivana123$',PASSWORD_DEFAULT),'is_active'=>$key==='nonaktif'?0:1]);
            $user=['id'=>$userId,'karyawan_id'=>$employeeId];
        } else {
            $employee=$db->query('SELECT nama FROM karyawan WHERE id='.(int)$user['karyawan_id'])->fetchColumn();
            if (!str_starts_with((string)$employee,'[DEMO] ')) throw new RuntimeException('Email collision with non-demo employee');
        }
        $accounts[$key]=$user;$summary['accounts'][$key]=['email'=>$email,'user_id'=>(int)$user['id'],'employee_id'=>(int)$user['karyawan_id']];
    }
    // A standalone Superadmin fixture can exercise RBAC without changing shared Admin grants.
    $superRole=demoFind('roles','nama','Superadmin');
    if (!$superRole) throw new RuntimeException('Superadmin role required for RBAC fixture.');
    $superEmail='superadmin@demo.zivana.test';$super=demoFind('users','email',$superEmail);
    if (!$super) {
        $superId=demoInsert('users',['role_id'=>$superRole['id'],'email'=>$superEmail,'password_hash'=>password_hash('DemoZivana123$',PASSWORD_DEFAULT),'is_active'=>1]);
    } else {
        if ($super['karyawan_id']!==null || (int)$super['role_id']!==(int)$superRole['id']) throw new RuntimeException('Superadmin fixture email collision');
        $superId=(int)$super['id'];
    }
    $summary['accounts']['superadmin']=['email'=>$superEmail,'user_id'=>$superId];
    // 2. Classes, including an empty class, all in the unchanged active school year.
    foreach(['Akar','Batang','Ranting','Daun'] as $i=>$level){
        $name='[DEMO] '.['Melati','Kenanga','Akasia','Kosong'][$i].' '.$yearLabel;
        $existing=demoFind('kelas','nama_kelas',$name);
        $classes[$i]=$existing['id'] ?? demoInsert('kelas',['tahun_ajaran_id'=>$yearId,'level_kelas'=>$level,'nama_kelas'=>$name]);
    }
    // 3. Pupils, then unique teacher assignments (teachers span multiple classes).
    $names=['Alya Kosong','Bima Draft','Citra Menunggu','Daffa Disetujui','Eira Shadow','Farid Shadow Draft','Gita Shadow Menunggu','Hana Shadow Disetujui','Intan Tanpa Guru','Joko Tanpa Keterangan','Kirana Tamat','Luthfi Berhenti','Maya Tanpa Kelas'];
    foreach($names as $i=>$name){
        $name='[DEMO] '.$name.' '.$yearLabel;$existing=demoFind('murid','nama_lengkap',$name);
        $classId=$i===12?null:$classes[$i%3];
        $status=[9=>'tanpa_keterangan',10=>'tamat',11=>'berhenti'][$i] ?? 'bersekolah';
        $data=['nama_lengkap'=>$name,'nama_panggilan'=>explode(' ',$names[$i])[0],'nisn'=>sprintf('990%07d',$i+1),
            'agama'=>'Islam','nik'=>sprintf('990000000000%04d',$i+1),'no_registrasi_akte'=>'DEMO-'.($i+1),'jenis_kelamin'=>$i%2?'L':'P',
            'tempat_lahir'=>'Makassar','tanggal_lahir'=>'2020-'.sprintf('%02d',($i%12)+1).'-10','alamat'=>'[DEMO] Alamat fiktif untuk uji UI',
            'tanggal_masuk_sekolah'=>((int)$year['tahun_awal']-2).'-07-15','status_kondisi'=>in_array($i,[4,6])?'ABK':'Regular',
            'jenis_kebutuhan'=>in_array($i,[4,6])?'[DEMO] Pendampingan belajar':null,'kelengkapan_berkas'=>'[DEMO] Dokumen contoh',
            'kelas_id'=>$classId,'alamat_domisili'=>'[DEMO] Makassar','anak_ke'=>1,'jumlah_saudara'=>2,
            'nama_ayah'=>'[DEMO] Ayah '.$i,'pendidikan_ayah'=>'S1','pekerjaan_ayah'=>'Swasta','telp_ayah'=>'000000000000',
            'nama_ibu'=>'[DEMO] Ibu '.$i,'pendidikan_ibu'=>'S1','pekerjaan_ibu'=>'Swasta','telp_ibu'=>'000000000000','status'=>$status];
        $studentId=$existing['id'] ?? demoInsert('murid',$data);
        $teacherId=$i<8?(int)$accounts[$i<4?'guru-a':'guru-b']['karyawan_id']:null;
        if (!$existing && $teacherId) demoInsert('kelas_guru_murid',['kelas_id'=>$classId,'guru_id'=>$teacherId,'murid_id'=>$studentId]);
        $students[$i]=['id'=>(int)$studentId,'teacher'=>$teacherId,'status'=>$status];
        $summary['students'][]=['id'=>(int)$studentId,'name'=>$name];
    }
    // 4. Separate demo templates: do not append fake curriculum to official templates.
    $scale=(int)$db->query('SELECT skala_id FROM skala_nilai_opsi GROUP BY skala_id HAVING COUNT(*)>=4 ORDER BY skala_id LIMIT 1')->fetchColumn();
    if (!$scale) throw new RuntimeException('Four-option assessment scale required.');
    $options=$db->query("SELECT id FROM skala_nilai_opsi WHERE skala_id=$scale ORDER BY display_order,id")->fetchAll(PDO::FETCH_COLUMN);
    foreach(['Tengah Semester','Akhir Semester'] as $type){
        $name='[DEMO] Montessori '.$type;$template=demoFind('template_rapor','nama',$name);
        $templateId=$template['id'] ?? demoInsert('template_rapor',['nama'=>$name,'kategori'=>'rapor_murid','tipe'=>'custom','is_active'=>1]);
        if (!$template){
            foreach(['Keterampilan Hidup','Sensorial','Bahasa','Matematika'] as $a=>$areaName){
                $areaId=demoInsert('template_rapor_area',['template_id'=>$templateId,'nama_area'=>'[DEMO] '.$areaName,'display_order'=>$a+1]);
                foreach(['Kegiatan Mandiri','Kegiatan Bersama'] as $s=>$subName){
                    $subId=demoInsert('template_rapor_subkategori',['area_id'=>$areaId,'label'=>chr(97+$s),'nama'=>$subName,'display_order'=>$s+1]);
                    for($n=1;$n<=10;$n++) demoInsert('template_rapor_item',['subkategori_id'=>$subId,'nama_tujuan'=>"[DEMO] Latihan $areaName $n — contoh penilaian, bukan kurikulum resmi",'skala_nilai_id'=>$scale,'display_order'=>$n]);
                }
            }
        }
        $templates[$type]=$templateId;
    }
    // 5. Four valid year slots, then demo-only sessions; never regenerate reports for real pupils.
    $slots=[['ganjil','Tengah Semester',-7,14],['ganjil','Akhir Semester',45,65],['genap','Tengah Semester',120,140],['genap','Akhir Semester',180,200]];
    $periods=[];
    foreach($slots as [$semester,$type,$start,$end]){
        $q=$db->prepare('SELECT * FROM periode_penilaian WHERE tahun_ajaran_id=? AND semester=? AND tipe=?');$q->execute([$yearId,$semester,$type]);$period=$q->fetch();
        $name="[DEMO] $type $semester $yearLabel";
        $from=date('Y-m-d',strtotime("$start days"));$until=date('Y-m-d',strtotime("+$end days"));
        $periodId=$period['id'] ?? demoInsert('periode_penilaian',['tahun_ajaran_id'=>$yearId,'semester'=>$semester,'nama'=>$name,'tipe'=>$type,'kategori'=>'Rapor Murid','awal_periode'=>$from,'akhir_periode'=>$until]);
        $periods[]=['id'=>$periodId,'semester'=>$semester,'type'=>$type,'from'=>$period['awal_periode']??$from,'until'=>$period['akhir_periode']??$until,'name'=>$name];
    }
    // Historical fixtures reuse a previous year's period without modifying its dates or existing sessions.
    $past=$db->query("SELECT p.* FROM periode_penilaian p WHERE p.tahun_ajaran_id<>$yearId AND p.akhir_periode<CURRENT_DATE ORDER BY p.akhir_periode DESC LIMIT 1")->fetch();
    if ($past) $periods[]=['id'=>$past['id'],'semester'=>$past['semester'],'type'=>$past['tipe'],'from'=>$past['awal_periode'],'until'=>$past['akhir_periode'],'name'=>'[DEMO] Riwayat '.$past['nama']];
    foreach($periods as $pIndex=>$period){
        $session=demoFind('sesi_pembagian_rapor','nama',$period['name']);
        if ($session) continue; // A repeat run never resets the user's UI test progress.
        $templateId=$templates[$period['type']];
        $sessionId=demoInsert('sesi_pembagian_rapor',['periode_id'=>$period['id'],'template_id'=>$templateId,'nama'=>$period['name'],'tanggal_mulai'=>$period['from'],'tanggal_selesai'=>$period['until']]);
        $items=$db->query("SELECT i.id,a.id area_id FROM template_rapor_item i JOIN template_rapor_subkategori s ON s.id=i.subkategori_id JOIN template_rapor_area a ON a.id=s.area_id WHERE a.template_id=$templateId ORDER BY a.display_order,s.display_order,i.display_order")->fetchAll();
        foreach($students as $i=>$student){
            if($i>8) continue;
            $state=$pIndex===0?($i%4):($pIndex===4?3:0);
            if (!$student['teacher']) $state=0;
            $reportId=demoInsert('rapor',['murid_id'=>$student['id'],'sesi_pembagian_id'=>$sessionId,'template_id'=>$templateId,'guru_id'=>$student['teacher'],'status'=>'belum_diisi']);
            $count=$state===1?20:($state>=2?count($items):0);
            foreach(array_slice($items,0,$count) as $n=>$item) demoInsert('rapor_nilai',['rapor_id'=>$reportId,'item_id'=>$item['id'],'semester'=>$period['semester'],'skala_nilai_opsi_id'=>$options[$n%count($options)]]);
            if($count) foreach(array_unique(array_column($items,'area_id')) as $areaId) demoInsert('rapor_catatan_guru',['rapor_id'=>$reportId,'area_id'=>$areaId,'catatan'=>'[DEMO] Catatan perkembangan fiktif untuk pengujian.']);
            if($state>=2){
                if ($count!==count($items)) throw new RuntimeException('Incomplete submitted demo report');
                $db->prepare('UPDATE rapor SET status=?,disetujui_oleh=?,disetujui_at=? WHERE id=?')->execute([$state===2?'menunggu_persetujuan':'disetujui',$state===3?$accounts['kepala']['id']:null,$state===3?min(date('Y-m-d'),$period['until']).' 10:00:00':null,$reportId]);
            }
        }
        $summary['sessions'][]=['id'=>$sessionId,'name'=>$period['name']];
    }
    // Verify every pre-existing row is byte-for-byte unchanged before allowing commit.
    foreach($before as $table=>[$max,$hash]) if(hash('sha256',json_encode($db->query("SELECT * FROM $table WHERE id<=$max ORDER BY id")->fetchAll()))!==$hash) throw new RuntimeException("Existing data changed: $table");
    foreach($tables as $table) $summary['inserted'][$table]=(int)$db->query("SELECT COUNT(*) FROM $table WHERE id>".$before[$table][0])->fetchColumn();
    if($apply) $db->commit();else $db->rollBack();
    echo ($apply?'APPLIED':'DRY RUN: rolled back').PHP_EOL.json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
