<?php
/**
 * inc/init.php — titik masuk semua halaman.
 * ---------------------------------------------------------------------------
 * Urutannya: konstanta -> fungsi bantu -> basis data -> folder & sesi -> skema.
 */
declare(strict_types=1);

/* Penanda aplikasi: berkas di folder inc/ hanya boleh dijalankan lewat
   inc/init.php, bukan diminta langsung dari peramban. */
define('EVOTING_APP', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/dpt.php';

siapkan_folder();
mulai_sesi();

/* Membuka basis data + memastikan skema & data awal tersedia. */
try {
    pdo();
    ensure_schema();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Basis data belum siap</title>'
        . '<p style="font-family:sans-serif;padding:2rem">Basis data belum dapat dibuka. '
        . 'Pastikan folder <code>data/</code> dapat ditulis oleh server, lalu muat ulang halaman.</p>';
    exit;
}
