<?php
/**
 * api_hasil.php — endpoint JSON untuk penyegaran hasil (real-count).
 * ---------------------------------------------------------------------------
 * Selalu membalas JSON. Dipakai halaman hasil & panel panitia (polling).
 * Lembaga ditentukan dari parameter ?lembaga= (bila ada) atau dari konteks sesi.
 * Bila panitia menutup hasil untuk publik, endpoint ini hanya menjawab pengelola
 * lembaga itu sendiri dan Super Admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$minta   = (int) ($_GET['lembaga'] ?? 0);
$konteks = lembaga_id_aktif();
$id      = $minta > 0 ? $minta : $konteks;

if ($id <= 0 || lembaga_by_id($id) === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Lembaga tidak ditentukan.']);
    exit;
}

/* Boleh membaca: Super Admin; pengelola/pemilih lembaga itu; siapa pun bila
   hasil lembaga itu memang dibuka untuk publik. */
$boleh = is_super()
    || ($konteks === $id && (is_admin() || is_pemilih()))
    || hasil_publik($id);

if (!$boleh) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Hasil belum dibuka untuk publik.']);
    exit;
}

$h = hasil_lengkap($id);

echo json_encode([
    'ok'         => true,
    'lembaga_id' => $id,
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
