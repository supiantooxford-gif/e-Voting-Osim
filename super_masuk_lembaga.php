<?php
/**
 * super_masuk_lembaga.php — Super Admin memilih lembaga yang akan dikelola.
 * ---------------------------------------------------------------------------
 * Super Admin melihat seluruh lembaga, tetapi panel pengelolaan (paslon, DPT,
 * hasil, reset) SELALU bekerja pada satu lembaga. Endpoint ini menetapkan
 * lembaga itu di sesi, lalu mengalihkan ke halaman tujuan yang diizinkan.
 *
 * Hanya POST + token CSRF: menetapkan konteks lewat tautan GET akan membuat
 * lembaga yang sedang dikelola bisa berubah karena gambar/tautan luar.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

require_super();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('super.php');
}
csrf_periksa();

$aksi = (string) ($_POST['aksi'] ?? 'masuk');

if ($aksi === 'keluar') {
    $nama = '';
    $l = lembaga_aktif();
    if ($l !== false) {
        $nama = (string) $l['nama'];
    }
    lembaga_konteks_hapus();
    catat_aktivitas('super_keluar_lembaga', 'Super Admin keluar dari konteks lembaga ' . $nama);
    redirect('super.php');
}

/* Halaman tujuan yang diizinkan setelah memilih lembaga. */
$tujuanBoleh = [
    'admin.php', 'admin_paslon.php', 'admin_dpt.php', 'admin_pengaturan.php',
    'admin_reset.php', 'hasil.php', 'cetak_kartu_pemilih.php',
];
$tujuan = basename((string) ($_POST['tujuan'] ?? 'admin.php'));
if (!in_array($tujuan, $tujuanBoleh, true)) {
    $tujuan = 'admin.php';
}

$lembagaId = (int) ($_POST['lembaga'] ?? 0);
$lembaga   = lembaga_by_id($lembagaId);
if ($lembaga === false) {
    flash('galat', 'Madrasah itu tidak ditemukan.');
    redirect('super.php');
}
if ((int) $lembaga['aktif'] !== 1) {
    flash('kuning', 'Lembaga "' . $lembaga['nama'] . '" sedang nonaktif. Aktifkan dulu bila ingin mengelolanya.');
    redirect('super_lembaga.php');
}

lembaga_konteks_set($lembagaId);
catat_aktivitas('super_masuk_lembaga', 'Super Admin mengelola lembaga ' . $lembaga['nama'], $lembagaId);

redirect($tujuan);
