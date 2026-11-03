<?php
/**
 * api_hasil.php — endpoint JSON untuk penyegaran hasil (real-count).
 * ---------------------------------------------------------------------------
 * Selalu membalas JSON. Dipakai halaman hasil & panel panitia (polling).
 * Bila panitia menutup hasil untuk publik, endpoint ini hanya menjawab panitia.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!hasil_publik() && !is_admin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Hasil belum dibuka untuk publik.']);
    exit;
}

$h = hasil_lengkap();

echo json_encode([
    'ok'         => true,
    'dpt'        => $h['dpt'],
    'masuk'      => $h['masuk'],
    'belum'      => $h['belum'],
    'turnout'    => $h['turnout'],
    'sah'        => $h['sah'],
    'diperbarui' => $h['diperbarui'],
    /* Kepemilikan/label pemenang dihitung di peramban dari daftar ini. */
    'paslon'     => array_map(static fn (array $p): array => [
        'id'     => $p['id'],
        'nomor'  => $p['nomor'],
        'nama'   => $p['ketua'] . ' & ' . $p['wakil'],
        'suara'  => $p['suara'],
        'persen' => $p['persen'],
        'warna'  => $p['warna'],
    ], $h['paslon']),
], JSON_UNESCAPED_UNICODE);
