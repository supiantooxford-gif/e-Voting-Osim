<?php
/**
 * inc/helpers.php — kumpulan fungsi bantu (keamanan, sesi, data, format).
 * ---------------------------------------------------------------------------
 * Semua fungsi di sini dipakai bersama oleh halaman pemilih maupun panel
 * panitia. Prinsipnya: satu aturan hanya ditulis di SATU tempat.
 */
declare(strict_types=1);

/* Berkas ini hanya boleh dijalankan lewat inc/init.php. */
if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

/* =========================================================================
   1. PERSIAPAN FOLDER & SESI
   ========================================================================= */

/** Memastikan folder data/ dan data/sessions/ tersedia & dapat ditulis. */
function siapkan_folder(): void
{
    foreach (['data', SESI_REL] as $rel) {
        $dir = APP_ROOT . '/' . $rel;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

/** Memulai sesi dengan pengaturan cookie yang dikeraskan. */
function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $dir = APP_ROOT . '/' . SESI_REL;
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
    }
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('EVOTINGOSIS');
    session_start();
}

/* =========================================================================
   1b. JENIS LEMBAGA & ISTILAH OSIS/OSIM
   ========================================================================= */

/**
 * Jenis lembaga yang berlaku: 'madrasah' atau 'umum'.
 * Permintaan pemilik: bila Madrasah dipilih, SELURUH tulisan "OSIS" di
 * aplikasi berubah menjadi "OSIM" (dan sebaliknya pada Sekolah Umum).
 */
function jenis_lembaga(): string
{
    $nilai = setting('jenis_lembaga', JENIS_LEMBAGA_BAWAAN);

    return isset(JENIS_LEMBAGA_NAMA[$nilai]) ? $nilai : JENIS_LEMBAGA_BAWAAN;
}

function is_madrasah(): bool
{
    return jenis_lembaga() === 'madrasah';
}

/** Nama jenis lembaga untuk ditampilkan. */
function jenis_lembaga_label(): string
{
    return JENIS_LEMBAGA_NAMA[jenis_lembaga()];
}

/** Istilah kegiatan kesiswaan yang berlaku (OSIS atau OSIM). */
function istilah_osis(): string
{
    return is_madrasah() ? ISTILAH_MADRA : ISTILAH_UMUM;
}

/**
 * Menukar istilah OSIS <-> OSIM pada potongan TEKS BIASA.
 * Arahnya mengikuti jenis lembaga: madrasah => OSIS menjadi OSIM, sekolah umum
 * => OSIM menjadi OSIS. Bentuk huruf dipertahankan ("OSIS"->"OSIM",
 * "Osis"->"Osim", "osis"->"osim").
 *
 * Penukaran DUA ARAH ini penting: nilai yang sudah tersimpan bisa saja berbunyi
 * "OSIM" (ditulis pemilik saat aplikasinya sudah memakai istilah itu), sehingga
 * tanpa arah balik tulisan itu tetap OSIM walau jenisnya Sekolah Umum.
 */
function istilah_osis_teks(string $teks): string
{
    [$dari, $ke] = istilah_osis_arah();

    return preg_replace_callback('/\b' . $dari . '\b/i', static function (array $m) use ($dari, $ke): string {
        $kata  = $m[0];
        $ganti = str_ireplace($dari, $ke, $kata);
        if ($kata === strtoupper($kata)) {
            return strtoupper($ganti);
        }
        if ($kata === strtolower($kata)) {
            return strtolower($ganti);
        }
        if ($kata === ucfirst(strtolower($kata))) {
            return ucfirst(strtolower($ganti));
        }

        return $ganti;
    }, $teks);
}

/** Arah penukaran istilah: [dari, ke] sesuai jenis lembaga. */
function istilah_osis_arah(): array
{
    return is_madrasah()
        ? [ISTILAH_UMUM, ISTILAH_MADRA]
        : [ISTILAH_MADRA, ISTILAH_UMUM];
}

/**
 * Menukar istilah OSIS <-> OSIM pada sebuah HTML.
 * HANYA teks di luar tag yang disentuh, sehingga nama atribut, nilai isian
 * (atribut value), tautan, dan id CSS tidak pernah ikut berubah. Isi
 * <script> dan <style> juga dilewati.
 */
function terjemahkan_osis(string $html): string
{
    [$dari, $ke] = istilah_osis_arah();
    if ($html === '' || $dari === $ke) {
        return $html;
    }

    /* Pisahkan blok <script>/<style> agar tidak ikut diproses. */
    $bagian = preg_split('/(<(?:script|style)\b.*?<\/(?:script|style)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($bagian === false) {
        return $html;
    }

    $hasil = '';
    foreach ($bagian as $i => $potongan) {
        if ($i % 2 === 1) {          /* blok script/style: apa adanya */
            $hasil .= $potongan;
            continue;
        }
        /* Pisahkan tag dari teksnya; hanya bagian teks yang diterjemahkan. */
        $kecil = preg_split('/(<[^>]*>)/', $potongan, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($kecil === false) {
            $hasil .= $potongan;
            continue;
        }
        foreach ($kecil as $j => $isi) {
            $hasil .= ($j % 2 === 1) ? $isi : istilah_osis_teks($isi);
        }
    }

    return $hasil;
}

/** Menerapkan terjemahan istilah pada seluruh keluaran halaman. */
function terjemahkan_selesai(): void
{
    if (!empty($GLOBALS['__ev_buf'])) {
        $GLOBALS['__ev_buf'] = false;
        $isi = ob_get_clean();
        echo terjemahkan_osis((string) $isi);
    }
}

/* =========================================================================
   1c. JENIS PEMILIH (SISWA / GURU)
   ========================================================================= */

/** Data tampilan satu jenis pemilih (label kolom induk & kelompok). */
function jenis_pemilih_info(string $jenis): array
{
    return JENIS_PEMILIH[$jenis] ?? JENIS_PEMILIH['siswa'];
}

/** Jenis pemilih yang sah; nilai lain jatuh ke 'siswa'. */
function jenis_pemilih_sah(?string $jenis): string
{
    return isset(JENIS_PEMILIH[(string) $jenis]) ? (string) $jenis : 'siswa';
}

/** Jumlah pemilih per jenis: ['siswa' => n, 'guru' => n]. */
function pemilih_jumlah_jenis(): array
{
    $hasil = ['siswa' => 0, 'guru' => 0];
    foreach (semua('SELECT jenis, COUNT(*) AS jml FROM pemilih GROUP BY jenis') as $b) {
        $jenis = jenis_pemilih_sah((string) $b['jenis']);
        $hasil[$jenis] = (int) $b['jml'];
    }

    return $hasil;
}

/** Daftar jabatan yang pernah dipakai (untuk saran isian) + pilihan tetap. */
function jabatan_pilihan(): array
{
    $dipakai = [];
    foreach (semua('SELECT DISTINCT jabatan FROM pemilih WHERE jabatan IS NOT NULL AND jabatan <> "" ORDER BY jabatan') as $b) {
        $dipakai[] = (string) $b['jabatan'];
    }

    return array_values(array_unique(array_merge($dipakai, JABATAN_PILIHAN)));
}

/* =========================================================================
   2. KELUARAN AMAN & PENGALIHAN
   ========================================================================= */

/**
 * Meng-escape nilai sebelum dicetak ke HTML.
 * Menerima nilai apa pun (angka sekalipun) supaya tidak ada TypeError
 * tersembunyi seperti `e((int) $n)` yang membuat halaman terpotong.
 */
function e(mixed $teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

/** Mengalihkan ke halaman lain di dalam aplikasi (alamat relatif saja). */
function redirect(string $tujuan): never
{
    /* Tolak alamat absolut/protokol ganda agar tidak bisa dipakai sebagai
       pengalihan terbuka (open redirect). */
    if (preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $tujuan)) {
        $tujuan = 'index.php';
    }
    /* Buang keluaran yang sudah terbuffer (kerangka halaman belum ditutup)
       supaya pengalihan benar-benar bersih tanpa sisa HTML. */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $tujuan, true, 302);
    exit;
}

/** Menyimpan pesan sekali-tampil (flash). */
function flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'][] = ['jenis' => $jenis, 'pesan' => $pesan];
}

/** Mengambil & mengosongkan pesan flash. */
function ambil_flash(): array
{
    $daftar = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $daftar;
}

/* =========================================================================
   3. CSRF (anti pemalsuan permintaan)
   ========================================================================= */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

/** Isian tersembunyi untuk ditaruh di setiap <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Memeriksa token CSRF; menjawab 419 bila tidak sah. */
function csrf_periksa(): void
{
    $kirim = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($kirim === '' || !hash_equals(csrf_token(), $kirim)) {
        http_response_code(419);
        exit('Permintaan kedaluwarsa (token CSRF tidak sah). Muat ulang halaman lalu coba lagi.');
    }
}

/* =========================================================================
   4. PENGGUNA: PANITIA (ADMIN) & PEMILIH
   ========================================================================= */

function is_admin(): bool
{
    return ($_SESSION['peran'] ?? '') === 'admin' && !empty($_SESSION['admin_id']);
}

function is_pemilih(): bool
{
    return ($_SESSION['peran'] ?? '') === 'pemilih' && !empty($_SESSION['pemilih_id']);
}

/** Data panitia yang sedang masuk. */
function admin_kini(): array|false
{
    if (!is_admin()) {
        return false;
    }

    return satu('SELECT * FROM admin WHERE id = ?', [(int) $_SESSION['admin_id']]);
}

/** Data pemilih yang sedang masuk. */
function pemilih_kini(): array|false
{
    if (!is_pemilih()) {
        return false;
    }
    $baris = satu('SELECT * FROM pemilih WHERE id = ?', [(int) $_SESSION['pemilih_id']]);
    if ($baris === false) {
        unset($_SESSION['pemilih_id'], $_SESSION['peran']);
        return false;
    }

    return $baris;
}

/** Halaman panel panitia: wajib masuk sebagai panitia. */
function require_admin(): void
{
    if (!is_admin()) {
        flash('galat', 'Silakan masuk sebagai panitia untuk membuka halaman itu.');
        redirect('index.php?tab=panitia');
    }
}

/** Halaman pemilih: wajib masuk sebagai pemilih & belum memilih. */
function require_pemilih(): void
{
    if (!is_pemilih()) {
        flash('galat', 'Silakan masuk dengan NISN dan token Anda terlebih dahulu.');
        redirect('index.php');
    }
    $pemilih = pemilih_kini();
    if ($pemilih === false || (int) $pemilih['sudah'] === 1) {
        unset($_SESSION['pemilih_id'], $_SESSION['peran']);
        flash('info', 'Anda sudah menggunakan hak pilih. Terima kasih!');
        redirect('index.php');
    }
}

/* =========================================================================
   5. PEMBATAS PERCOBAAN MASUK (anti tebak sandi / token)
   ========================================================================= */

/** Menghitung sisa percobaan masuk yang diizinkan pada sesi ini. */
function coba_sisa(): int
{
    $data = $_SESSION['coba'] ?? ['jumlah' => 0, 'mulai' => time()];
    if (time() - (int) $data['mulai'] > LOGIN_JENDELA) {
        $data = ['jumlah' => 0, 'mulai' => time()];
    }
    $_SESSION['coba'] = $data;

    return max(0, LOGIN_MAKS_COBA - (int) $data['jumlah']);
}

/** Mencatat percobaan masuk yang gagal. */
function coba_tambah(): void
{
    coba_sisa();
    $_SESSION['coba']['jumlah'] = (int) $_SESSION['coba']['jumlah'] + 1;
}

/** Mengosongkan hitungan percobaan (dipanggil setelah berhasil masuk). */
function coba_reset(): void
{
    unset($_SESSION['coba']);
}

/* =========================================================================
   6. PENGATURAN APLIKASI
   ========================================================================= */

function setting(string $kunci, string $bawaan = ''): string
{
    $baris = satu('SELECT nilai FROM pengaturan WHERE kunci = ?', [$kunci]);
    if ($baris === false || $baris['nilai'] === null || $baris['nilai'] === '') {
        return PENGATURAN_BAWAAN[$kunci] ?? $bawaan;
    }

    return (string) $baris['nilai'];
}

function set_setting(string $kunci, string $nilai): void
{
    q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai', [$kunci, $nilai]);
}

/** Apakah hasil pemilihan boleh dilihat publik (tanpa masuk panel)? */
function hasil_publik(): bool
{
    return setting('tampilkan_hasil', '1') === '1';
}

/* =========================================================================
   7. TOKEN PEMILIH
   ========================================================================= */

/** Membuat token acak baru, mis. "K7QP-3MXR". */
function token_baru(): string
{
    $abjad = TOKEN_ABJAD;
    $maks  = strlen($abjad) - 1;
    $kode  = '';
    for ($i = 0; $i < TOKEN_PANJANG; $i++) {
        $kode .= $abjad[random_int(0, $maks)];
    }

    return substr($kode, 0, 4) . '-' . substr($kode, 4);
}

/** Menormalkan tulisan token: huruf besar, tanpa spasi/tanda hubung, O→0, I/L→1. */
function token_norm(string $t): string
{
    $t = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t) ?? '');
    $t = strtr($t, ['O' => '0', 'I' => '1', 'L' => '1']);

    return $t;
}

/**
 * Token yang dijamin belum dipakai pemilih lain (dipakai saat menambah siswa
 * maupun saat mengacak token). Pemeriksaannya diulang, bukan sekadar percaya
 * pada keacakan.
 */
function token_unik(): string
{
    do {
        $t   = token_baru();
        $ada = (int) nilai('SELECT COUNT(*) FROM pemilih WHERE token = ?', [$t]);
    } while ($ada > 0);

    return $t;
}

/**
 * Token yang tahan ditulis salah oleh siswa. Token ABAJAD aplikasi ini tidak
 * memakai O/0/I/1/L sehingga penukaran di atas hanya menolong bila siswa
 * mengetik angka/huruf yang mirip (mis. "0" padahal "O").
 */
function token_cocok(string $masukan, string $tersimpan): bool
{
    return hash_equals(token_norm($tersimpan), token_norm($masukan));
}

/**
 * Ungkapan SQL yang menormalkan token SAMA seperti `token_norm()` di PHP.
 * 🚨 WAJIB dipakai untuk setiap pemeriksaan keunikan token: dulu pemeriksaan
 * memakai `REPLACE(UPPER(token),'-','')` saja (tanpa penukaran O→0/I/L→1),
 * sehingga token "TOKEN-UJI1" dianggap berbeda dari "T0KEN-UJ11" padahal saat
 * siswa mengetik keduanya dianggap sama — akibatnya baris impor lolos
 * pemeriksaan lalu ditolak UNIQUE constraint (impor dibatalkan).
 */
function token_sql_kunci(string $kolom = 'token'): string
{
    return "REPLACE(REPLACE(REPLACE(REPLACE(UPPER($kolom), '-', ''), 'O', '0'), 'I', '1'), 'L', '1')";
}

/** Apakah token itu sudah dipakai pemilih lain (perbandingan ternormalisasi)? */
function token_dipakai(string $token, int $kecualiId = 0): bool
{
    $sql  = 'SELECT COUNT(*) FROM pemilih WHERE ' . token_sql_kunci() . ' = ?';
    $args = [token_norm($token)];
    if ($kecualiId > 0) {
        $sql .= ' AND id <> ?';
        $args[] = $kecualiId;
    }

    return (int) nilai($sql, $args) > 0;
}

/* =========================================================================
   8. DATA PASLON & HASIL
   ========================================================================= */

/** Daftar pasangan calon (urut nomor). $nonaktif = sertakan yang dinonaktifkan. */
function paslon_list(bool $nonaktif = false): array
{
    $sql = 'SELECT * FROM paslon ' . ($nonaktif ? '' : 'WHERE aktif = 1') . ' ORDER BY nomor, id';

    return semua($sql);
}

function paslon_by_id(int $id): array|false
{
    return satu('SELECT * FROM paslon WHERE id = ?', [$id]);
}

/** Nomor urut berikutnya yang belum dipakai. */
function nomor_urut_baru(): int
{
    return ((int) nilai('SELECT MAX(nomor) FROM paslon')) + 1;
}

/** Jumlah suara tiap paslon (termasuk yang sudah dinonaktifkan bila bersuara). */
function suara_per_paslon(): array
{
    $baris = semua(
        'SELECT p.id, COUNT(s.id) AS suara
           FROM paslon p
           LEFT JOIN suara s ON s.paslon_id = p.id
          GROUP BY p.id'
    );
    $peta = [];
    foreach ($baris as $b) {
        $peta[(int) $b['id']] = (int) $b['suara'];
    }

    return $peta;
}

/**
 * Hasil lengkap untuk dashboard real-count.
 * Dipakai halaman hasil, panel panitia, dan endpoint JSON (api_hasil.php)
 * sehingga angkanya selalu sama di semua tempat.
 */
function hasil_lengkap(): array
{
    $peta   = suara_per_paslon();
    $sah    = (int) nilai('SELECT COUNT(*) FROM suara');
    $dpt    = (int) nilai('SELECT COUNT(*) FROM pemilih');
    $masuk  = $sah; /* satu pemilih = satu surat suara */
    $belum  = max(0, $dpt - $masuk);
    $turnout = $dpt > 0 ? ($masuk / $dpt) * 100 : 0.0;

    $palet = ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed', '#65a30d', '#e11d48'];
    $paslon = [];
    $i = 0;
    foreach (semua('SELECT * FROM paslon ORDER BY nomor, id') as $p) {
        $jml = $peta[(int) $p['id']] ?? 0;
        /* Paslon nonaktif tanpa suara tidak perlu tampil di papan hasil. */
        if ((int) $p['aktif'] !== 1 && $jml === 0) {
            continue;
        }
        $paslon[] = [
            'id'      => (int) $p['id'],
            'nomor'   => (int) $p['nomor'],
            'ketua'   => (string) $p['ketua'],
            'wakil'   => (string) $p['wakil'],
            'aktif'   => (int) $p['aktif'],
            'foto'    => foto_paslon($p),
            'suara'   => $jml,
            'persen'  => $sah > 0 ? round(($jml / $sah) * 100, 1) : 0.0,
            'warna'   => $palet[$i % count($palet)],
        ];
        $i++;
    }

    /* Pemenang sementara = suara terbanyak (bila sudah ada suara). */
    $teratas = null;
    foreach ($paslon as $p) {
        if ($sah > 0 && ($teratas === null || $p['suara'] > $teratas['suara'])) {
            $teratas = $p;
        }
    }

    return [
        'dpt'       => $dpt,
        'masuk'     => $masuk,
        'belum'     => $belum,
        'turnout'   => round($turnout, 1),
        'sah'       => $sah,
        'paslon'    => $paslon,
        'teratas'   => $teratas,
        'diperbarui' => date('Y-m-d H:i:s'),
    ];
}

/** Statistik ringkas. */
function statistik(): array
{
    $h = hasil_lengkap();

    return [
        'dpt'     => $h['dpt'],
        'masuk'   => $h['masuk'],
        'belum'   => $h['belum'],
        'turnout' => $h['turnout'],
    ];
}

/** Jumlah paslon aktif. */
function paslon_aktif_jumlah(): int
{
    return (int) nilai('SELECT COUNT(*) FROM paslon WHERE aktif = 1');
}

/**
 * Memeriksa keutuhan data: jumlah surat suara harus sama dengan jumlah pemilih
 * yang bertanda "sudah memilih". Dipakai panel panitia sebagai kendali cepat.
 */
function integritas_data(): array
{
    $suara   = (int) nilai('SELECT COUNT(*) FROM suara');
    $pemilih = (int) nilai('SELECT COUNT(*) FROM pemilih WHERE sudah = 1');

    return ['suara' => $suara, 'pemilih' => $pemilih, 'cocok' => $suara === $pemilih];
}

/* =========================================================================
   9. FOTO KANDIDAT (media proxy platform)
   ========================================================================= */

/** URL foto paslon yang aman dicetak, atau null bila kosong. */
function foto_paslon(array $p): ?string
{
    $url = trim((string) ($p['foto'] ?? ''));

    return $url !== '' && tautan_aman($url) ? $url : null;
}

/** Hanya tautan http/https yang boleh dipakai pada atribut src/href. */
function tautan_aman(?string $url): bool
{
    return (bool) preg_match('#^https?://#i', trim((string) $url));
}

/** Token unggah media (server-side saja — tidak pernah dikirim ke peramban). */
function media_token(): string
{
    $berkas = APP_ROOT . '/' . MEDIA_TOKEN_FILE;
    if (!is_file($berkas)) {
        return '';
    }

    return trim((string) @file_get_contents($berkas));
}

/**
 * Mengirim berkas ke media proxy platform dan mengembalikan URL CDN-nya.
 * Yang disimpan ke basis data HANYA URL hasil respons.
 */
function media_upload(string $berkas_tmp, string $nama_asli): array
{
    $token = media_token();
    if ($token === '') {
        return ['ok' => false, 'pesan' => 'Fasilitas unggah belum siap di server ini. Anda masih bisa memakai kolom "Tautan foto".'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'pesan' => 'Server tidak mendukung unggahan. Gunakan kolom "Tautan foto".'];
    }
    $isi = @file_get_contents($berkas_tmp);
    if ($isi === false || $isi === '') {
        return ['ok' => false, 'pesan' => 'Berkas tidak dapat dibaca. Coba ulangi.'];
    }

    $ch = curl_init(MEDIA_ENDPOINT . '?filename=' . rawurlencode($nama_asli));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $isi,
        CURLOPT_HTTPHEADER     => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $balasan = curl_exec($ch);
    $kode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $galat   = curl_error($ch);
    curl_close($ch);

    if ($balasan === false || $kode < 200 || $kode >= 300) {
        return ['ok' => false, 'pesan' => 'Unggahan ditolak server penyimpanan' . ($galat !== '' ? ' (' . e($galat) . ')' : ' (kode ' . $kode . ')') . '. Coba lagi atau gunakan kolom "Tautan foto".'];
    }
    $json = json_decode((string) $balasan, true);
    if (!is_array($json) || empty($json['ok']) || empty($json['url'])) {
        return ['ok' => false, 'pesan' => 'Balasan unggahan tidak dikenali. Gunakan kolom "Tautan foto".'];
    }

    return ['ok' => true, 'url' => (string) $json['url']];
}

/** Memeriksa berkas gambar yang diunggah (ekstensi, ukuran, isi). */
function validasi_gambar(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'pesan' => 'Berkas gagal diunggah (kode ' . (int) $file['error'] . ').'];
    }
    if ((int) $file['size'] > FOTO_MAKS_BYTE) {
        return ['ok' => false, 'pesan' => 'Ukuran foto melebihi 15 MB.'];
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, FOTO_EKSTENSI, true)) {
        return ['ok' => false, 'pesan' => 'Format foto harus JPG, PNG, GIF, WEBP, atau SVG.'];
    }
    /* Periksa ISI berkas, bukan hanya namanya (SVG tidak dikenali getimagesize). */
    if ($ext === 'svg') {
        $isi = (string) @file_get_contents((string) $file['tmp_name']);
        if (stripos($isi, '<svg') === false) {
            return ['ok' => false, 'pesan' => 'Berkas SVG tidak sah.'];
        }
    } elseif (@getimagesize((string) $file['tmp_name']) === false) {
        return ['ok' => false, 'pesan' => 'Berkas itu bukan gambar yang sah.'];
    }

    return ['ok' => true];
}

/* =========================================================================
   10. AKTIVITAS (jejak keamanan)
   ========================================================================= */

/** Alamat IP pemanggil (di belakang proxy: entri terakhir X-Forwarded-For). */
function ip_pemanggil(): string
{
    $teruskan = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($teruskan !== '') {
        $bagian = array_map('trim', explode(',', $teruskan));

        return (string) end($bagian);
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
}

/** Keterangan peramban & perangkat dari User-Agent. */
function peramban_pemanggil(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $nama = 'Tidak dikenal';
    foreach ([
        'Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Safari' => 'Safari',
        'Firefox' => 'Firefox', 'MSIE' => 'Internet Explorer',
    ] as $tanda => $label) {
        if (stripos($ua, $tanda) !== false) {
            $nama = $label;
            break;
        }
    }
    $perangkat = 'Komputer';
    if (preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua)) {
        $perangkat = preg_match('/iPad|Tablet/i', $ua) ? 'Tablet' : 'HP';
    }

    return $nama . ' - ' . $perangkat;
}

/** Mencatat aktivitas (tidak pernah menyimpan token/sandi). */
function catat_aktivitas(string $jenis, string $keterangan = ''): void
{
    try {
        q(
            'INSERT INTO aktivitas (waktu, jenis, keterangan, ip, peramban) VALUES (?, ?, ?, ?, ?)',
            [date('Y-m-d H:i:s'), $jenis, potong_teks($keterangan, 300), ip_pemanggil(), peramban_pemanggil()]
        );
        /* Pangkas jejak lama supaya tabel tidak tumbuh tanpa batas. */
        if (random_int(1, 40) === 1 && (int) nilai('SELECT COUNT(*) FROM aktivitas') > 2200) {
            q('DELETE FROM aktivitas WHERE id NOT IN (SELECT id FROM aktivitas ORDER BY id DESC LIMIT 2000)');
        }
    } catch (Throwable $e) {
        /* Kegagalan mencatat jejak tidak boleh menggagalkan permintaan. */
    }
}

/* =========================================================================
   11. FORMAT TAMPILAN
   ========================================================================= */

const NAMA_BULAN = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

/** Tanggal gaya Indonesia: "20 Januari 2026" (opsional dengan hari & jam). */
function tanggal_id(?string $waktu, bool $denganHari = false, bool $denganJam = false): string
{
    if ($waktu === null || $waktu === '') {
        return '-';
    }
    $ts = strtotime($waktu);
    if ($ts === false) {
        return '-';
    }
    $teks = date('j', $ts) . ' ' . NAMA_BULAN[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    if ($denganHari) {
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $teks = $hari[(int) date('w', $ts)] . ', ' . $teks;
    }
    if ($denganJam) {
        $teks .= ' pukul ' . date('H:i', $ts) . ' ' . LABEL_ZONA;
    }

    return $teks;
}

/** Angka dengan pemisah ribuan gaya Indonesia. */
function angka(int|float $n): string
{
    return number_format((float) $n, 0, ',', '.');
}

/** Persentase dengan satu angka di belakang koma. */
function persen(float $n): string
{
    return number_format($n, 1, ',', '.') . '%';
}

/** Memecah teks multi-baris menjadi daftar poin yang tidak kosong. */
function poin(?string $teks): array
{
    $hasil = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $teks) ?: [] as $baris) {
        $baris = trim($baris, " \t-*•");
        if ($baris !== '') {
            $hasil[] = $baris;
        }
    }

    return $hasil;
}

/** Warna aksen tiap paslon (dipakai grafik & kartu). */
function warna_paslon(int $i): string
{
    $palet = ['#4f46e5', '#059669', '#d97706', '#db2777', '#0891b2', '#7c3aed', '#65a30d', '#e11d48'];

    return $palet[$i % count($palet)];
}

/**
 * Memotong teks dengan aman. Ekstensi mbstring tidak selalu tersedia pada
 * runtime server, jadi pemotongan huruf dijalankan hanya bila ada.
 */
function potong_teks(?string $teks, int $maks): string
{
    $teks = trim((string) $teks);
    if (function_exists('mb_substr')) {
        return mb_substr($teks, 0, $maks);
    }

    return substr($teks, 0, $maks);
}

/** Membatasi teks isian. */
function batas_teks(?string $teks, int $maks = TEKS_MAKS): string
{
    return potong_teks($teks, $maks);
}

/** Menormalkan NISN (hanya angka & huruf). */
function nisn_bersih(?string $teks): string
{
    return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $teks) ?? '');
}

/** URL halaman ini beserta parameter tertentu (untuk tautan & pengalihan). */
function url_dengan(array $param): string
{
    $halaman = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
    $gabung = array_merge($_GET, $param);
    $gabung = array_filter($gabung, static fn ($v) => $v !== '' && $v !== null);

    return $halaman . ($gabung ? '?' . http_build_query($gabung) : '');
}
