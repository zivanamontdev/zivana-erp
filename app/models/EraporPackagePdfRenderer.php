<?php

/** Deterministic, offline renderer for a frozen eRapor package snapshot. */
final class EraporPackagePdfRenderer
{
    private const VERSION = 'erapor-print-v4';

    public static function render(array $package): string
    {
        return self::renderArtifact($package)['bytes'];
    }

    /** Render once and expose only non-sensitive PDF metadata for the publication manifest. */
    public static function renderArtifact(array $package): array
    {
        if (!class_exists(\Dompdf\Dompdf::class)) throw new RuntimeException('Dompdf belum tersedia.');
        $types=array_column($package['documents'] ?? [],'jenis_dokumen');
        $expected=['RTS','AGAMA','UMMI','BING'];
        if (($package['student']['status_kondisi'] ?? '')==='ABK') $expected[]='PPI';
        // Template Manajemen Template boleh berisi satu rubrik; paket rapor murid wajib lengkap sesuai kondisi.
        $valid=!empty($package['template']) ? ($types!==[] && array_values(array_intersect($expected,$types))===$types) : $types===$expected;
        if (!$valid) throw new DomainException('Susunan paket atau rubrik PDF belum lengkap.');
        if (($package['period']['jenis'] ?? '')!=='TENGAH') throw new DomainException('PDF final menunggu spesifikasi Rapor Akhir Semester.');

        $body='';
        foreach ($package['documents'] as $document) {
            $body.='<section class="report report-'.strtolower(e($document['jenis_dokumen'])).'">';
            $body.=match ($document['jenis_dokumen']) {
                'RTS'=>self::rts($package,$document),
                'AGAMA'=>self::agama($package,$document),
                'UMMI'=>self::ummi($package,$document),
                'BING'=>self::bing($package,$document),
                'PPI'=>self::ppi($package,$document),
                default=>throw new DomainException('Renderer PDF belum mendukung jenis dokumen ini.'),
            };
            $body.='</section>';
        }
        // Pratinjau draf (nilai terkini, belum tentu disetujui) diberi tanda air di setiap halaman.
        if (!empty($package['draft'])) $body='<div class="draft-mark">DRAF · belum disetujui · nilai dapat berubah</div>'.$body;
        $html='<!doctype html><html lang="id"><head><meta charset="UTF-8"><style>'.self::css().'.draft-mark{position:fixed;top:-11mm;left:0;right:0;text-align:right;font-size:8pt;font-weight:bold;color:#c62828;letter-spacing:.5pt}</style></head><body>'.$body.'</body></html>';
        $pdf=new \Dompdf\Dompdf(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>false,'isHtml5ParserEnabled'=>true]);
        $pdf->loadHtml($html,'UTF-8'); $pdf->setPaper('A4','portrait'); $pdf->render();
        $bytes=$pdf->output();
        if (!is_string($bytes) || !str_starts_with($bytes,'%PDF-') || strlen($bytes)>33554432) {
            throw new RuntimeException('PDF paket kosong atau melebihi batas 32 MB.');
        }
        $pages=(int)$pdf->getCanvas()->get_page_count();
        if ($pages<1 || $pages>80) throw new RuntimeException('Jumlah halaman PDF berada di luar batas yang diperiksa.');
        return ['bytes'=>$bytes,'pages'=>$pages];
    }

    public static function version(): string { return self::VERSION; }

    private static function rts(array $p,array $d): string
    {
        $f=$d['form']['definitions']; $vals=$d['period_values'] ?? [];
        $scale=[]; foreach ($f['scale'] ?? [] as $s) $scale[(int)$s['nilai']]=$s;
        $subByArea=[]; foreach ($f['subareas'] ?? [] as $s) $subByArea[(int)$s['area_id']][]=$s;
        $groups=[]; foreach ($f['groups'] ?? [] as $g) $groups[(int)$g['sub_area_id']][]=$g;
        $items=[]; foreach ($f['items'] ?? [] as $i) $items[(int)$i['sub_area_id']][]=$i;
        $html='<table class="report-table"><thead>'.self::tableIdentity($p,$d,4).'<tr><th>Tujuan</th><th>Aparatus/Media Pendukung</th><th>TS Ganjil</th><th>TS Genap</th></tr></thead><tbody>'
            .'<tr class="legend-row"><td colspan="4">'.self::legend($f['scale'] ?? []).'</td></tr>';
        foreach ($f['areas'] ?? [] as $area) {
            $html.='<tr class="section-row"><th colspan="4">'.e($area['nama']).'</th></tr>';
            foreach ($subByArea[(int)$area['id']] ?? [] as $sub) {
                if (!(bool)$sub['implisit']) $html.='<tr class="sub-row"><th colspan="4">'.e(trim(($sub['huruf'] ?? '').'. '.($sub['nama'] ?? ''))).'</th></tr>';
                foreach ($groups[(int)$sub['id']] ?? [] as $group) $html.='<tr class="group-row"><td>'.e($group['nama']).'</td><td></td><td></td><td></td></tr>';
                foreach ($items[(int)$sub['id']] ?? [] as $item) {
                    $g=$vals['TS_GANJIL']['nilai:'.$item['id']] ?? null; $e=$vals['TS_GENAP']['nilai:'.$item['id']] ?? null;
                    $html.='<tr><td>'.e($item['tujuan']).'</td><td>'.self::text($item['aparatus'] ?? '').'</td>';
                    // null = belum diisi (kosong); 0 = Belum Dikenalkan (tanda -), jangan tertukar lewat cast (int)null.
                    $html.='<td class="grade">'.self::symbol($g===null?null:($scale[(int)$g]['simbol'] ?? null)).'</td><td class="grade">'.self::symbol($e===null?null:($scale[(int)$e]['simbol'] ?? null)).'</td></tr>';
                }
            }
        }
        $html.='</tbody></table><h2>Tanda Tangan Periode Tengah Semester</h2>';
        foreach (['GANJIL'=>'Tengah Semester Ganjil','GENAP'=>'Tengah Semester Genap'] as $semester=>$label) {
            $block=$d['signature_periods'][$semester] ?? null;
            $html.=self::signatureBlock($p,$d,$label,$block);
        }
        return $html;
    }

    private static function agama(array $p,array $d): string
    {
        $f=$d['form']['definitions']; $items=$d['all_items'] ?? [];
        $values=$d['period_values'] ?? []; $scopeNames=[]; $subs=[]; $names=$d['item_names'] ?? [];
        foreach ($f['scopes'] ?? [] as $s) $scopeNames[(int)$s['id']]=$s;
        foreach ($f['subscopes'] ?? [] as $s) $subs[(int)$s['id']]=$s;
        $printColumns=['TELADAN','TALQIN','TAHFIZH/D','TAHFIZH/J','TAHFIZH/M','TAFHIM','TADIB'];
        // Dompdf (table-layout:fixed) mengambil lebar kolom dari baris pertama dan mengabaikan colgroup;
        // baris pengukur tak terlihat ini mengunci 30% untuk Ruang Lingkup dan 5% untuk tiap 14 kolom nilai.
        $sizer='<tr class="col-sizer"><th style="width:30%"></th>'.str_repeat('<th style="width:5%"></th>',14).'</tr>';
        $html='<table class="report-table agama-table"><thead>'.$sizer.self::tableIdentity($p,$d,15,4)
            .'<tr><th rowspan="3">Ruang Lingkup / Capaian</th><th colspan="7">TENGAH SEMESTER</th><th colspan="7">AKHIR SEMESTER</th></tr>'
            .'<tr class="agama-stage"><th rowspan="2">TELADAN</th><th rowspan="2">TALQIN</th><th colspan="3">TAHFIZH</th><th rowspan="2">TAFHIM</th><th rowspan="2">TA\'DIB</th>'
            .'<th rowspan="2">TELADAN</th><th rowspan="2">TALQIN</th><th colspan="3">TAHFIZH</th><th rowspan="2">TAFHIM</th><th rowspan="2">TA\'DIB</th></tr>'
            .'<tr><th>D</th><th>J</th><th>M</th><th>D</th><th>J</th><th>M</th></tr></thead><tbody>';
        $openScope=null; $openSub=null; $openSemester=null;
        foreach ($items as $item) {
            $semester=$item['semester'];
            if ($openSemester!==$semester) {
                $html.='<tr class="section-row"><th colspan="15">CAPAIAN SEMESTER '.($semester==='GANJIL'?'GANJIL':'GENAP').'</th></tr>';
                $openSemester=$semester; $openScope=null; $openSub=null;
            }
            $scopeId=(int)$item['lingkup_id']; $subId=(int)$item['sub_id'];
            if ($openScope!==$scopeId) {
                $scope=$scopeNames[$scopeId] ?? null;
                if ($scope) $html.='<tr class="section-row"><th colspan="15">'.e($scope['nomor_romawi'].'. '.$scope['nama']).'</th></tr>';
                $openScope=$scopeId; $openSub=null;
            }
            if ($openSub!==$subId && isset($subs[$subId]) && !(bool)$subs[$subId]['implisit']) {
                $sub=$subs[$subId]; $html.='<tr class="sub-row"><th colspan="15">'.e(trim(($sub['huruf'] ?? '').'. '.($sub['nama'] ?? ''))).'</th></tr>'; $openSub=$subId;
            }
            $label=($item['nomor'] ? $item['nomor'].'. ' : '').$item['teks'];
            // Seed Asmaul Husna menyimpan daftar nama di teks butir sekaligus di erapor_agama_item_nama; jangan dicetak dua kali.
            $joined=implode(', ',$names[(int)$item['id']] ?? []);
            if ($joined!=='' && trim((string)$item['teks'])!==$joined) $label.=' — '.$joined;
            $html.='<tr><td>'.e($label).'</td>';
            foreach (['TENGAH','AKHIR'] as $periodType) {
                $key=$periodType.'_'.$semester; $choice=$values[$key]['nilai:'.$item['id']] ?? null;
                foreach ($printColumns as $column) $html.='<td class="grade agama-grade">'.($choice===$column?'<span class="check">✓</span>':'').'</td>';
            }
            $html.='</tr>';
        }
        $html.='</tbody></table><h2>Pengertian Tahapan</h2><table class="report-table"><tbody>';
        foreach ($f['stages'] ?? [] as $stage) $html.='<tr><th>'.e($stage['label']).'</th><td>'.self::text($stage['definisi']).'</td></tr>';
        $html.='</tbody></table><h2>Bagian VII — Laporan Perkembangan Agama</h2><p class="narrative">'.e($d['narrative'] ?? '').'</p>';
        $html.=self::signatureBlock($p,$d,'Pengesahan Rapor Agama',$d['signature_current'] ?? null);
        return $html;
    }

    private static function ummi(array $p,array $d): string
    {
        $f=$d['form']['definitions']; $items=$d['items'] ?? $f['items'] ?? []; $vals=$d['period_values'] ?? [];
        if (empty($d['show_pra_tk'])) $items=array_values(array_filter($items,static fn($item)=>empty($item['hanya_pra_tk'])));
        $html='<table class="report-table ummi-table"><thead>'.self::tableIdentity($p,$d,4)
            .'<tr><th rowspan="2">Jilid</th><th rowspan="2">Materi</th><th colspan="2">CAPAIAN SEMESTER '.e($p['period']['semester_label']).'</th></tr>'
            .'<tr><th>TENGAH SEMESTER</th><th>AKHIR SEMESTER</th></tr></thead><tbody>';
        $scale=[]; foreach ($f['scale'] ?? [] as $s) $scale[(string)$s['kode']]=$s['label'];
        foreach ($f['volumes'] ?? [] as $volume) {
            $volumeItems=array_values(array_filter($items,fn($i)=>(int)$i['jilid_id']===(int)$volume['id']));
            if (!$volumeItems) continue;
            foreach ($volumeItems as $index=>$item) {
                // Tanpa rowspan: Dompdf memindahkan sel rowspan ke kolom yang salah bila grup terpotong halaman.
                $html.='<tr>'.($index===0?'<th class="jilid-cell">'.e($volume['nama']).'</th>':'<th class="jilid-cell jilid-cont"></th>').'<td>'.e($item['teks']).'</td>';
                foreach (['TENGAH','AKHIR'] as $period) { $v=$vals[$period]['bacaan:'.$item['id']] ?? null; $html.='<td class="grade">'.e($scale[(string)$v] ?? '').'</td>'; }
                $html.='</tr>';
            }
        }
        $html.='</tbody></table>';
        $currentKey=$p['period']['jenis']; $note=$vals[$currentKey]['catatan'] ?? null;
        if ($note!==null) $html.='<h2>CATATAN GURU</h2><p class="narrative">'.self::text($note).'</p>';
        $tests=[];
        foreach (['TENGAH','AKHIR'] as $period) foreach ($d['tests'][$period] ?? [] as $test) $tests[]=$test;
        if ($tests) usort($tests,static fn($a,$b)=>[$a['tanggal_tes'],$a['urutan']]<=>[$b['tanggal_tes'],$b['urutan']]);
        $html.='<h2>TES KENAIKAN JILID</h2><table class="report-table"><thead><tr><th>No.</th><th>Tanggal</th><th>Jilid</th><th>Nilai</th></tr></thead><tbody>';
        foreach ($tests as $test) $html.='<tr><td>'.e((string)$test['urutan']).'</td><td>'.e($test['tanggal_tes']).'</td><td>'.e($test['jilid']).'</td><td>'.e($scale[(string)$test['nilai']] ?? $test['nilai']).'</td></tr>';
        for ($row=count($tests);$row<2;$row++) {
            $html.='<tr class="test-placeholder-row"><td>—</td><td>—</td><td>—</td><td>—</td></tr>';
        }
        $html.='</tbody></table>';
        $html.=self::signatureBlock($p,$d,'Pengesahan Rapor Ummi',$d['signature_current'] ?? null);
        return $html;
    }

    private static function bing(array $p,array $d): string
    {
        $f=$d['form']['definitions']; $vals=$d['period_values']['CURRENT'] ?? ($d['form']['values'] ?? []); $labels=[];
        foreach ($f['scale'] ?? [] as $scale) $labels[(string)$scale['kode']]=$scale['label'];
        $html=self::bingHeader($p,$d).'<h2>A. LEARNING ACHIEVEMENT</h2><table class="report-table bing-achievement"><thead><tr><th>Progress Indicators</th><th>In Figures<br><span>(Excellent, Outstanding, Good, Fair)</span></th></tr></thead><tbody>';
        $openGroup=null;
        foreach ($f['items'] ?? [] as $item) {
            $group=trim((string)($item['grup'] ?? ''));
            if ($group!=='' && $group!==$openGroup) $html.='<tr class="section-row bing-group"><th>'.e($group).'</th><th></th></tr>';
            $openGroup=$group!==''?$group:null;
            $html.='<tr><td>'.e(trim(($item['penanda_cetak'] ?? '').' '.$item['label_cetak'])).'</td><td class="grade">'.e($labels[(string)($vals['nilai:'.$item['id']] ?? '')] ?? '').'</td></tr>';
        }
        $html.='</tbody></table><h2>B. TEACHER COMMENTS</h2><table class="report-table bing-comments"><tbody>';
        foreach ($f['comments'] ?? [] as $comment) $html.='<tr><th>'.e($comment['label_cetak']).':</th><td>'.self::bullets($vals['komentar:'.$comment['id']] ?? '').'</td></tr>';
        $html.='</tbody></table><div class="page-break"></div>'.self::bingHeader($p,$d).'<h2>REMARKS</h2><table class="report-table bing-remarks"><tbody>';
        foreach ($f['scale'] ?? [] as $scale) $html.='<tr><th>'.e($scale['label']).'</th><td>'.self::text($scale['definisi'] ?? '').'</td></tr>';
        $html.='</tbody></table>'.self::signatureBlock($p,$d,'',$d['signature_current'] ?? null);
        return $html;
    }

    private static function bingHeader(array $p,array $d): string
    {
        $s=$p['student']; $school=$p['school']['snapshot']; $logo=$p['school']['logo_data_uri'] ?? '';
        $birth='-';
        if (!empty($s['tanggal_lahir'])) {
            try { $birth=(new DateTimeImmutable($s['tanggal_lahir']))->format('d/m/Y'); } catch (Throwable) { $birth=(string)$s['tanggal_lahir']; }
        }
        return '<table class="bing-header"><tr><td class="bing-logo"><img src="'.e($logo).'" alt=""></td><td><strong>STATEMENT OF RESULT</strong><br>ENGLISH CLASS<br>'.e($school['nama_komersial']).'<br>ACADEMIC YEAR '.e($p['period']['tahun_label']).'</td></tr></table>'
            .'<table class="report-table bing-student"><tbody>'
            .'<tr><th>Student\'s Name</th><td>'.e($s['nama_lengkap']).'</td></tr>'
            .'<tr><th>Date of Birth</th><td>'.e($birth).'</td></tr>'
            .'<tr><th>Class</th><td>'.e($s['nama_kelas'] ?? '-').'</td></tr>'
            .'<tr><th>Term / Semester</th><td>'.e($p['period']['label']).'</td></tr>'
            .'</tbody></table>';
    }

    private static function ppi(array $p,array $d): string
    {
        $f=$d['form']['definitions']; $values=$d['form']['values'] ?? []; $columns=$d['all_columns'] ?? $f['columns'] ?? [];
        $student=$p['student'];
        $identity=[['Nama',$student['nama_lengkap']],['Usia',$student['usia'] ?? '-'],['Kelas',$student['nama_kelas'] ?? '-'],['Diagnosa','-'],['Sekolah',$p['school']['snapshot']['nama_komersial']]];
        $html='<table class="report-table ppi-identity"><thead>'.self::tableIdentity($p,$d,4).'</thead></table>'
            .'<h2>A. IDENTITAS</h2><table class="report-table ppi-identity-fields"><tbody>';
        foreach ($identity as [$label,$value]) $html.='<tr><th>'.e($label).'</th><td>'.e((string)$value).'</td></tr>';
        $html.='</tbody></table><h2>B. KARAKTERISTIK ANAK BERDASARKAN HASIL OBSERVASI</h2>'
            .'<table class="report-table ppi-table ppi-observation"><colgroup><col style="width:1cm"><col style="width:3cm"><col style="width:7cm"><col style="width:6cm"></colgroup><thead>'
            .self::tableIdentity($p,$d,4).'<tr><th>No</th><th>Aspek Perkembangan</th><th>Kekuatan</th><th>Tantangan</th></tr></thead><tbody>';
        foreach ($f['aspects'] ?? [] as $i=>$aspect) {
            $by=[]; foreach ($f['columns'] ?? [] as $column) $by[$column['kode']]=$values[$aspect['id'].':'.$column['id']] ?? '';
            $html.='<tr><td>'.($i+1).'</td><td>'.e($aspect['nama']).'</td><td>'.self::bullets($by['kekuatan'] ?? '').'</td><td>'.self::bullets($by['tantangan'] ?? '').'</td></tr>';
        }
        $html.='</tbody></table><h2 class="ppi-program-title">C. PROGRAM PEMBELAJARAN INDIVIDU</h2><p class="ppi-implementer">Pelaksana: Guru dan Orangtua</p>'
            .'<table class="report-table ppi-table ppi-program"><colgroup><col style="width:.8cm"><col style="width:2.4cm">';
        foreach ($columns as $column) $html.='<col style="width:'.e((string)$column['lebar_cetak_cm']).'cm">';
        $html.='</colgroup><thead>'.self::tableIdentity($p,$d,8).'<tr><th rowspan="2">No</th><th rowspan="2">Aspek Perkembangan</th><th colspan="2">Tujuan</th><th rowspan="2">Strategi</th><th rowspan="2">Media</th><th colspan="2">Hasil Capaian</th></tr><tr>';
        // Baris kedua hanya sub-kolom Tujuan dan Hasil Capaian; Strategi/Media sudah rowspan=2 di baris pertama.
        foreach ($columns as $column) {
            if (!preg_match('/^(tujuan_|hasil_capaian_)/',(string)$column['kode'])) continue;
            $html.='<th>'.nl2br(e($column['label_cetak']),false).'</th>';
        }
        $html.='</tr></thead><tbody>';
        foreach ($f['aspects'] ?? [] as $i=>$aspect) {
            $html.='<tr><td>'.($i+1).'</td><td>'.e($aspect['nama']).'</td>';
            foreach ($columns as $column) {
                $value=!empty($column['diisi_di_sesi'])?($values[$aspect['id'].':'.$column['id']] ?? ''):'';
                $html.='<td>'.self::bullets($value).'</td>';
            }
            $html.='</tr>';
        }
        $html.='</tbody></table>'.self::signatureBlock($p,$d,'Pengesahan Program Pembelajaran Individu',$d['signature_current'] ?? null);
        return $html;
    }

    private static function tableIdentity(array $p,array $d,int $columns,int $logoSpan=1): string
    {
        $s=$p['student']; $school=$p['school']['snapshot']; $logo=$p['school']['logo_data_uri'] ?? '';
        $title=$d['judul_cetak'] ?? $d['nama'];
        if ($d['jenis_dokumen']==='UMMI') $title=str_replace('{SEMESTER}',$p['period']['semester_label'],$title);
        if ($d['jenis_dokumen']==='PPI') $title='PROGRAM PEMBELAJARAN INDIVIDU';
        $identity='Nama: '.e($s['nama_lengkap']).' &nbsp; Kelas: '.e($s['nama_kelas'] ?? '-').' &nbsp; NISN: '.e($s['nisn'] ?? '-');
        if ($d['jenis_dokumen']==='UMMI') $identity='Unit Sekolah: '.e($school['nama_komersial']).' &nbsp; Nama: '.e($s['nama_lengkap']).' &nbsp; NISN: '.e($s['nisn'] ?? '-').' &nbsp; Kelas: '.e($s['nama_kelas'] ?? '-');
        if ($d['jenis_dokumen']==='PPI') $identity.='<br>Tempat, tanggal lahir: '.e(trim(($s['tempat_lahir'] ?? '').', '.($s['tanggal_lahir'] ?? ''))).' &nbsp; Usia: '.e($s['usia'] ?? '-');
        $periodLine=$d['jenis_dokumen']==='RTS'?'T.A '.$p['period']['tahun_label']:$p['period']['label'].' · Tahun Pelajaran '.$p['period']['tahun_label'];
        return '<tr class="identity-head"><th colspan="'.($columns-$logoSpan).'"><strong>'.e($title).'</strong><br>'.e($periodLine).'<br>'.e($school['nama_komersial']).'<br>'.$identity.'</th><th class="logo-cell"'.($logoSpan>1?' colspan="'.$logoSpan.'"':'').'><img src="'.e($logo).'" alt=""></th></tr>';
    }

    private static function legend(array $scales): string
    {
        // Tabel satu baris: simbol dan label sejajar tengah, tidak terpotong ke baris baru secara acak.
        $html='<table class="legend"><tr><th>Keterangan</th>';
        foreach ($scales as $scale) $html.='<td class="legend-symbol">'.self::symbol($scale['simbol'] ?? null).'</td><td>'.e($scale['label']).'</td>';
        return $html.'</tr></table>';
    }

    private static function signatureBlock(array $p,array $d,string $title,?array $block): string
    {
        $definitions=$d['signers'] ?? [];
        if ($definitions===[]) throw new DomainException('Rubrik tidak memiliki definisi penandatangan.');
        $positionRank=['kiri'=>0,'tengah'=>1,'kanan'=>2];
        usort($definitions,static fn($a,$b)=>(($positionRank[$a['posisi_cetak'] ?? ''] ?? 3)<=>($positionRank[$b['posisi_cetak'] ?? ''] ?? 3))?:((int)$a['urutan']<=>(int)$b['urutan']));
        $count=max(1,count($definitions));
        $date=$block && !empty($block['tanggal'])?e($block['tempat']).', '.e($block['tanggal']):'&nbsp;';
        // Identitas di tabel terpisah: kolom logo (18%) tidak boleh ikut menentukan lebar kolom tanda tangan,
        // sehingga ketiga kolom benar-benar sama lebar. Blok tidak dipotong ke halaman berikutnya.
        $html='<div class="signature-block"><table class="report-table signature-identity"><thead>'.self::tableIdentity($p,$d,2).'</thead>';
        if ($title!=='') $html.='<tbody><tr><th colspan="2" class="signature-heading">'.e($title).'</th></tr></tbody>';
        $html.='</table><table class="signatures"><tbody><tr>'.str_repeat('<td></td>',$count-1).'<td class="place-date">'.$date.'</td></tr><tr>';
        foreach ($definitions as $definition) {
            $role=(string)$definition['peran']; $signer=$block['signers'][$role] ?? null;
            $job=str_replace('{nama_kelas}',(string)($p['student']['nama_kelas'] ?? ''),(string)$definition['jabatan_cetak']);
            // Tinggi area jabatan tetap (maks. 3 baris) agar gambar dan garis nama ketiga kolom sejajar.
            $html.='<td><div class="sign-job">'.(!empty($definition['prefiks'])?e($definition['prefiks']).'<br>':'').e($job).'</div>';
            if ($signer && !empty($signer['ttd_data_uri'])) $html.='<div class="sign-image"><img src="'.e($signer['ttd_data_uri']).'" alt=""></div>';
            else $html.='<div class="sign-image"></div>';
            $html.=($signer['nama'] ?? '')!==''?'<div class="sign-name">'.e($signer['nama']).'</div>':'<div class="sign-line">&nbsp;</div>';
            if (!empty($definition['cetak_nuptk']) && !empty($signer['nuptk'])) $html.='<div class="sign-nuptk">NUPTK: '.e($signer['nuptk']).'</div>';
            $html.='</td>';
        }
        return $html.'</tr></tbody></table></div>';
    }

    private static function symbol(?string $code): string
    {
        if ($code==='-') return '<span class="skala-dash">&#8212;</span>';
        return $code!==null ? renderSkalaSimbol($code) : '';
    }
    private static function text(mixed $value): string
    {
        return nl2br(e(is_scalar($value)?(string)$value:''),false);
    }
    private static function bullets(mixed $value): string
    {
        if (!is_scalar($value) || (string)$value==='') return '';
        $html='';
        foreach (preg_split('/\R/u',(string)$value) ?: [] as $line) {
            if (trim($line)==='') continue;
            $html.='<div class="bullet-line">• '.e(trim($line)).'</div>';
        }
        return $html;
    }
    private static function css(): string
    {
        $ink=colorToken('neutral-900'); $muted=colorToken('neutral-500'); $line=colorToken('neutral-150');
        $surface=colorToken('neutral-50'); $section=colorToken('neutral-75'); $white=colorToken('neutral-white');
        return '@page{size:A4 portrait;margin:16mm 13mm 14mm}body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:'.$ink.'}.report{page-break-after:always}.report:last-child{page-break-after:auto}h2{font-size:11pt;margin:12pt 0 5pt}.report-table{width:100%;border-collapse:collapse;margin:0 0 10pt}.report-table th,.report-table td{border:.5pt solid '.$line.';padding:4pt;vertical-align:top}.report-table th{background:'.$surface.';font-weight:bold}.report-table thead{display:table-header-group}.report-table tr{page-break-inside:avoid}.section-row th{background:'.$section.';text-align:left}.sub-row th{background:'.$surface.';text-align:left}.group-row td:first-child{font-weight:bold}.grade{text-align:center;width:9%}.identity-head th{background:'.$white.';text-align:left;line-height:1.5}.identity-head .logo-cell{width:18%;text-align:center;vertical-align:middle}.logo-cell img{width:92pt}.legend{width:100%;border-collapse:collapse;margin:4pt 0}.legend th,.legend td{border:0;padding:3pt 4pt;vertical-align:middle;font-size:8pt;text-align:left}.legend th{width:12%;background:none}.legend td.legend-symbol{width:4%;padding-right:0;text-align:center}.skala-simbol{width:12pt;height:12pt;vertical-align:middle}.narrative{line-height:1.5;text-align:justify}.signature-block{page-break-inside:avoid;margin-top:6pt}.signature-identity{margin-bottom:4pt}.signature-heading{text-align:center!important}.signatures{width:100%;border-collapse:collapse;table-layout:fixed}.signatures td{width:33.333%;border:0;text-align:center;vertical-align:top;padding:4pt}.signatures .place-date{padding-bottom:6pt}.sign-line{width:70%;margin:0 auto;border-bottom:.5pt solid '.$ink.';line-height:1}.skala-dash{font-weight:bold;font-size:10pt;line-height:1}.agama-table td{height:12pt}.agama-grade .check{font-size:10pt;font-weight:bold;line-height:1}.sign-job{height:34pt;overflow:hidden}.sign-image{height:45pt;margin:2pt auto}.sign-image img{max-height:43pt;max-width:105pt}.sign-name{font-weight:bold;text-decoration:underline}.sign-nuptk,.muted{font-size:8pt;color:'.$muted.'}.ppi-table{font-size:7pt}.ppi-table th,.ppi-table td{padding:3pt;word-wrap:break-word}.report-ppi{margin-left:7mm;margin-right:7mm}.ppi-program-title,.page-break{page-break-before:always}.ppi-identity-fields th{width:25%;text-align:left}.ppi-identity-fields td{width:75%}.ppi-implementer{margin:0 0 6pt}.ppi-program{width:17cm;table-layout:fixed}.ppi-program th,.ppi-program td{overflow-wrap:break-word}.agama-table{table-layout:fixed;font-size:6pt}.agama-table th,.agama-table td{padding:2pt}.agama-table th:first-child,.agama-table td:first-child{width:30%;text-align:left}.agama-stage th{font-size:4.5pt;padding:2pt 0!important;text-align:center;vertical-align:middle}.agama-grade{width:5%;padding:2pt 0!important;text-align:center;vertical-align:middle!important}.bing-header{width:100%;border-collapse:collapse;margin:0 0 10pt}.bing-header td{border:0;background:'.$white.';text-align:center;line-height:1.4}.bing-logo{width:24%;text-align:left!important}.bing-logo img{width:100pt}.bing-header strong{font-size:15pt}.bing-student th{width:32%;text-align:left}.bing-comments th{text-align:left;width:37%}.bing-group th{background:'.$section.'}.bing-remarks th{width:22%}.bullet-line{margin:0 0 3pt}.report-table .jilid-cell{border-bottom:0!important}.report-table .jilid-cont{border-top:0!important}.report-table tr:last-child .jilid-cell{border-bottom:.5pt solid '.$line.'!important}.col-sizer th{height:0;padding:0!important;border:0!important;background:none!important;line-height:0;font-size:0}.signature-block h2:empty{display:none}';
    }
}
