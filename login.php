<?php
/**
 * login.php — memproses masuk Super Admin, panitia, & pemilih.
 * ---------------------------------------------------------------------------
 * Semua pemeriksaan penting ada di SERVER (bukan hanya di peramban):
 *   - token CSRF, batas percobaan, kecocokan token, status "sudah memilih";
 *   - akun panitia diperiksa TERHADAP lembaganya (panitia lembaga A tidak dapat
 *     masuk lewat halaman lembaga B);
 *   - NISN/NIK diperiksa di dalam lembaga yang dipilih, sehingga dua lembaga
 *     boleh memiliki nomor induk yang sama tanpa saling bertabrakan.
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

$peran = (string) ($_GET['peran'] ?? 'pemilih');

/* ======================= 1. MASUK SUPER ADMIN ======================= */
if ($peran === 'super') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $sandi    = (string) ($_POST['sandi'] ?? '');

    if ($username === '' || $sandi === '') {
        coba_tambah();
        flash('galat', 'Nama pengguna dan kata sandi wajib diisi.');
        redirect('admin');
    }

    $admin = satu("SELECT * FROM admin WHERE LOWER(username) = LOWER(?) AND peran = 'super' AND aktif = 1", [$username]);
    if ($admin === false || !password_verify($sandi, (string) $admin['sandi'])) {
        coba_tambah();
        /* Pesan sengaja umum supaya tidak membocorkan nama pengguna yang ada. */
        flash('galat', 'Nama pengguna atau kata sandi Super Admin salah. Sisa percobaan: ' . coba_sisa() . ' kali.');
        catat_aktivitas('masuk_gagal', 'Percobaan masuk Super Admin gagal: ' . $username);
        redirect('admin');
    }

    session_regenerate_id(true);
    $_SESSION['peran']    = PERAN_SUPER;
    $_SESSION['admin_id'] = (int) $admin['id'];
    unset($_SESSION['pemilih_id'], $_SESSION['konteks_lembaga']);
    coba_reset();
    catat_aktivitas('masuk_super', 'Super Admin masuk: ' . $admin['nama']);
    flash('ok', 'Selamat datang, ' . $admin['nama'] . '.');
    redirect('super.php');
}

/* ============ 2. MASUK PANITIA / PEMILIH PADA SEBUAH LEMBAGA ============ */
$lembagaId = (int) ($_POST['lembaga'] ?? 0);
$lembaga   = lembaga_by_id($lembagaId);
if ($lembaga === false) {
    coba_tambah();
    flash('galat', 'Madrasah yang dituju tidak ditemukan. Pilih dari daftar di halaman depan.');
    redirect('index.php');
}
if ((int) $lembaga['aktif'] !== 1) {
    flash('kuning', 'Pemilihan di ' . $lembaga['nama'] . ' sedang tidak aktif.');
    redirect('index.php');
}
lembaga_pakai($lembagaId);

/* =========================== MASUK PEMILIH =========================== */
if ($peran === 'pemilih') {
    $nisn  = nisn_bersih($_POST['nisn'] ?? '');
    $token = (string) ($_POST['token'] ?? '');

    if ($nisn === '' || trim($token) === '') {
        coba_tambah();
        flash('galat', 'NISN/NIK dan token wajib diisi.');
        redirect('masuk.php?lembaga=' . $lembagaId);
    }

    $pemilih = satu('SELECT * FROM pemilih WHERE nisn = ? AND lembaga_id = ?', [$nisn, $lembagaId]);
    if ($pemilih === false) {
        coba_tambah();
        flash('galat', 'NISN/NIK tidak terdaftar pada Daftar Pemilih Tetap ' . $lembaga['nama'] . '. Hubungi panitia.');
        catat_aktivitas('masuk_gagal', 'NISN tidak terdaftar: ' . $nisn);
        redirect('masuk.php?lembaga=' . $lembagaId);
    }
    if (!token_cocok($token, (string) $pemilih['token'])) {
        coba_tambah();
        flash('galat', 'Token tidak sesuai. Periksa kembali atau minta token baru kepada panitia.');
        catat_aktivitas('masuk_gagal', 'Token salah untuk NISN: ' . $nisn);
        redirect('masuk.php?lembaga=' . $lembagaId);
    }
    if ((int) $pemilih['sudah'] === 1) {
        flash('kuning', 'NISN ini sudah menggunakan hak pilih pada '
            . tanggal_id((string) $pemilih['waktu'], false, true) . '. Setiap pemilih hanya boleh memilih satu kali.');
        catat_aktivitas('masuk_gagal', 'Token sudah dipakai: ' . $nisn);
        redirect('masuk.php?lembaga=' . $lembagaId);
    }

    /* Berhasil: segarkan id sesi (anti session fixation) lalu simpan identitas. */
    session_regenerate_id(true);
    $_SESSION['peran']      = PERAN_PEMILIH;
    $_SESSION['pemilih_id'] = (int) $pemilih['id'];
    unset($_SESSION['admin_id'], $_SESSION['konteks_lembaga']);
    coba_reset();
    catat_aktivitas('masuk_pemilih', 'Pemilih masuk: ' . $pemilih['nama'] . ' (' . $pemilih['kelas'] . ')');
    redirect('pilih.php');
}

/* =========================== MASUK PANITIA =========================== */
$username = trim((string) ($_POST['username'] ?? ''));
$sandi    = (string) ($_POST['sandi'] ?? '');

if ($username === '' || $sandi === '') {
    coba_tambah();
    flash('galat', 'Nama pengguna dan kata sandi wajib diisi.');
    redirect('masuk.php?lembaga=' . $lembagaId . '&tab=panitia');
}

$admin = satu("SELECT * FROM admin WHERE LOWER(username) = LOWER(?) AND peran = 'panitia' AND aktif = 1", [$username]);
if ($admin === false || !password_verify($sandi, (string) $admin['sandi'])) {
    coba_tambah();
    flash('galat', 'Nama pengguna atau kata sandi salah. Sisa percobaan: ' . coba_sisa() . ' kali.');
    catat_aktivitas('masuk_gagal', 'Percobaan masuk panitia gagal: ' . $username, $lembagaId);
    redirect('masuk.php?lembaga=' . $lembagaId . '&tab=panitia');
}
/* Akun ini milik lembaga lain -> tetap ditolak, dengan pesan yang menjelaskan. */
if ((int) $admin['lembaga_id'] !== $lembagaId) {
    coba_tambah();
    flash('galat', 'Akun panitia itu bukan milik ' . $lembaga['nama'] . '. Masuk melalui halaman madrasah yang benar.');
    catat_aktivitas('masuk_gagal', 'Panitia lembaga lain mencoba masuk: ' . $username, $lembagaId);
    redirect('masuk.php?lembaga=' . $lembagaId . '&tab=panitia');
}

session_regenerate_id(true);
$_SESSION['peran']    = PERAN_PANITIA;
$_SESSION['admin_id'] = (int) $admin['id'];
unset($_SESSION['pemilih_id'], $_SESSION['konteks_lembaga']);
coba_reset();
catat_aktivitas('masuk_panitia', 'Panitia masuk: ' . $admin['nama'], $lembagaId);
flash('ok', 'Selamat datang, ' . $admin['nama'] . '.');
redirect('admin.php');
