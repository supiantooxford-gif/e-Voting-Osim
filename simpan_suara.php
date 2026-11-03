<?php
/**
 * simpan_suara.php — menyimpan surat suara (POST saja).
 * ---------------------------------------------------------------------------
 * Keutuhan data dijaga oleh satu transaksi `BEGIN IMMEDIATE`:
 *   1. Tandai pemilih `sudah = 1` HANYA bila nilainya masih 0.
 *      Bila jumlah baris yang berubah 0 → pemilih itu sudah pernah memilih
 *      (mis. menekan tombol dua kali) dan suaranya TIDAK ditambahkan.
 *   2. Simpan satu baris di tabel `suara` (tanpa identitas pemilih → rahasia).
 * Dengan begitu jumlah surat suara selalu sama dengan jumlah pemilih yang
 * bertanda "sudah memilih", walaupun tombol ditekan bersamaan dari dua HP.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

require_pemilih();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pilih.php');
}
csrf_periksa();

$paslonId = (int) ($_POST['paslon_id'] ?? 0);
$paslon   = satu('SELECT * FROM paslon WHERE id = ? AND aktif = 1', [$paslonId]);
if ($paslon === false) {
    flash('galat', 'Pasangan calon yang Anda pilih tidak tersedia. Silakan pilih ulang.');
    redirect('pilih.php');
}

$pemilih = pemilih_kini();
$pdo     = pdo();
$berhasil = false;

try {
    $pdo->exec('BEGIN IMMEDIATE');
    $ubah = jalankan(
        'UPDATE pemilih SET sudah = 1, waktu = ? WHERE id = ? AND sudah = 0',
        [date('Y-m-d H:i:s'), (int) $pemilih['id']]
    );

    if ($ubah === 1) {
        q('INSERT INTO suara (paslon_id, dibuat) VALUES (?, ?)', [$paslonId, date('Y-m-d H:i:s')]);
        $pdo->exec('COMMIT');
        $berhasil = true;
    } else {
        $pdo->exec('ROLLBACK');
    }
} catch (Throwable $e) {
    try {
        $pdo->exec('ROLLBACK');
    } catch (Throwable $abaikan) {
    }
    flash('galat', 'Terjadi gangguan saat menyimpan suara. Silakan ulangi atau lapor panitia.');
    redirect('pilih.php');
}

if (!$berhasil) {
    unset($_SESSION['pemilih_id'], $_SESSION['peran']);
    flash('kuning', 'Suara Anda sudah tercatat sebelumnya. Setiap pemilih hanya boleh memilih satu kali.');
    catat_aktivitas('suara_ganda', 'Percobaan memilih dua kali ditolak');
    redirect('index.php');
}

/* Jejak aktivitas TIDAK memuat pilihan maupun identitas pemilih. */
catat_aktivitas('suara_masuk', 'Satu surat suara diterima (anonim)');

/* Pemilih langsung dianggap selesai; halaman sukses menampilkan notifikasi. */
$_SESSION['sukses_suara']  = 1;
$_SESSION['sukses_waktu']  = date('Y-m-d H:i:s');
flash('ok', 'Suara Anda berhasil direkam. Terima kasih sudah menggunakan hak pilih!');
redirect('sukses.php');
