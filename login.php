<?php
/**
 * login.php — memproses masuk pemilih & panitia.
 * ---------------------------------------------------------------------------
 * Semua pemeriksaan penting ada di SERVER (bukan hanya di peramban):
 *   - token CSRF, batas percobaan, kecocokan token, status "sudah memilih".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_periksa();

if (coba_sisa() <= 0) {
    flash('galat', 'Terlalu banyak percobaan masuk. Coba lagi dalam 10 menit.');
    catat_aktivitas('masuk_diblokir', 'Percobaan masuk melebihi batas');
    redirect('index.php');
}

$peran = ($_GET['peran'] ?? '') === 'admin' ? 'admin' : 'pemilih';

/* =========================== 1. MASUK PEMILIH =========================== */
if ($peran === 'pemilih') {
    $nisn  = nisn_bersih($_POST['nisn'] ?? '');
    $token = (string) ($_POST['token'] ?? '');

    if ($nisn === '' || trim($token) === '') {
        coba_tambah();
        flash('galat', 'NISN dan token wajib diisi.');
        redirect('index.php');
    }

    $pemilih = satu('SELECT * FROM pemilih WHERE nisn = ?', [$nisn]);
    if ($pemilih === false) {
        coba_tambah();
        flash('galat', 'NISN tidak terdaftar pada Daftar Pemilih Tetap. Hubungi panitia OSIS.');
        catat_aktivitas('masuk_gagal', 'NISN tidak terdaftar: ' . $nisn);
        redirect('index.php');
    }
    if (!token_cocok($token, (string) $pemilih['token'])) {
        coba_tambah();
        flash('galat', 'Token tidak sesuai. Periksa kembali atau minta token baru kepada panitia.');
        catat_aktivitas('masuk_gagal', 'Token salah untuk NISN: ' . $nisn);
        redirect('index.php');
    }
    if ((int) $pemilih['sudah'] === 1) {
        flash('kuning', 'NISN ini sudah menggunakan hak pilih pada '
            . tanggal_id((string) $pemilih['waktu'], false, true) . '. Setiap pemilih hanya boleh memilih satu kali.');
        catat_aktivitas('masuk_gagal', 'Token sudah dipakai: ' . $nisn);
        redirect('index.php');
    }

    /* Berhasil: segarkan id sesi (anti session fixation) lalu simpan identitas. */
    session_regenerate_id(true);
    $_SESSION['peran']      = 'pemilih';
    $_SESSION['pemilih_id'] = (int) $pemilih['id'];
    coba_reset();
    catat_aktivitas('masuk_pemilih', 'Pemilih masuk: ' . $pemilih['nama'] . ' (' . $pemilih['kelas'] . ')');
    redirect('pilih.php');
}

/* =========================== 2. MASUK PANITIA =========================== */
$username = trim((string) ($_POST['username'] ?? ''));
$sandi    = (string) ($_POST['sandi'] ?? '');

if ($username === '' || $sandi === '') {
    coba_tambah();
    flash('galat', 'Nama pengguna dan kata sandi wajib diisi.');
    redirect('index.php?tab=panitia');
}

$admin = satu('SELECT * FROM admin WHERE username = ?', [$username]);
if ($admin === false || !password_verify($sandi, (string) $admin['sandi'])) {
    coba_tambah();
    /* Pesan sengaja umum supaya tidak membocorkan nama pengguna yang ada. */
    flash('galat', 'Nama pengguna atau kata sandi salah. Sisa percobaan: ' . coba_sisa() . ' kali.');
    catat_aktivitas('masuk_gagal', 'Percobaan masuk panitia gagal: ' . $username);
    redirect('index.php?tab=panitia');
}

session_regenerate_id(true);
$_SESSION['peran']    = 'admin';
$_SESSION['admin_id'] = (int) $admin['id'];
coba_reset();
catat_aktivitas('masuk_panitia', 'Panitia masuk: ' . $admin['nama']);
flash('ok', 'Selamat datang, ' . $admin['nama'] . '.');
redirect('admin.php');
