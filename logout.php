<?php
/**
 * logout.php — mengakhiri sesi (dipakai panitia maupun pemilih).
 * ?selesai=1 dipakai setelah pemilih menyelesaikan suara.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$selesai = ($_GET['selesai'] ?? '') === '1';

if (is_pemilih()) {
    $p = pemilih_kini();
    catat_aktivitas('keluar_pemilih', $p !== false ? 'Pemilih keluar: ' . $p['nama'] : 'Pemilih keluar');
} elseif (is_admin()) {
    $a = admin_kini();
    catat_aktivitas('keluar_panitia', $a !== false ? 'Panitia keluar: ' . $a['nama'] : 'Panitia keluar');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();

/* Sesi baru hanya untuk membawa pesan flash. */
mulai_sesi();
session_regenerate_id(true);
flash($selesai ? 'ok' : 'info', $selesai
    ? 'Terima kasih! Suara Anda sudah tersimpan dan tercatat. Silakan berikan perangkat kepada pemilih berikutnya.'
    : 'Anda sudah keluar dari aplikasi.');

redirect('index.php');
