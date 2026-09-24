<?php

/** Additional independently configurable actions; 'lihat' remains required. */
class PermissionCatalog
{
    public const EXTRA = [
        ['Sekolah','Data Sekolah',null,'tahun_ajaran','edit'],
        ['Sekolah','Kurikulum','Manajemen Template','pdf','lihat'],
        ['Sekolah','Kurikulum','Periode Penilaian','tambah','edit'],
        ['Sekolah','Kurikulum','Periode Penilaian','hapus','edit'],
        ['Human Capital','Karyawan','Daftar Karyawan','tambah','edit'],
        ['Human Capital','Karyawan','Daftar Karyawan','hapus','edit'],
        ['Human Capital','Karyawan','Daftar Karyawan','status','edit'],
        ['Human Capital','Karyawan','Daftar Karyawan','kata_sandi','edit'],
        ['Human Capital','Karyawan','Jabatan','tambah','edit'],
        ['Human Capital','Karyawan','Jabatan','hapus','edit'],
        ['Human Capital','Karyawan','Jabatan','status','edit'],
        ['Murid','Manajemen Murid',null,'tambah','edit'],
        ['Murid','Manajemen Kelas',null,'tambah','edit'],
        ['Murid','Manajemen Kelas',null,'hapus','edit'],
        ['Murid','Manajemen Kelas',null,'atur_murid','edit'],
        ['Murid','Rapor Murid',null,'pdf','lihat'],
        ['Portal Guru','Daftar Murid',null,'kirim','edit'],
        ['Portal Guru','Daftar Murid',null,'pdf','lihat'],
        ['eRapor','Persetujuan',null,'lihat',null],
        ['eRapor','Persetujuan',null,'edit',null],
    ];

    public static function label(array $permission): string
    {
        $action=$permission['aksi']; $section=$permission['sub_section'] ?: $permission['section'];
        if ($permission['modul']==='eRapor') return $action==='edit'?'Setujui persetujuan yang ditugaskan':'Lihat antrean & pratinjau persetujuan';
        if ($action==='lihat') return 'Lihat daftar, detail & pratinjau';
        if ($action==='edit') return match($section) {
            'Rapor Murid'=>'Setujui Rapor', 'Manajemen Guru'=>'Atur Anak Murid',
            'Daftar Murid'=>'Isi & Simpan Rapor', 'RBAC'=>'Simpan Hak Akses', default=>'Ubah Data',
        };
        return ['tambah'=>'Tambah','hapus'=>'Hapus','status'=>'Aktifkan / Nonaktifkan','kata_sandi'=>'Ubah Kata Sandi','atur_murid'=>'Atur Anak Murid','tahun_ajaran'=>'Perbarui Tahun Ajaran','pdf'=>'Simpan PDF','kirim'=>'Kirim Rapor untuk Persetujuan'][$action] ?? ucfirst($action);
    }

    public static function visible(array $permission): bool
    {
        return !($permission['aksi']==='edit' && in_array($permission['sub_section'] ?: $permission['section'], ['Manajemen Template','Dashboard'], true));
    }
}
