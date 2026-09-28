<?php

/**
 * Baca "Data Piloting E-Rapor" (.xlsx) menjadi guru, kelas, dan murid untuk DataResetSeeder.
 * Data pribadi anak sengaja TIDAK disimpan di repository: file diunggah lewat /sistem/reset-data
 * atau diberikan ke database/bootstrap.php --data=... . Hanya butuh ekstensi zip + SimpleXML.
 */
final class PilotDataImport
{
    /** Kolom wajib pada baris judul (huruf besar/kecil diabaikan). */
    private const HEADERS = [
        'nama' => 'nama anak', 'nisn' => 'nisn', 'kelas' => 'kelas', 'guru' => ['nama guru pic', 'nama guru'], 'keterangan' => 'keterangan',
        'jk' => 'jenis kelamin', 'tempat' => 'tempat', 'lahir' => 'tanggal lahir', 'agama' => 'agama', 'anak_ke' => 'anak ke-',
        'saudara' => 'jumlah bersaudara', 'ayah' => 'nama ayah', 'ibu' => 'nama ibu', 'alamat' => 'alamat',
        'kerja_ayah' => 'pekerjaan ayah', 'kerja_ibu' => 'pekerjaan ibu',
    ];
    /** Kolom opsional format baru: wali kelas per kelas beserta NUPTK dan tautan TTD. */
    private const OPTIONAL = ['wali' => 'nama wali kelas', 'wali_nuptk' => 'nuptk', 'wali_ttd' => 'ttd wali kelas'];
    /** Blok pimpinan di atas tabel (label di kolom A, nama di kolom berikutnya, "NUPTK. …" di baris bawahnya). */
    private const LEADERS = ['kepala' => 'nama kepala', 'koordinator_bing' => 'english coordinator', 'koordinator_quran' => 'koordinator agama'];
    private const LEVELS = ['Akar', 'Batang', 'Ranting', 'Daun'];
    private const MONTHS = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12];

    /**
     * @return array{teachers: array<string,array>, classes: array<string,array>, students: list<array>,
     *   leaders: array<string,array>, wali: array<string,string>}
     * teachers[nama] = nama, jabatan, email, nuptk (null), ttd_url (null); leaders[peran] = nama, nuptk, ttd_url.
     */
    public static function fromXlsx(string $path, string $emailDomain, string $schoolYearStart): array
    {
        [$rows, $links] = self::readRows($path);
        $headerIndex = null; $map = [];
        foreach ($rows as $i => $row) {
            $normalized = array_map(fn($v) => mb_strtolower(trim((string) $v)), $row);
            if (in_array('nama anak', $normalized, true) && (in_array('nama guru', $normalized, true) || in_array('nama guru pic', $normalized, true))) {
                foreach (self::HEADERS as $key => $labels) {
                    $col = false;
                    foreach ((array) $labels as $label) if (($col = array_search($label, $normalized, true)) !== false) break;
                    if ($col === false) throw new DomainException('Kolom "' . ((array) $labels)[0] . '" tidak ditemukan di file Excel.');
                    $map[$key] = $col;
                }
                foreach (self::OPTIONAL as $key => $label) {
                    $col = array_search($label, $normalized, true);
                    if ($col !== false) $map[$key] = $col;
                }
                $headerIndex = $i; break;
            }
        }
        if ($headerIndex === null) throw new DomainException('Baris judul (No, Nama Anak, NISN, Kelas, Nama Guru, …) tidak ditemukan di sheet pertama.');

        $teachers = []; $classes = []; $students = []; $emails = []; $wali = []; $leaders = [];
        $addTeacher = static function (string $name, ?string $nuptk, ?string $ttd) use (&$teachers, &$emails, $emailDomain): void {
            if (!isset($teachers[$name])) {
                $email = self::emailFor($name, $emailDomain, $emails);
                $emails[$email] = true;
                $teachers[$name] = ['nama' => $name, 'jabatan' => 'Guru Kelas', 'email' => $email, 'nuptk' => null, 'ttd_url' => null];
            }
            if ($nuptk !== null) $teachers[$name]['nuptk'] = $nuptk;
            if ($ttd !== null) $teachers[$name]['ttd_url'] = $ttd;
        };
        // Blok pimpinan di atas tabel: kepala sekolah dan koordinator (nama, NUPTK di baris bawahnya, TTD sebagai tautan).
        foreach (array_slice($rows, 0, $headerIndex, true) as $i => $row) {
            $label = mb_strtolower(trim((string) ($row[0] ?? '')));
            foreach (self::LEADERS as $role => $prefix) {
                if (!str_starts_with($label, $prefix)) continue;
                $cells = array_values(array_filter(array_slice($row, 1), fn($v) => trim((string) $v) !== ''));
                $name = trim((string) ($cells[0] ?? ''));
                if ($name === '') continue;
                $ttd = null;
                foreach ($links[$i] ?? [] as $url) { $ttd = $url; break; }
                $nuptk = null;
                foreach ($rows[$i + 1] ?? [] as $value) if (preg_match('/^NUPTK\.?\s*(.*)$/i', trim((string) $value), $m)) $nuptk = self::nuptk($m[1]);
                $leaders[$role] = ['nama' => $name, 'nuptk' => $nuptk, 'ttd_url' => $ttd];
            }
        }
        foreach (array_slice($rows, $headerIndex + 1) as $offset => $row) {
            $get = fn(string $key) => trim((string) ($row[$map[$key]] ?? ''));
            $name = $get('nama');
            if ($name === '') continue;
            $line = $headerIndex + $offset + 2; // nomor baris Excel
            [$level, $className] = self::classOf($get('kelas'), $line);
            $classKey = $level . ' ' . $className;
            $classes[$classKey] = ['level' => $level, 'nama' => $className];

            $teacherName = $get('guru');
            if ($teacherName === '') throw new DomainException("Baris $line: Nama Guru kosong.");
            $addTeacher($teacherName, null, null);
            if (isset($map['wali']) && ($waliName = $get('wali')) !== '') {
                $waliTtd = isset($map['wali_ttd']) ? ($links[$headerIndex + $offset + 1][$map['wali_ttd']] ?? null) : null;
                $addTeacher($waliName, isset($map['wali_nuptk']) ? self::nuptk($get('wali_nuptk')) : null, $waliTtd);
                if (isset($wali[$classKey]) && $wali[$classKey] !== $waliName) throw new DomainException("Baris $line: Kelas $classKey punya lebih dari satu wali kelas.");
                $wali[$classKey] = $waliName;
            }

            $condition = mb_strtolower($get('keterangan'));
            if (!in_array($condition, ['reguler', 'regular', 'abk'], true)) throw new DomainException("Baris $line: Keterangan harus Reguler atau ABK.");
            $gender = mb_strtolower($get('jk'));
            if (!in_array($gender, ['laki-laki', 'perempuan'], true)) throw new DomainException("Baris $line: Jenis Kelamin harus Laki-laki atau Perempuan.");
            $nisn = $get('nisn');
            // Excel menyimpan angka panjang dalam notasi ilmiah (3.210765994E9).
            if (preg_match('/^\d+(\.\d+)?(E\+?\d+)?$/i', $nisn)) $nisn = sprintf('%.0f', (float) $nisn);
            $address = $get('alamat') ?: '-';
            $students[] = [
                'kelas' => $classKey, 'guru' => $teacherName,
                'kondisi' => $condition === 'abk' ? 'Berkebutuhan Khusus' : 'Reguler',
                'row' => [
                    'nama_lengkap' => $name, 'nama_panggilan' => explode(' ', $name)[0],
                    'nisn' => preg_match('/^\d{1,20}$/', $nisn) ? $nisn : null,
                    'agama' => $get('agama') ?: 'Islam', 'nik' => '-', 'no_registrasi_akte' => '-',
                    'jenis_kelamin' => $gender === 'perempuan' ? 'P' : 'L',
                    'tempat_lahir' => $get('tempat') ?: '-', 'tanggal_lahir' => self::date($row[$map['lahir']] ?? '', $line),
                    'alamat' => $address, 'alamat_domisili' => $address,
                    // Tidak ada di file: diisi awal tahun ajaran aktif, dapat diperbarui lewat Manajemen Murid.
                    'tanggal_masuk_sekolah' => $schoolYearStart,
                    'jenis_kebutuhan' => null, 'kelengkapan_berkas' => null,
                    'anak_ke' => self::int($get('anak_ke')), 'jumlah_saudara' => self::int($get('saudara')) ?? 0,
                    'nama_ayah' => $get('ayah') ?: '-', 'pendidikan_ayah' => '-', 'pekerjaan_ayah' => $get('kerja_ayah') ?: '-', 'telp_ayah' => '-',
                    'nama_ibu' => $get('ibu') ?: '-', 'pendidikan_ibu' => '-', 'pekerjaan_ibu' => $get('kerja_ibu') ?: '-', 'telp_ibu' => '-',
                    'status' => 'bersekolah',
                ],
            ];
        }
        if (!$students) throw new DomainException('Tidak ada data murid di file Excel.');
        // Koordinator juga akun guru (satu orang bisa sekaligus wali kelas); kepala sekolah akun tersendiri.
        foreach (['koordinator_bing', 'koordinator_quran'] as $role) {
            if (isset($leaders[$role])) $addTeacher($leaders[$role]['nama'], $leaders[$role]['nuptk'], $leaders[$role]['ttd_url']);
        }
        if (isset($leaders['kepala'])) $leaders['kepala']['email'] = self::emailFor($leaders['kepala']['nama'], $emailDomain, $emails);
        return ['teachers' => $teachers, 'classes' => $classes, 'students' => $students, 'leaders' => $leaders, 'wali' => $wali];
    }

    /** "7058759660230163" -> string; "-" / kosong / bukan 16 digit -> null. */
    private static function nuptk(string $value): ?string
    {
        $value = preg_replace('/\s+/', '', $value);
        if (preg_match('/^\d+(\.\d+)?E\+?\d+$/i', $value)) $value = sprintf('%.0f', (float) $value);
        return preg_match('/^\d{16}$/', $value) ? $value : null;
    }

    /** "Ranting Akasia" -> ['Ranting', 'Akasia']. */
    private static function classOf(string $value, int $line): array
    {
        $parts = preg_split('/\s+/', trim($value), 2);
        $level = ucfirst(mb_strtolower($parts[0] ?? ''));
        if (!in_array($level, self::LEVELS, true) || trim($parts[1] ?? '') === '') {
            throw new DomainException("Baris $line: Kelas \"$value\" harus diawali level (Akar, Batang, Ranting, Daun) lalu nama kelas.");
        }
        return [$level, trim($parts[1])];
    }

    /** Nama tanpa gelar -> nama.depan@domain, unik. */
    private static function emailFor(string $name, string $domain, array $used): string
    {
        $plain = mb_strtolower(trim(explode(',', $name)[0]));
        $plain = preg_replace('/[^a-z\s]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $plain) ?: $plain);
        $words = array_values(array_filter(preg_split('/\s+/', $plain)));
        $base = implode('.', array_slice($words, 0, 2)) ?: 'guru';
        $email = $base . '@' . $domain;
        for ($n = 2; isset($used[$email]); $n++) $email = $base . $n . '@' . $domain;
        return $email;
    }

    /** Serial tanggal Excel, "18 Mei 2021", atau "2021-05-18" -> Y-m-d. */
    private static function date(mixed $value, int $line): string
    {
        $value = trim((string) $value);
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $value . ' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return "$m[1]-$m[2]-$m[3]";
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/i', $value, $m) && isset(self::MONTHS[mb_strtolower($m[2])])
            && checkdate(self::MONTHS[mb_strtolower($m[2])], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], self::MONTHS[mb_strtolower($m[2])], $m[1]);
        }
        throw new DomainException("Baris $line: Tanggal Lahir \"$value\" tidak dikenali (contoh: 18 Mei 2021).");
    }

    private static function int(string $value): ?int
    {
        $value = preg_replace('/\.0+$/', '', $value);
        return preg_match('/^\d+$/', $value) ? (int) $value : null;
    }

    /** Sheet pertama: [baris (sel kosong = ''), tautan [indeks baris][indeks kolom] => URL]. */
    private static function readRows(string $path): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Ekstensi PHP zip tidak tersedia untuk membaca .xlsx.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new DomainException('File bukan .xlsx yang valid.');
        $strings = [];
        if (($shared = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $xml = simplexml_load_string($shared);
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text = (string) $si->t;
                foreach ($si->r as $run) $text .= (string) $run->t;
                $strings[] = $text;
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $relsXml = $zip->getFromName('xl/worksheets/_rels/sheet1.xml.rels');
        $zip->close();
        $targets = [];
        if ($relsXml !== false) {
            foreach (simplexml_load_string($relsXml)->Relationship as $rel) {
                if (str_ends_with((string) $rel['Type'], '/hyperlink')) $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
        }
        if ($sheet === false) throw new DomainException('Sheet pertama tidak ditemukan di file Excel.');
        $xml = simplexml_load_string($sheet);
        $rows = []; $refs = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = 0; foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
                $type = (string) $c['t'];
                $value = $type === 's' ? ($strings[(int) $c->v] ?? '') : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
                $cells[$col - 1] = $value;
            }
            if ($cells) { $max = max(array_keys($cells)); $refs[(int) $row['r']] = count($rows); $rows[] = array_replace(array_fill(0, $max + 1, ''), $cells); }
        }
        $links = [];
        foreach ($xml->hyperlinks->hyperlink ?? [] as $link) {
            $id = (string) $link->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (!isset($targets[$id]) || !preg_match('/^([A-Z]+)(\d+)$/', (string) $link['ref'], $m) || !isset($refs[(int) $m[2]])) continue;
            $col = 0; foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
            $links[$refs[(int) $m[2]]][$col - 1] = $targets[$id];
        }
        return [$rows, $links];
    }
}
