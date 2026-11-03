<?php
/**
 * inc/dpt.php — impor data pemilih & penyiapan cetak kartu pemilih.
 * ---------------------------------------------------------------------------
 * Dua fitur yang ditambahkan atas permintaan pemilik:
 *   1. Impor data pemilih dari berkas Excel/CSV supaya pengisian DPT cepat.
 *   2. Cetak kartu pemilih (nama, NISN, kelas, token) — 12 kartu per lembar A4.
 *
 * Berkas ini hanya boleh dijalankan lewat inc/init.php.
 */
declare(strict_types=1);

if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

/* =========================================================================
   1. IMPOR DATA PEMILIH
   ========================================================================= */

/**
 * Daftar nama kolom yang dikenali pada berkas impor.
 * Kunci = medan di basis data, nilai = kemungkinan judul kolom di berkas.
 *
 * Kolom identitas selalu disimpan pada medan `nisn`: NISN untuk siswa, NIK untuk
 * guru. Kolom kelompok memakai medan `kelas` (siswa) atau `jabatan` (guru).
 */
function dpt_kolom_kenal(string $jenis = 'siswa'): array
{
    $info     = jenis_pemilih_info($jenis);
    $kelompok = strtolower($info['kelompok']);    /* 'kelas' atau 'jabatan' */

    /* Nomor induk TIDAK saling silang antar jenis: kolom NISN hanya diterima
       pada DPT siswa dan kolom NIK hanya pada DPT guru. Kalau dibiarkan saling
       silang, berkas data siswa bisa masuk ke tab guru (dan sebaliknya) lalu
       tersimpan sebagai pemilih jenis yang salah — jabatannya pun terisi dari
       kolom kelas. Lihat dpt_kolom_jenis_lain() untuk pesan yang menjelaskan. */
    $induk = jenis_pemilih_sah($jenis) === 'guru'
        ? ['nik', 'nip', 'nomor induk', 'no induk', 'kode']
        : ['nisn', 'nis', 'nomor induk', 'no induk', 'nomor induk siswa', 'kode'];

    return [
        'nisn'    => $induk,
        'nama'    => ['nama', 'nama lengkap', 'nama siswa', 'nama guru', 'nama pemilih', 'namalengkap'],
        'kelas'   => $kelompok === 'kelas'
            ? ['kelas', 'rombel', 'rombongan belajar', 'kelasrombel']
            : ['kelas', 'rombel'],
        'jabatan' => ['jabatan', 'posisi', 'tugas', 'jabatan tugas'],
        'token'   => ['token', 'token pemilih', 'kode akses', 'kodeakses', 'password', 'sandi'],
    ];
}

/**
 * Judul kolom nomor induk milik JENIS LAIN.
 * Dipakai untuk memberi pesan yang tepat ketika berkas tertukar tab —
 * misalnya berkas siswa (NISN) dibuka pada tab DPT Guru & Tendik.
 */
function dpt_kolom_jenis_lain(string $jenis): array
{
    return jenis_pemilih_sah($jenis) === 'guru' ? ['nisn', 'nis'] : ['nik'];
}

/**
 * Memeriksa apakah judul kolom memuat nomor induk JENIS LAIN.
 * Mengembalikan ['ada' => bool, 'judul' => judul asli kolom itu].
 */
function dpt_berkas_jenis_lain(array $header, string $jenis): array
{
    $lain = dpt_kolom_jenis_lain($jenis);
    foreach ($header as $judul) {
        $kunci = dpt_kolom_kunci(is_string($judul) ? $judul : (string) $judul);
        foreach ($lain as $cari) {
            if ($kunci === $cari || str_starts_with($kunci, $cari)) {
                return ['ada' => true, 'judul' => (string) $judul];
            }
        }
    }

    return ['ada' => false, 'judul' => ''];
}

/** Menormalkan judul kolom: huruf kecil, hanya huruf/angka. */
function dpt_kolom_kunci(?string $teks): string
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $teks) ?? '');
}

/**
 * Memetakan judul kolom berkas impor ke medan DPT.
 * Dua tahap seperti impor pada aplikasi lain: cocok PERSIS lebih dulu, lalu
 * cocok AWALAN — dan satu kolom tidak boleh dipakai dua medan.
 *
 * @return array{medan: array<string,int>, kolom: array<int,string>}
 */
function dpt_peta_kolom(array $header, string $jenis = 'siswa'): array
{
    $bersih = [];
    foreach ($header as $i => $judul) {
        $bersih[$i] = dpt_kolom_kunci(is_string($judul) ? $judul : (string) $judul);
    }

    $peta  = [];   /* medan => indeks kolom */
    $pakai = [];   /* indeks kolom yang sudah terpakai */

    /* Tahap 1: judul sama persis. */
    foreach (dpt_kolom_kenal($jenis) as $medan => $kandidat) {
        foreach ($kandidat as $kandidatBersih) {
            foreach ($bersih as $i => $judulBersih) {
                if ($judulBersih === '' || isset($pakai[$i])) {
                    continue;
                }
                if ($judulBersih === $kandidatBersih) {
                    $peta[$medan] = $i;
                    $pakai[$i] = true;
                    continue 3;
                }
            }
        }
    }

    /* Tahap 2: judul diawali nama medan (mis. "Nama Siswa (lengkap)"). */
    foreach (dpt_kolom_kenal($jenis) as $medan => $kandidat) {
        if (isset($peta[$medan])) {
            continue;
        }
        foreach ($kandidat as $kandidatBersih) {
            foreach ($bersih as $i => $judulBersih) {
                if ($judulBersih === '' || isset($pakai[$i])) {
                    continue;
                }
                if (str_starts_with($judulBersih, $kandidatBersih)) {
                    $peta[$medan] = $i;
                    $pakai[$i] = true;
                    continue 3;
                }
            }
        }
    }

    return $peta;
}

/**
 * Menyimpan hasil impor dari berkas Excel/CSV.
 *
 * @param array $header    Baris judul kolom.
 * @param array $rows      Baris data (tiap baris array nilai).
 * @param bool  $perbarui  true = data pemilih yang sudah ada ikut diperbarui.
 * @return array Ringkasan: tambah, ubah, lewat, masalah[], token_baru[]
 */
function dpt_impor_baris(array $header, array $rows, bool $perbarui = false, string $jenis = 'siswa'): array
{
    $jenis   = jenis_pemilih_sah($jenis);
    $info    = jenis_pemilih_info($jenis);
    $peta    = dpt_peta_kolom($header, $jenis);
    $ringkas = [
        'total' => 0, 'tambah' => 0, 'ubah' => 0, 'lewat' => 0,
        'masalah' => [], 'token_baru' => [], 'kolom' => $peta,
        'kolom_dikenal' => array_keys($peta),
    ];
    $masalah = static function (string $pesan) use (&$ringkas): void {
        if (count($ringkas['masalah']) < 12) {
            $ringkas['masalah'][] = $pesan;
        }
    };

    if (!isset($peta['nisn']) || !isset($peta['nama'])) {
        $lain = dpt_berkas_jenis_lain($header, $jenis);
        if ($lain['ada']) {
            $jenisLain = $jenis === 'guru' ? 'siswa' : 'guru';
            $ringkas['masalah'][] = 'Berkas itu tampaknya berisi data '
                . jenis_pemilih_info($jenisLain)['nama'] . ' (memakai kolom "' . $lain['judul']
                . '"). Anda sedang membuka tab DPT ' . $info['nama'] . ', yang memerlukan kolom "'
                . $info['induk'] . '" dan "Nama". Buka tab DPT '
                . jenis_pemilih_info($jenisLain)['nama'] . ' lalu impor berkas itu di sana.';
        } else {
            $ringkas['masalah'][] = 'Judul kolom tidak dikenali. Berkas impor harus memuat kolom '
                . '"' . $info['induk'] . '" dan "Nama" (boleh ditambah "' . $info['kelompok']
                . '" dan "Token"). Unduh contoh format untuk memastikan.';
        }

        return $ringkas;
    }

    $pdo = pdo();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $dipakaiDalamBerkas = [];
        $urutan = 0;

        foreach ($rows as $baris) {
            if (!is_array($baris)) {
                continue;
            }
            /* Lewati baris yang seluruh selnya kosong. */
            $adaIsi = false;
            foreach ($baris as $sel) {
                if (trim((string) $sel) !== '') { $adaIsi = true; break; }
            }
            if (!$adaIsi) {
                continue;
            }

            $urutan++;
            if ($urutan > IMPOR_MAKS_BARIS) {
                $masalah('Baris ke-' . $urutan . ' dan seterusnya dilewati: batas ' . angka(IMPOR_MAKS_BARIS) . ' baris per impor.');
                break;
            }
            $ringkas['total']++;

            $ambil = static function (string $medan) use ($peta, $baris): string {
                if (!isset($peta[$medan])) {
                    return '';
                }

                return trim((string) ($baris[$peta[$medan]] ?? ''));
            };

            $nisn  = nisn_bersih($ambil('nisn'));
            $nama  = batas_teks($ambil('nama'), 120);
            $kelas = batas_teks($ambil('kelas'), 60);
            /* Untuk guru, kolom kelompok = Jabatan (kolom `kelas` dikosongkan). */
            $jabatan = $jenis === 'guru' ? batas_teks($ambil('jabatan') !== '' ? $ambil('jabatan') : $kelas, 80) : '';
            if ($jenis === 'guru') {
                $kelas = '';
            }
            $token = strtoupper(trim($ambil('token')));

            if ($nisn === '') {
                $masalah('Baris ' . $urutan . ': ' . $info['induk'] . ' kosong — baris dilewati.');
                continue;
            }
            if ($nama === '') {
                $masalah('Baris ' . $urutan . ': nama pemilih kosong — baris dilewati.');
                continue;
            }
            if (isset($dipakaiDalamBerkas[$nisn])) {
                $masalah('Baris ' . $urutan . ': ' . $info['induk'] . ' ' . $nisn
                    . ' muncul dua kali di berkas — baris kedua dilewati.');
                continue;
            }
            $dipakaiDalamBerkas[$nisn] = true;

            $lama = satu('SELECT * FROM pemilih WHERE nisn = ?', [$nisn]);

            /* Token: pakai dari berkas bila sah & belum dipakai siswa lain. */
            $tokenSiap = '';
            if ($token !== '') {
                if (strlen(token_norm($token)) < 4) {
                    $masalah('Baris ' . $urutan . ': token "' . $token . '" terlalu pendek — dibuatkan token baru.');
                } elseif (token_dipakai($token, $lama !== false ? (int) $lama['id'] : 0)) {
                    $masalah('Baris ' . $urutan . ': token ' . $token . ' sudah dipakai siswa lain — dibuatkan token baru.');
                } else {
                    $tokenSiap = $token;
                }
            }

            if ($lama === false) {
                /* Menyimpan dengan percobaan ulang: bila token ternyata sudah
                   terpakai (mis. dua panitia mengimpor bersamaan), token baru
                   dibuat lalu penyimpanan diulang — impor tidak pernah gagal
                   hanya karena bentrok token. */
                $waktu = date('Y-m-d H:i:s');
                for ($coba = 1; ; $coba++) {
                    if ($tokenSiap === '' || ($coba > 1 && token_dipakai($tokenSiap))) {
                        $tokenSiap = token_unik();
                    }
                    try {
                        q('INSERT INTO pemilih (nisn, nama, kelas, token, sudah, dibuat, jenis, jabatan)'
                            . ' VALUES (?, ?, ?, ?, 0, ?, ?, ?)',
                            [$nisn, $nama, $kelas, $tokenSiap, $waktu, $jenis, $jabatan === '' ? null : $jabatan]);
                        break;
                    } catch (PDOException $e) {
                        if ($coba >= 5 || !str_contains($e->getMessage(), 'UNIQUE')) {
                            throw $e;
                        }
                        $masalah('Baris ' . $urutan . ': token ' . $tokenSiap . ' sudah terpakai saat disimpan — dibuatkan token baru.');
                        $tokenSiap = '';
                    }
                }
                $ringkas['tambah']++;
                if (count($ringkas['token_baru']) < 30) {
                    $ringkas['token_baru'][] = [
                        'nisn' => $nisn, 'nama' => $nama, 'kelas' => $kelas,
                        'jabatan' => $jabatan, 'token' => $tokenSiap,
                    ];
                }
            } elseif ($perbarui) {
                if ($tokenSiap === '') {
                    $tokenSiap = (string) $lama['token'];
                }
                q('UPDATE pemilih SET nama = ?, kelas = ?, jabatan = ?, token = ?, jenis = ? WHERE id = ?',
                    [$nama, $kelas, $jabatan === '' ? null : $jabatan, $tokenSiap, $jenis, (int) $lama['id']]);
                $ringkas['ubah']++;
            } else {
                $ringkas['lewat']++;
            }
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $abaikan) {}
        /* Dicatat ke log server supaya penyebabnya dapat ditelusuri. */
        error_log('Impor DPT gagal: ' . $e->getMessage());
        $ringkas['masalah'][] = 'Impor dibatalkan karena gangguan penyimpanan — TIDAK ada data yang masuk.';
        $ringkas['tambah'] = 0;
        $ringkas['ubah'] = 0;
        $ringkas['lewat'] = 0;
    }

    return $ringkas;
}

/** Contoh isi berkas impor (dipakai tombol "Unduh contoh format"). */
function dpt_contoh_baris(string $jenis = 'siswa'): array
{
    if (jenis_pemilih_sah($jenis) === 'guru') {
        return [
            ['NIK', 'Nama', 'Jabatan', 'Token'],
            ['197501012005011001', 'Drs. Ahmad Fauzi, M.Pd', 'Kepala Sekolah/Madrasah', 'AB12-CD34'],
            ['198203152008012002', 'Siti Aminah, S.Pd', 'Guru', ''],
            ['199005202015031003', 'Rahmat Hidayat, S.Pd', 'Staf Tata Usaha', ''],
        ];
    }

    return [
        ['NISN', 'Nama', 'Kelas', 'Token'],
        ['0071234501', 'Aisyah Ramadhani', 'X IPA 1', 'K7QP-3MXR'],
        ['0071234502', 'Budi Santoso', 'X IPA 1', ''],
        ['0071234503', 'Citra Dewi Lestari', 'X IPS 1', ''],
    ];
}

/** Mengirim berkas CSV contoh (kolom DPT) ke peramban. */
function dpt_contoh_csv(string $jenis = 'siswa'): never
{
    $jenis = jenis_pemilih_sah($jenis);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contoh-format-impor-dpt-'
        . ($jenis === 'guru' ? 'guru' : 'siswa') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF"; /* BOM supaya Excel membaca huruf khusus dengan benar */
    $keluaran = fopen('php://output', 'wb');
    foreach (dpt_contoh_baris($jenis) as $baris) {
        fputcsv($keluaran, $baris, ';');
    }
    fclose($keluaran);
    exit;
}

/* =========================================================================
   2. CETAK KARTU PEMILIH
   ========================================================================= */

/** Membaca penyaring halaman DPT/kartu dari parameter GET. */
function dpt_filter_get(): array
{
    $status = (string) ($_GET['status'] ?? 'semua');
    if (!in_array($status, ['semua', 'belum', 'sudah'], true)) {
        $status = 'semua';
    }

    return [
        'cari'   => batas_teks($_GET['cari'] ?? '', 60),
        'status' => $status,
        'kelas'  => batas_teks($_GET['kelas'] ?? '', 60),
        /* Jenis pemilih: siswa (NISN/Kelas) atau guru (NIK/Jabatan). */
        'jenis'  => jenis_pemilih_sah($_GET['jenis'] ?? 'siswa'),
    ];
}

/** Menyusun klausa WHERE dari penyaring DPT. */
function dpt_where(array $f): array
{
    $sql  = ' WHERE 1 = 1';
    $args = [];
    if ($f['cari'] !== '') {
        $sql .= ' AND (nama LIKE ? OR nisn LIKE ? OR kelas LIKE ? OR token LIKE ?)';
        $kunci = '%' . $f['cari'] . '%';
        $args = array_merge($args, [$kunci, $kunci, $kunci, $kunci]);
    }
    if ($f['status'] === 'sudah') {
        $sql .= ' AND sudah = 1';
    } elseif ($f['status'] === 'belum') {
        $sql .= ' AND sudah = 0';
    }
    if ($f['kelas'] !== '') {
        $sql .= ' AND kelas = ?';
        $args[] = $f['kelas'];
    }
    /* DPT siswa & guru disimpan pada tabel yang sama, dipisah kolom `jenis`. */
    $sql .= ' AND jenis = ?';
    $args[] = $f['jenis'];

    return [$sql, $args];
}

/** Daftar pemilih sesuai penyaring (dipakai halaman cetak kartu). */
function dpt_pemilih_saring(array $f): array
{
    [$where, $args] = dpt_where($f);
    $urut = $f['jenis'] === 'guru' ? 'jabatan, nama' : 'kelas, nama';

    return semua('SELECT * FROM pemilih' . $where . ' ORDER BY ' . $urut, $args);
}

/** Daftar jabatan yang dipakai pemilih guru (untuk pilihan penyaring). */
function dpt_jabatan_daftar(): array
{
    $hasil = [];
    foreach (semua('SELECT DISTINCT jabatan FROM pemilih WHERE jenis = "guru"'
        . ' AND jabatan IS NOT NULL AND jabatan <> "" ORDER BY jabatan') as $b) {
        $hasil[] = (string) $b['jabatan'];
    }

    return $hasil;
}

/** Daftar kelas yang dipakai pemilih (untuk pilihan penyaring). */
function dpt_kelas_daftar(): array
{
    $hasil = [];
    foreach (semua('SELECT DISTINCT kelas FROM pemilih WHERE jenis = "siswa"'
        . ' AND kelas IS NOT NULL AND kelas <> "" ORDER BY kelas') as $b) {
        $hasil[] = (string) $b['kelas'];
    }

    return $hasil;
}
