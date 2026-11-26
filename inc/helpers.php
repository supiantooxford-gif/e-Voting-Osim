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
    session_name('EVOTINGOSIM');
    session_start();
}

/* =========================================================================
   1a. LEMBAGA (MADRASAH) — KONTEKS, STATISTIK, KELOLA
   ========================================================================= */

/**
 * Menetapkan lembaga untuk HALAMAN yang sedang dibuka (mis. halaman masuk
 * sebuah lembaga yang dibuka pengunjung tanpa sesi). Nilai ini hanya berlaku
 * selama satu permintaan; konteks pemilih/panitia tetap diambil dari sesi.
 */
function lembaga_pakai(int $id): void
{
    $GLOBALS['__ev_lembaga_id'] = max(0, $id);
}

/**
 * Id lembaga yang sedang aktif — SATU-SATUNYA sumber saringan data.
 *   0  = tidak ada konteks (halaman lintas lembaga: daftar lembaga, panel
 *        Super Admin). Halaman seperti itu TIDAK boleh menampilkan data
 *        paslon/DPT/suara lembaga mana pun tanpa memilih lembaga dulu.
 */
function lembaga_id_aktif(): int
{
    if (!empty($GLOBALS['__ev_lembaga_id'])) {
        return (int) $GLOBALS['__ev_lembaga_id'];
    }

    switch ((string) ($_SESSION['peran'] ?? '')) {
        case PERAN_PEMILIH:
            $p = pemilih_kini();

            return $p !== false ? (int) $p['lembaga_id'] : 0;
        case PERAN_PANITIA:
            $a = admin_kini();

            return $a !== false ? (int) ($a['lembaga_id'] ?? 0) : 0;
        case PERAN_SUPER:
            return (int) ($_SESSION['konteks_lembaga'] ?? 0);
    }

    return 0;
}

/** Baris lembaga yang sedang aktif (false bila tidak ada konteks). */
function lembaga_aktif(): array|false
{
    $id = lembaga_id_aktif();

    return $id > 0 ? lembaga_by_id($id) : false;
}

/** Satu lembaga menurut id (di-cache selama satu permintaan). */
function lembaga_by_id(int $id): array|false
{
    static $cache = [];
    if ($id <= 0) {
        return false;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = satu('SELECT * FROM lembaga WHERE id = ?', [$id]);
    }

    return $cache[$id];
}

/** Daftar lembaga (urut nama). $nonaktif = sertakan yang dinonaktifkan. */
function lembaga_semua(bool $nonaktif = false): array
{
    return semua('SELECT * FROM lembaga ' . ($nonaktif ? '' : 'WHERE aktif = 1') . ' ORDER BY nama COLLATE NOCASE');
}

/** Jenis lembaga yang sah; nilai lain jatuh ke bawaan. */
function lembaga_jenis_sah(?string $jenis): string
{
    return isset(JENIS_LEMBAGA_NAMA[(string) $jenis]) ? (string) $jenis : JENIS_LEMBAGA_BAWAAN;
}

/** Istilah kegiatan kesiswaan aplikasi ini (OSIM). */
function lembaga_istilah(string $jenis): string
{
    return ISTILAH;
}

/** URL logo lembaga (Komisi Pemilihan OSIM) yang aman dicetak, atau null. */
function lembaga_logo(array $l): ?string
{
    $url = trim((string) ($l['logo'] ?? ''));

    return $url !== '' && tautan_aman($url) ? $url : null;
}

/** Menyimpan / menghapus logo sebuah lembaga (null = hapus). */
function lembaga_logo_set(int $id, ?string $url): void
{
    jalankan('UPDATE lembaga SET logo = ?, diubah = ? WHERE id = ?', [
        $url !== null && $url !== '' ? $url : null, date('Y-m-d H:i:s'), $id,
    ]);
}

/**
 * URL logo Komisi Pemilihan OSIM pada HALAMAN DEPAN (pengaturan tingkat
 * aplikasi, lembaga_id = 0), atau null bila belum diunggah.
 * Satu sumber aturan untuk halaman depan (index.php) & panel Super Admin.
 */
function depan_logo(): ?string
{
    $url = trim(setting_lembaga(0, 'depan_logo'));

    return $url !== '' && tautan_aman($url) ? $url : null;
}

/** Menyimpan / menghapus logo halaman depan (null = hapus). */
function depan_logo_set(?string $url): void
{
    set_setting_lembaga(0, 'depan_logo', $url !== null && trim($url) !== '' ? trim($url) : '');
}

/** Inisial nama lembaga untuk kartu di halaman depan (maks 3 huruf). */
function lembaga_inisial(array $l): string
{
    $kata = preg_split('/\s+/', trim((string) ($l['nama'] ?? ''))) ?: [];
    $kata = array_values(array_filter($kata, static fn (string $k): bool => $k !== '' && !preg_match('/^(ma|mts|mi|smk|sma|smp|sd|mak)\.?$/i', $k)));
    $huruf = '';
    foreach ($kata as $k) {
        $huruf .= strtoupper(substr($k, 0, 1));
        if (strlen($huruf) >= 2) {
            break;
        }
    }

    return $huruf !== '' ? $huruf : strtoupper(substr((string) ($l['nama'] ?? 'L'), 0, 1));
}

/** Tahun pelajaran berjalan, mis. "Tahun Pelajaran 2025/2026". */
function tahun_pelajaran(): string
{
    $tahun = (int) date('Y');
    $bulan = (int) date('n');
    /* Tahun pelajaran berganti pada Juli. */
    $awal = $bulan >= 7 ? $tahun : $tahun - 1;

    return 'Tahun Pelajaran ' . $awal . '/' . ($awal + 1);
}

/**
 * Statistik seluruh lembaga dalam beberapa query saja (dipakai dashboard
 * Super Admin). Kunci = id lembaga.
 */
function lembaga_statistik_semua(): array
{
    $hasil = [];
    foreach (lembaga_semua(true) as $l) {
        $hasil[(int) $l['id']] = [
            'paslon' => 0, 'paslon_aktif' => 0, 'dpt' => 0, 'siswa' => 0, 'guru' => 0,
            'masuk' => 0, 'belum' => 0, 'turnout' => 0.0,
        ];
    }
    foreach (semua('SELECT lembaga_id, COUNT(*) AS jml, SUM(aktif) AS aktif FROM paslon GROUP BY lembaga_id') as $b) {
        $id = (int) $b['lembaga_id'];
        if (isset($hasil[$id])) {
            $hasil[$id]['paslon'] = (int) $b['jml'];
            $hasil[$id]['paslon_aktif'] = (int) $b['aktif'];
        }
    }
    foreach (semua('SELECT lembaga_id, jenis, COUNT(*) AS jml, SUM(sudah) AS sudah FROM pemilih GROUP BY lembaga_id, jenis') as $b) {
        $id = (int) $b['lembaga_id'];
        if (!isset($hasil[$id])) {
            continue;
        }
        $hasil[$id]['dpt'] += (int) $b['jml'];
        $hasil[$id]['masuk'] += (int) $b['sudah'];
        if (jenis_pemilih_sah((string) $b['jenis']) === 'guru') {
            $hasil[$id]['guru'] += (int) $b['jml'];
        } else {
            $hasil[$id]['siswa'] += (int) $b['jml'];
        }
    }
    foreach ($hasil as $id => $s) {
        $hasil[$id]['belum'] = max(0, $s['dpt'] - $s['masuk']);
        $hasil[$id]['turnout'] = $s['dpt'] > 0 ? round(($s['masuk'] / $s['dpt']) * 100, 1) : 0.0;
    }

    return $hasil;
}

/** Statistik satu lembaga. */
function lembaga_statistik(int $lembagaId): array
{
    $semua = lembaga_statistik_semua();

    return $semua[$lembagaId] ?? ['paslon' => 0, 'paslon_aktif' => 0, 'dpt' => 0, 'siswa' => 0,
        'guru' => 0, 'masuk' => 0, 'belum' => 0, 'turnout' => 0.0];
}

/** Jumlah akun panitia aktif sebuah lembaga. */
function lembaga_jumlah_akun(int $lembagaId): int
{
    return (int) nilai("SELECT COUNT(*) FROM admin WHERE lembaga_id = ? AND peran = 'panitia' AND aktif = 1", [$lembagaId]);
}

/**
 * Menambah lembaga baru + pengaturan bawaannya (+ akun panitia & data contoh
 * bila diminta) dalam SATU transaksi.
 *
 * @param array      $d     ['nama','jenis','npsn','alamat']
 * @param array|null $akun  ['nama','username','sandi'] — akun panitia pertama
 * @return array{ok:bool,pesan:string,id:int}
 */
function lembaga_tambah(array $d, ?array $akun = null, bool $denganContoh = false): array
{
    $nama  = batas_teks($d['nama'] ?? '', LEMBAGA_NAMA_MAKS);
    $jenis = JENIS_LEMBAGA_BAWAAN;   /* aplikasi ini khusus madrasah */
    $npsn  = preg_replace('/[^0-9]/', '', (string) ($d['npsn'] ?? '')) ?? '';
    $alamat = batas_teks($d['alamat'] ?? '', 255);

    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama lembaga wajib diisi.', 'id' => 0];
    }
    if ($npsn !== '' && strlen($npsn) !== 8) {
        return ['ok' => false, 'pesan' => 'NPSN harus tepat 8 angka (boleh dikosongkan).', 'id' => 0];
    }
    if ($npsn !== '' && (int) nilai('SELECT COUNT(*) FROM lembaga WHERE npsn = ?', [$npsn]) > 0) {
        return ['ok' => false, 'pesan' => 'NPSN ' . $npsn . ' sudah dipakai lembaga lain.', 'id' => 0];
    }
    if ((int) nilai('SELECT COUNT(*) FROM lembaga') >= LEMBAGA_MAKS) {
        return ['ok' => false, 'pesan' => 'Batas jumlah lembaga tercapai (' . LEMBAGA_MAKS . ').', 'id' => 0];
    }
    if ($akun !== null) {
        $periksa = akun_panitia_validasi($akun);
        if (!$periksa['ok']) {
            return ['ok' => false, 'pesan' => (string) $periksa['pesan'], 'id' => 0];
        }
    }

    $pdo = pdo();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        q('INSERT INTO lembaga (nama, jenis, npsn, alamat, aktif, dibuat) VALUES (?, ?, ?, ?, 1, ?)', [
            $nama, $jenis, $npsn !== '' ? $npsn : null, $alamat !== '' ? $alamat : null, date('Y-m-d H:i:s'),
        ]);
        $id = (int) $pdo->lastInsertId();
        lembaga_isi_pengaturan($pdo, $id, $jenis);
        if ($akun !== null) {
            q('INSERT INTO admin (lembaga_id, peran, nama, username, sandi, aktif, dibuat)
               VALUES (?, ?, ?, ?, ?, 1, ?)', [
                $id, PERAN_PANITIA, batas_teks($akun['nama'] ?? '', 120),
                akun_username_bersih($akun['username'] ?? ''),
                password_hash((string) ($akun['sandi'] ?? ''), PASSWORD_DEFAULT), date('Y-m-d H:i:s'),
            ]);
        }
        if ($denganContoh) {
            db_isi_contoh($pdo, $id);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $abaikan) {
        }
        error_log('Tambah lembaga gagal: ' . $e->getMessage());

        return ['ok' => false, 'pesan' => 'Lembaga gagal disimpan. Muat ulang halaman lalu coba lagi.', 'id' => 0];
    }

    return ['ok' => true, 'pesan' => 'Lembaga "' . $nama . '" berhasil ditambahkan.', 'id' => $id];
}

/**
 * Mengubah identitas sebuah lembaga.
 * Nama & jenis sengaja hanya dapat diubah Super Admin (pengontrol utama).
 */
function lembaga_ubah(int $id, array $d): array
{
    $l = lembaga_by_id($id);
    if ($l === false) {
        return ['ok' => false, 'pesan' => 'Lembaga tidak ditemukan.'];
    }
    $nama   = batas_teks($d['nama'] ?? '', LEMBAGA_NAMA_MAKS);
    $jenis  = JENIS_LEMBAGA_BAWAAN;   /* aplikasi ini khusus madrasah */
    $npsn   = preg_replace('/[^0-9]/', '', (string) ($d['npsn'] ?? '')) ?? '';
    $alamat = batas_teks($d['alamat'] ?? '', 255);

    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama lembaga wajib diisi.'];
    }
    if ($npsn !== '' && strlen($npsn) !== 8) {
        return ['ok' => false, 'pesan' => 'NPSN harus tepat 8 angka (boleh dikosongkan).'];
    }
    if ($npsn !== '' && (int) nilai('SELECT COUNT(*) FROM lembaga WHERE npsn = ? AND id <> ?', [$npsn, $id]) > 0) {
        return ['ok' => false, 'pesan' => 'NPSN ' . $npsn . ' sudah dipakai lembaga lain.'];
    }

    jalankan('UPDATE lembaga SET nama = ?, jenis = ?, npsn = ?, alamat = ?, diubah = ? WHERE id = ?', [
        $nama, $jenis, $npsn !== '' ? $npsn : null, $alamat !== '' ? $alamat : null, date('Y-m-d H:i:s'), $id,
    ]);

    return ['ok' => true, 'pesan' => 'Data lembaga "' . $nama . '" diperbarui.'];
}

/** Mengaktifkan / menonaktifkan sebuah lembaga. */
function lembaga_aktif_set(int $id, bool $aktif): void
{
    jalankan('UPDATE lembaga SET aktif = ?, diubah = ? WHERE id = ?', [$aktif ? 1 : 0, date('Y-m-d H:i:s'), $id]);
}

/**
 * Menghapus lembaga BESERTA seluruh datanya (paslon, DPT, suara, pengaturan,
 * akun panitia) dalam satu transaksi.
 * Ditolak bila lembaga itu sudah memiliki surat suara: hasil pemilihan tidak
 * boleh hilang hanya karena lembaganya dihapus — nonaktifkan saja.
 */
function lembaga_hapus(int $id): array
{
    $l = lembaga_by_id($id);
    if ($l === false) {
        return ['ok' => false, 'pesan' => 'Lembaga tidak ditemukan.'];
    }
    $suara = (int) nilai('SELECT COUNT(*) FROM suara WHERE lembaga_id = ?', [$id]);
    if ($suara > 0) {
        return ['ok' => false, 'pesan' => 'Lembaga "' . $l['nama'] . '" sudah memiliki ' . angka($suara)
            . ' surat suara sehingga tidak dapat dihapus. Gunakan tombol Nonaktifkan.'];

    }
    if ((int) nilai('SELECT COUNT(*) FROM lembaga') <= 1) {
        return ['ok' => false, 'pesan' => 'Lembaga terakhir tidak dapat dihapus — aplikasi harus memiliki minimal satu lembaga.'];
    }

    $pdo = pdo();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        jalankan('DELETE FROM suara     WHERE lembaga_id = ?', [$id]);
        jalankan('DELETE FROM pemilih   WHERE lembaga_id = ?', [$id]);
        jalankan('DELETE FROM paslon    WHERE lembaga_id = ?', [$id]);
        jalankan('DELETE FROM pengaturan WHERE lembaga_id = ?', [$id]);
        jalankan('DELETE FROM admin     WHERE lembaga_id = ?', [$id]);
        jalankan('DELETE FROM lembaga   WHERE id = ?', [$id]);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $abaikan) {
        }
        error_log('Hapus lembaga gagal: ' . $e->getMessage());

        return ['ok' => false, 'pesan' => 'Lembaga gagal dihapus. Tidak ada data yang berubah.'];
    }

    return ['ok' => true, 'pesan' => 'Lembaga "' . $l['nama'] . '" beserta seluruh datanya dihapus.'];
}

/** Menetapkan lembaga yang sedang dikelola Super Admin. */
function lembaga_konteks_set(int $id): void
{
    $_SESSION['konteks_lembaga'] = $id > 0 ? $id : 0;
    unset($GLOBALS['__ev_lembaga_id']);
}

/** Keluar dari konteks lembaga (kembali ke panel Super Admin). */
function lembaga_konteks_hapus(): void
{
    unset($_SESSION['konteks_lembaga'], $GLOBALS['__ev_lembaga_id']);
}

/* =========================================================================
   1b. JENIS LEMBAGA & ISTILAH OSIM
   ========================================================================= */

/**
 * Jenis lembaga yang berlaku pada halaman ini: 'madrasah'.
 * Mengembalikan '' bila halaman tidak berada dalam konteks lembaga (halaman
 * depan & panel Super Admin) — halaman itu lintas lembaga.
 * Aplikasi ini KHUSUS madrasah (permintaan pemilik v2.3.0); tidak ada lagi
 * jenis lembaga lain.
 */
function jenis_lembaga(): string
{
    return lembaga_aktif() !== false ? JENIS_LEMBAGA_BAWAAN : '';
}

/** Nama jenis lembaga untuk ditampilkan. */
function jenis_lembaga_label(): string
{
    $jenis = jenis_lembaga();

    return $jenis !== '' ? JENIS_LEMBAGA_NAMA[$jenis] : 'Lintas lembaga';
}

/** Istilah kegiatan kesiswaan aplikasi ini (OSIM). */
function istilah_kegiatan(): string
{
    return ISTILAH;
}

/**
 * Menukar istilah LAMA (OSIS) menjadi istilah aplikasi (OSIM) pada potongan
 * TEKS BIASA. Bentuk huruf dipertahankan ("OSIS"->"OSIM", "Osis"->"Osim",
 * "osis"->"osim").
 *
 * Penukaran ini penting untuk nilai yang SUDAH TERSIMPAN atau diketik pengguna
 * (mis. nama pemilihan buatan versi lama yang masih berbunyi "OSIS"), sehingga
 * tampilan tetap konsisten memakai OSIM tanpa perlu menyunting isian pemilik.
 */
function istilah_terapkan_teks(string $teks): string
{
    $dari = ISTILAH_LAMA;
    $ke   = ISTILAH;

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

/**
 * Menukar istilah lama menjadi istilah aplikasi pada sebuah HTML.
 * HANYA teks di luar tag yang disentuh, sehingga nama atribut, nilai isian
 * (atribut value), tautan, dan id CSS tidak pernah ikut berubah. Isi
 * <script> dan <style> juga dilewati.
 */
function terjemahkan_istilah(string $html): string
{
    if ($html === '') {
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
            $hasil .= ($j % 2 === 1) ? $isi : istilah_terapkan_teks($isi);
        }
    }

    return $hasil;
}

/** Menerapkan penukaran istilah pada seluruh keluaran halaman. */
function terjemahkan_selesai(): void
{
    if (!empty($GLOBALS['__ev_buf'])) {
        $GLOBALS['__ev_buf'] = false;
        $isi = ob_get_clean();
        echo terjemahkan_istilah((string) $isi);
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

/** Jumlah pemilih per jenis pada lembaga aktif: ['siswa' => n, 'guru' => n]. */
function pemilih_jumlah_jenis(?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();
    $hasil = ['siswa' => 0, 'guru' => 0];
    foreach (semua('SELECT jenis, COUNT(*) AS jml FROM pemilih WHERE lembaga_id = ? GROUP BY jenis', [$id]) as $b) {
        $jenis = jenis_pemilih_sah((string) $b['jenis']);
        $hasil[$jenis] = (int) $b['jml'];
    }

    return $hasil;
}

/** Daftar jabatan yang pernah dipakai (untuk saran isian) + pilihan tetap. */
function jabatan_pilihan(?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();
    $dipakai = [];
    foreach (semua("SELECT DISTINCT jabatan FROM pemilih WHERE lembaga_id = ? AND jabatan IS NOT NULL AND jabatan <> ''
                    ORDER BY jabatan", [$id]) as $b) {
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
   4. PENGGUNA: SUPER ADMIN, PANITIA, & PEMILIH
   ========================================================================= */

/** Apakah yang masuk adalah Super Admin (pengontrol utama)? */
function is_super(): bool
{
    return ($_SESSION['peran'] ?? '') === PERAN_SUPER && !empty($_SESSION['admin_id']);
}

/** Apakah yang masuk adalah panitia sebuah lembaga? */
function is_panitia(): bool
{
    return ($_SESSION['peran'] ?? '') === PERAN_PANITIA && !empty($_SESSION['admin_id']);
}

function is_pemilih(): bool
{
    return ($_SESSION['peran'] ?? '') === PERAN_PEMILIH && !empty($_SESSION['pemilih_id']);
}

/**
 * Boleh membuka panel pengelolaan sebuah lembaga?
 * Panitia selalu untuk lembaganya sendiri; Super Admin hanya setelah memilih
 * lembaga (konteks) — tanpa konteks, Super Admin berada di panel Super Admin.
 */
function is_admin(): bool
{
    return (is_panitia() || is_super()) && lembaga_id_aktif() > 0;
}

/** Data akun pengelola yang sedang masuk (Super Admin atau panitia). */
function admin_kini(): array|false
{
    if (!is_super() && !is_panitia()) {
        return false;
    }
    $baris = satu('SELECT * FROM admin WHERE id = ?', [(int) $_SESSION['admin_id']]);
    if ($baris === false || (int) ($baris['aktif'] ?? 1) !== 1) {
        unset($_SESSION['admin_id'], $_SESSION['peran'], $_SESSION['konteks_lembaga']);

        return false;
    }
    /* Panitia yang lembaganya sudah dihapus/nonaktif tidak boleh masuk. */
    if ((string) $baris['peran'] === PERAN_PANITIA) {
        $l = lembaga_by_id((int) ($baris['lembaga_id'] ?? 0));
        if ($l === false || (int) $l['aktif'] !== 1) {
            unset($_SESSION['admin_id'], $_SESSION['peran']);

            return false;
        }
    }

    return $baris;
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

/** Halaman yang HANYA boleh dibuka Super Admin. */
function require_super(): void
{
    if (!is_super()) {
        flash('galat', 'Halaman itu hanya untuk Super Admin.');
        redirect('index.php');
    }
}

/** Halaman panel pengelola lembaga: wajib ada konteks lembaga. */
function require_admin(): void
{
    if (!is_admin()) {
        if (is_super()) {
            flash('info', 'Pilih lembaga yang ingin dikelola lebih dulu.');
            redirect('super.php');
        }
        flash('galat', 'Silakan masuk sebagai panitia untuk membuka halaman itu.');
        redirect('index.php');
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
    /* Lembaga yang dinonaktifkan tidak boleh menerima suara. */
    $l = lembaga_by_id((int) $pemilih['lembaga_id']);
    if ($l === false || (int) $l['aktif'] !== 1) {
        unset($_SESSION['pemilih_id'], $_SESSION['peran']);
        flash('kuning', 'Pemilihan di madrasah itu sedang tidak aktif. Hubungi panitia.');
        redirect('index.php');
    }
}

/* =========================================================================
   4b. AKUN PANITIA (dibuat & diatur Super Admin)
   ========================================================================= */

/** Nama pengguna: dibersihkan dari spasi tepi & karakter aneh. */
function akun_username_bersih(?string $teks): string
{
    return substr(trim((string) $teks), 0, 60);
}

/** Memeriksa isian akun panitia (nama, nama pengguna, sandi). */
function akun_panitia_validasi(array $d, int $kecualiId = 0, bool $wajibSandi = true): array
{
    $nama     = batas_teks($d['nama'] ?? '', 120);
    $username = akun_username_bersih($d['username'] ?? '');
    $sandi    = (string) ($d['sandi'] ?? '');

    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama pengelola wajib diisi.'];
    }
    if (strlen($username) < 4) {
        return ['ok' => false, 'pesan' => 'Nama pengguna minimal 4 karakter.'];
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        return ['ok' => false, 'pesan' => 'Nama pengguna hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung (tanpa spasi).'];
    }
    if ((int) nilai('SELECT COUNT(*) FROM admin WHERE LOWER(username) = LOWER(?) AND id <> ?', [$username, $kecualiId]) > 0) {
        return ['ok' => false, 'pesan' => 'Nama pengguna "' . $username . '" sudah dipakai akun lain.'];
    }
    if ($wajibSandi || $sandi !== '') {
        if (strlen($sandi) < SANDI_MIN) {
            return ['ok' => false, 'pesan' => 'Kata sandi minimal ' . SANDI_MIN . ' karakter.'];
        }
    }

    return ['ok' => true, 'pesan' => ''];
}

/** Membuat akun panitia untuk sebuah lembaga. */
function akun_panitia_tambah(int $lembagaId, array $d): array
{
    $l = lembaga_by_id($lembagaId);
    if ($l === false) {
        return ['ok' => false, 'pesan' => 'Lembaga tidak ditemukan.'];
    }
    $periksa = akun_panitia_validasi($d);
    if (!$periksa['ok']) {
        return $periksa;
    }
    q('INSERT INTO admin (lembaga_id, peran, nama, username, sandi, aktif, dibuat) VALUES (?, ?, ?, ?, ?, 1, ?)', [
        $lembagaId, PERAN_PANITIA, batas_teks($d['nama'] ?? '', 120),
        akun_username_bersih($d['username'] ?? ''),
        password_hash((string) $d['sandi'], PASSWORD_DEFAULT), date('Y-m-d H:i:s'),
    ]);

    return ['ok' => true, 'pesan' => 'Akun panitia "' . akun_username_bersih($d['username'] ?? '') . '" dibuat untuk ' . $l['nama'] . '.'];
}

/** Mengubah nama & nama pengguna akun panitia (sandinya terpisah). */
function akun_panitia_ubah(int $id, array $d): array
{
    $a = satu("SELECT * FROM admin WHERE id = ? AND peran = 'panitia'", [$id]);
    if ($a === false) {
        return ['ok' => false, 'pesan' => 'Akun panitia tidak ditemukan.'];
    }
    $periksa = akun_panitia_validasi($d, $id, false);
    if (!$periksa['ok']) {
        return $periksa;
    }
    jalankan('UPDATE admin SET nama = ?, username = ?, aktif = ? WHERE id = ?', [
        batas_teks($d['nama'] ?? '', 120), akun_username_bersih($d['username'] ?? ''),
        !empty($d['aktif']) ? 1 : 0, $id,
    ]);

    return ['ok' => true, 'pesan' => 'Akun panitia diperbarui.'];
}

/** Menetapkan kata sandi baru sebuah akun (Super Admin). */
function akun_sandi_set(int $id, string $sandi): array
{
    if (strlen($sandi) < SANDI_MIN) {
        return ['ok' => false, 'pesan' => 'Kata sandi minimal ' . SANDI_MIN . ' karakter.'];
    }
    $ubah = jalankan('UPDATE admin SET sandi = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
    if ($ubah < 1) {
        return ['ok' => false, 'pesan' => 'Akun tidak ditemukan.'];
    }

    return ['ok' => true, 'pesan' => 'Kata sandi akun berhasil diganti.'];
}

/** Daftar akun panitia (semua lembaga bila $lembagaId null). */
function akun_panitia_semua(?int $lembagaId = null): array
{
    if ($lembagaId === null) {
        return semua("SELECT a.*, l.nama AS lembaga_nama FROM admin a
                      LEFT JOIN lembaga l ON l.id = a.lembaga_id
                      WHERE a.peran = 'panitia' ORDER BY l.nama COLLATE NOCASE, a.nama COLLATE NOCASE");
    }

    return semua("SELECT a.*, l.nama AS lembaga_nama FROM admin a
                  LEFT JOIN lembaga l ON l.id = a.lembaga_id
                  WHERE a.peran = 'panitia' AND a.lembaga_id = ? ORDER BY a.nama COLLATE NOCASE", [$lembagaId]);
}

/** Jumlah akun panitia per lembaga: [lembaga_id => jumlah]. */
function akun_panitia_jumlah(): array
{
    $peta = [];
    foreach (semua("SELECT lembaga_id, COUNT(*) AS jml FROM admin WHERE peran = 'panitia' GROUP BY lembaga_id") as $b) {
        $peta[(int) $b['lembaga_id']] = (int) $b['jml'];
    }

    return $peta;
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
   6. PENGATURAN APLIKASI (per lembaga + tingkat aplikasi)
   ========================================================================= */

/**
 * Nilai pengaturan untuk lembaga AKTIF.
 * `nama_lembaga` & `jenis_lembaga` tidak dibaca dari tabel pengaturan: keduanya
 * bersumber dari tabel `lembaga` (satu sumber aturan).
 */
function setting(string $kunci, string $bawaan = ''): string
{
    if ($kunci === 'nama_lembaga') {
        $l = lembaga_aktif();
        if ($l !== false) {
            return (string) $l['nama'];
        }

        return $bawaan !== '' ? $bawaan : '';
    }
    if ($kunci === 'jenis_lembaga') {
        $jenis = jenis_lembaga();

        return $jenis !== '' ? $jenis : ($bawaan !== '' ? $bawaan : JENIS_LEMBAGA_BAWAAN);
    }

    return setting_lembaga(lembaga_id_aktif(), $kunci, $bawaan);
}

/** Nilai pengaturan milik satu lembaga (lembaga_id = 0 untuk kunci global). */
function setting_lembaga(int $lembagaId, string $kunci, string $bawaan = ''): string
{
    static $cache = [];
    $scope = in_array($kunci, array_keys(PENGATURAN_GLOBAL_BAWAAN), true) ? 0 : $lembagaId;
    $tanda = $scope . '|' . $kunci;
    if (!array_key_exists($tanda, $cache)) {
        $baris = satu('SELECT nilai FROM pengaturan WHERE lembaga_id = ? AND kunci = ?', [$scope, $kunci]);
        $cache[$tanda] = $baris === false ? null : (string) ($baris['nilai'] ?? '');
    }
    $nilai = $cache[$tanda];
    if ($nilai === null || $nilai === '') {
        return PENGATURAN_BAWAAN[$kunci] ?? $bawaan;
    }

    return $nilai;
}

/** Menyimpan pengaturan lembaga aktif. */
function set_setting(string $kunci, string $nilai): void
{
    set_setting_lembaga(lembaga_id_aktif(), $kunci, $nilai);
}

/** Menyimpan pengaturan sebuah lembaga. */
function set_setting_lembaga(int $lembagaId, string $kunci, string $nilai): void
{
    $scope = in_array($kunci, array_keys(PENGATURAN_GLOBAL_BAWAAN), true) ? 0 : $lembagaId;
    q('INSERT INTO pengaturan (lembaga_id, kunci, nilai) VALUES (?, ?, ?)
       ON CONFLICT(lembaga_id, kunci) DO UPDATE SET nilai = excluded.nilai', [$scope, $kunci, $nilai]);
}

/** Apakah hasil pemilihan sebuah lembaga boleh dilihat publik? */
function hasil_publik(?int $lembagaId = null): bool
{
    $id = $lembagaId ?? lembaga_id_aktif();

    return $id > 0 && setting_lembaga($id, 'tampilkan_hasil', '1') === '1';
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
   8. DATA PASLON & HASIL (selalu terpisah per lembaga)
   ========================================================================= */

/**
 * Daftar pasangan calon sebuah lembaga (urut nomor).
 * $nonaktif = sertakan yang dinonaktifkan.
 */
function paslon_list(bool $nonaktif = false, ?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();
    if ($id <= 0) {
        return [];
    }
    $sql = 'SELECT * FROM paslon WHERE lembaga_id = ?'
        . ($nonaktif ? '' : ' AND aktif = 1')
        . ' ORDER BY nomor, id';

    return semua($sql, [$id]);
}

/** Satu paslon MILIK lembaga aktif (saringan lembaga = anti-IDOR). */
function paslon_by_id(int $id, ?int $lembagaId = null): array|false
{
    $lid = $lembagaId ?? lembaga_id_aktif();
    if ($lid <= 0) {
        return false;
    }

    return satu('SELECT * FROM paslon WHERE id = ? AND lembaga_id = ?', [$id, $lid]);
}

/** Nomor urut berikutnya yang belum dipakai pada lembaga aktif. */
function nomor_urut_baru(?int $lembagaId = null): int
{
    $id = $lembagaId ?? lembaga_id_aktif();

    return ((int) nilai('SELECT MAX(nomor) FROM paslon WHERE lembaga_id = ?', [$id])) + 1;
}

/** Jumlah suara tiap paslon pada lembaga aktif (termasuk yang nonaktif). */
function suara_per_paslon(?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();
    $baris = semua(
        'SELECT p.id, COUNT(s.id) AS suara
           FROM paslon p
           LEFT JOIN suara s ON s.paslon_id = p.id
          WHERE p.lembaga_id = ?
          GROUP BY p.id',
        [$id]
    );
    $peta = [];
    foreach ($baris as $b) {
        $peta[(int) $b['id']] = (int) $b['suara'];
    }

    return $peta;
}

/**
 * Hasil lengkap sebuah lembaga untuk dashboard real-count.
 * Dipakai halaman hasil, panel panitia, dan endpoint JSON (api_hasil.php)
 * sehingga angkanya selalu sama di semua tempat.
 */
function hasil_lengkap(?int $lembagaId = null): array
{
    $id     = $lembagaId ?? lembaga_id_aktif();
    $peta   = suara_per_paslon($id);
    $sah    = (int) nilai('SELECT COUNT(*) FROM suara WHERE lembaga_id = ?', [$id]);
    $dpt    = (int) nilai('SELECT COUNT(*) FROM pemilih WHERE lembaga_id = ?', [$id]);
    $masuk  = $sah; /* satu pemilih = satu surat suara */
    $belum  = max(0, $dpt - $masuk);
    $turnout = $dpt > 0 ? ($masuk / $dpt) * 100 : 0.0;

    $paslon = [];
    $i = 0;
    foreach (semua('SELECT * FROM paslon WHERE lembaga_id = ? ORDER BY nomor, id', [$id]) as $p) {
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
            'warna'   => warna_paslon($i),
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
        'lembaga_id' => $id,
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

/** Statistik ringkas sebuah lembaga. */
function statistik(?int $lembagaId = null): array
{
    $h = hasil_lengkap($lembagaId);

    return [
        'dpt'     => $h['dpt'],
        'masuk'   => $h['masuk'],
        'belum'   => $h['belum'],
        'turnout' => $h['turnout'],
    ];
}

/** Jumlah paslon aktif sebuah lembaga. */
function paslon_aktif_jumlah(?int $lembagaId = null): int
{
    $id = $lembagaId ?? lembaga_id_aktif();

    return (int) nilai('SELECT COUNT(*) FROM paslon WHERE lembaga_id = ? AND aktif = 1', [$id]);
}

/**
 * Memeriksa keutuhan data sebuah lembaga: jumlah surat suara harus sama dengan
 * jumlah pemilih bertanda "sudah memilih".
 */
function integritas_data(?int $lembagaId = null): array
{
    $id      = $lembagaId ?? lembaga_id_aktif();
    $suara   = (int) nilai('SELECT COUNT(*) FROM suara WHERE lembaga_id = ?', [$id]);
    $pemilih = (int) nilai('SELECT COUNT(*) FROM pemilih WHERE lembaga_id = ? AND sudah = 1', [$id]);

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

/** Mencatat aktivitas sebuah lembaga (tidak pernah menyimpan token/sandi). */
function catat_aktivitas(string $jenis, string $keterangan = '', ?int $lembagaId = null): void
{
    $lid = $lembagaId ?? lembaga_id_aktif();
    $lid = $lid > 0 ? $lid : null;
    try {
        q(
            'INSERT INTO aktivitas (lembaga_id, waktu, jenis, keterangan, ip, peramban) VALUES (?, ?, ?, ?, ?, ?)',
            [$lid, date('Y-m-d H:i:s'), $jenis, potong_teks($keterangan, 300), ip_pemanggil(), peramban_pemanggil()]
        );
        /* Pangkas jejak lama PER LEMBAGA supaya tabel tidak tumbuh tanpa batas. */
        if ($lid !== null && random_int(1, 40) === 1
            && (int) nilai('SELECT COUNT(*) FROM aktivitas WHERE lembaga_id = ?', [$lid]) > 2200) {
            q('DELETE FROM aktivitas WHERE lembaga_id = ? AND id NOT IN
               (SELECT id FROM aktivitas WHERE lembaga_id = ? ORDER BY id DESC LIMIT 2000)', [$lid, $lid]);
        }
    } catch (Throwable $e) {
        /* Kegagalan mencatat jejak tidak boleh menggagalkan permintaan. */
    }
}

/**
 * Jejak aktivitas sebuah lembaga (dipakai laporan / keperluan lain).
 * Dashboard PANITIA tidak lagi menampilkannya (v2.5.0) — histori lengkap
 * seluruh lembaga ada di akun Super Admin (super_histori.php).
 */
function aktivitas_terakhir(int $jumlah = 8, ?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();
    if ($id <= 0) {
        return semua('SELECT * FROM aktivitas WHERE lembaga_id IS NULL ORDER BY id DESC LIMIT ' . max(1, $jumlah));
    }

    return semua('SELECT * FROM aktivitas WHERE lembaga_id = ? ORDER BY id DESC LIMIT ' . max(1, $jumlah), [$id]);
}

/* =========================================================================
   10b. HISTORI AKTIVITAS SUPER ADMIN
   -------------------------------------------------------------------------
   Histori perpindahan menu pengelolaan TIDAK lagi ada di panel panitia
   (permintaan pemilik v2.5.0); tempatnya di akun Super Admin. Semua query di
   sini LINTAS lembaga — nama madrasah diambil lewat LEFT JOIN supaya catatan
   yang lembaganya sudah dihapus (lembaga_id NULL) tetap tampil.
   ========================================================================= */

/**
 * Penyaring histori dari parameter GET (halaman super_histori.php).
 *
 * @return array{lembaga: string, cari: string} lembaga: 'semua' | 'lintas' | id
 */
function histori_saring_get(): array
{
    $lembaga = batas_teks($_GET['lembaga'] ?? 'semua', 12);
    /* Pemeriksaan angka memakai preg_match: ekstensi ctype TIDAK selalu
       tersedia pada runtime server (sudah terbukti "Call to undefined
       function ctype_digit()"). */
    if ($lembaga !== 'semua' && $lembaga !== 'lintas' && !preg_match('/^[0-9]{1,9}$/', $lembaga)) {
        $lembaga = 'semua';
    }

    return [
        'lembaga' => $lembaga !== '' ? $lembaga : 'semua',
        'cari'    => batas_teks($_GET['cari'] ?? '', 60),
    ];
}

/** Klausa WHERE + argumen histori aktivitas (satu sumber aturan). */
function histori_where(array $saring): array
{
    $sql  = ' WHERE 1 = 1';
    $args = [];

    if (($saring['lembaga'] ?? 'semua') === 'lintas') {
        $sql .= ' AND a.lembaga_id IS NULL';
    } elseif (preg_match('/^[0-9]{1,9}$/', (string) ($saring['lembaga'] ?? '')) && (int) $saring['lembaga'] > 0) {
        $sql .= ' AND a.lembaga_id = ?';
        $args[] = (int) $saring['lembaga'];
    }

    $cari = trim((string) ($saring['cari'] ?? ''));
    if ($cari !== '') {
        $kunci = '%' . $cari . '%';
        $sql  .= ' AND (a.jenis LIKE ? OR a.keterangan LIKE ? OR a.ip LIKE ? OR a.peramban LIKE ? OR l.nama LIKE ?)';
        $args  = array_merge($args, [$kunci, $kunci, $kunci, $kunci, $kunci]);
    }

    return [$sql, $args];
}

/** Baris histori aktivitas seluruh lembaga, terbaru lebih dulu. */
function histori_aktivitas(array $saring = [], int $batas = 20, int $mulai = 0): array
{
    [$where, $args] = histori_where($saring);
    $batas = max(1, min(200, $batas));
    $mulai = max(0, $mulai);

    return semua(
        'SELECT a.*, l.nama AS lembaga_nama FROM aktivitas a'
        . ' LEFT JOIN lembaga l ON l.id = a.lembaga_id' . $where
        . ' ORDER BY a.id DESC LIMIT ' . $batas . ' OFFSET ' . $mulai,
        $args
    );
}

/** Jumlah baris histori untuk penyaring yang sama (untuk paginasi). */
function histori_aktivitas_jumlah(array $saring = []): int
{
    [$where, $args] = histori_where($saring);

    return (int) nilai(
        'SELECT COUNT(*) FROM aktivitas a LEFT JOIN lembaga l ON l.id = a.lembaga_id' . $where,
        $args
    );
}

/** Daftar madrasah untuk penyaring histori (id => nama). */
function histori_lembaga_pilihan(): array
{
    $hasil = [];
    foreach (semua('SELECT id, nama FROM lembaga ORDER BY nama') as $l) {
        $hasil[(int) $l['id']] = (string) $l['nama'];
    }

    return $hasil;
}

/** Label kegiatan yang mudah dibaca (mis. masuk_panitia → "Masuk panitia"). */
function histori_jenis_label(?string $jenis): string
{
    $teks = trim(str_replace('_', ' ', (string) $jenis));

    return $teks === '' ? '-' : ucfirst($teks);
}

/* =========================================================================
   10c. SEBARAN DPT MENURUT KELAS (grafik dashboard panitia)
   ========================================================================= */

/**
 * Sebaran DPT sebuah lembaga menurut kelas, beserta status sudah/belum memilih.
 * Dipakai grafik "DPT per Kelas" pada dashboard panitia (v2.5.0).
 *
 * Pemilih pada tabel `pemilih` terdiri atas siswa (punya `kelas`) dan guru &
 * tendik (punya `jabatan`, tanpa kelas) — keduanya dihitung, tetapi bar guru
 * dipisah supaya judul "per kelas" tidak menyesatkan.
 *
 * @return array{baris: array<int,array{label:string,dpt:int,sudah:int,belum:int,jenis:string}>,
 *               dpt:int, sudah:int, belum:int, kelas:int, guru:int}
 */
function dpt_per_kelas(?int $lembagaId = null): array
{
    $id = $lembagaId ?? lembaga_id_aktif();

    $kelas = [];
    foreach (semua(
        "SELECT kelas, COUNT(*) AS dpt,
                SUM(CASE WHEN sudah = 1 THEN 1 ELSE 0 END) AS sudah
           FROM pemilih
          WHERE lembaga_id = ? AND jenis = 'siswa'
          GROUP BY kelas",
        [$id]
    ) as $b) {
        $nama = trim((string) ($b['kelas'] ?? ''));
        $kelas[] = [
            'label' => $nama !== '' ? $nama : 'Tanpa kelas',
            'dpt'   => (int) $b['dpt'],
            'sudah' => (int) ($b['sudah'] ?? 0),
            'jenis' => 'kelas',
        ];
    }
    /* Urutan alami: X IPA 1 → X IPA 2 → XI IPA 1 → XII IPA 1. */
    usort($kelas, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

    $guru = satu(
        "SELECT COUNT(*) AS dpt, SUM(CASE WHEN sudah = 1 THEN 1 ELSE 0 END) AS sudah
           FROM pemilih WHERE lembaga_id = ? AND jenis = 'guru'",
        [$id]
    );
    $guruDpt   = (int) ($guru['dpt'] ?? 0);
    $guruSudah = (int) ($guru['sudah'] ?? 0);

    $baris = $kelas;
    if ($guruDpt > 0) {
        $baris[] = [
            'label' => 'Guru & Tendik',
            'dpt'   => $guruDpt,
            'sudah' => $guruSudah,
            'jenis' => 'guru',
        ];
    }
    foreach ($baris as $i => $b) {
        $baris[$i]['belum'] = max(0, $b['dpt'] - $b['sudah']);
    }

    $dpt   = array_sum(array_column($baris, 'dpt'));
    $sudah = array_sum(array_column($baris, 'sudah'));

    return [
        'baris' => $baris,
        'dpt'   => $dpt,
        'sudah' => $sudah,
        'belum' => max(0, $dpt - $sudah),
        'kelas' => count($kelas),
        'guru'  => $guruDpt,
    ];
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
    /* Palet mengikuti tema aplikasi: hijau + kuning keemasan (v2.3.0). */
    $palet = ['#047857', '#d97706', '#0e7490', '#a16207', '#15803d', '#b45309', '#115e59', '#4d7c0f'];

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

/**
 * Jalur yang diminta pengunjung, TANPA query string (mis. "/e-voting-osis/admin").
 * Dipakai untuk alamat rapi: server menyerahkan setiap jalur yang BUKAN berkas
 * nyata ke index.php, sehingga rutenya cukup dikenali di sana (tanpa aturan
 * server / .htaccess — server aplikasi ini Caddy dan tidak membaca .htaccess).
 */
function jalur_permintaan(): string
{
    $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
}

/** Apakah jalur yang diminta adalah alamat tertentu? Garis miring akhir diabaikan. */
function jalur_sama(string $jalur, string $alamat): bool
{
    return rtrim($jalur, '/') === rtrim($alamat, '/') ||
        substr(rtrim($jalur, '/'), -strlen('/' . $alamat)) === '/' . $alamat;
}

/** URL halaman ini beserta parameter tertentu (untuk tautan & pengalihan). */
function url_dengan(array $param): string
{
    $halaman = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
    $gabung = array_merge($_GET, $param);
    $gabung = array_filter($gabung, static fn ($v) => $v !== '' && $v !== null);

    return $halaman . ($gabung ? '?' . http_build_query($gabung) : '');
}
