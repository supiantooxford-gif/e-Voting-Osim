<?php
/**
 * admin_reset.php — RESET DATA (khusus panitia, dengan konfirmasi keamanan).
 * ---------------------------------------------------------------------------
 * Tiga pilihan:
 *   1. Kosongkan perolehan suara  → surat suara dihapus & semua pemilih kembali
 *      "belum memilih" (dipakai untuk simulasi/gladi bersih sebelum hari-H).
 *   2. Hapus seluruh data         → paslon, pemilih, dan suara dikosongkan
 *      supaya panitia dapat mengisi dari awal.
 *   3. Muat ulang data contoh     → mengembalikan 3 paslon + 20 pemilih contoh.
 * Semua tindakan WAJIB mengetik kata kunci HAPUS dan berjalan dalam satu
 * transaksi, sehingga tidak pernah berhenti di tengah jalan.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $jenis  = (string) ($_POST['jenis'] ?? '');
    $ketik  = strtoupper(trim((string) ($_POST['konfirmasi'] ?? '')));
    $pilihan = [
        'suara'  => 'Kosongkan perolehan suara',
        'semua'  => 'Hapus seluruh data pemilihan',
        'contoh' => 'Muat ulang data contoh',
    ];

    if (!isset($pilihan[$jenis])) {
        $galat[] = 'Jenis reset tidak dikenal.';
    } elseif ($ketik !== 'HAPUS') {
        $galat[] = 'Ketik HAPUS (huruf besar) pada kolom konfirmasi untuk melanjutkan.';
    } else {
        $pdo = pdo();
        try {
            $pdo->exec('BEGIN IMMEDIATE');
            if ($jenis === 'suara') {
                jalankan('DELETE FROM suara');
                jalankan('UPDATE pemilih SET sudah = 0, waktu = NULL');
            } elseif ($jenis === 'semua') {
                jalankan('DELETE FROM suara');
                jalankan('DELETE FROM pemilih');
                jalankan('DELETE FROM paslon');
            } else { /* contoh */
                jalankan('DELETE FROM suara');
                jalankan('DELETE FROM pemilih');
                jalankan('DELETE FROM paslon');
                /* Tanda "sudah diisi contoh" dilepas agar data contoh dibuat ulang. */
                jalankan("DELETE FROM pengaturan WHERE kunci = 'seed_contoh'");
                db_isi_contoh($pdo);
            }
            $pdo->exec('COMMIT');
            catat_aktivitas('reset_data', $pilihan[$jenis]);
            flash('ok', $pilihan[$jenis] . ' berhasil dijalankan.');
        } catch (Throwable $e) {
            try { $pdo->exec('ROLLBACK'); } catch (Throwable $abaikan) {}
            $galat[] = 'Reset gagal dijalankan. Tidak ada data yang berubah.';
        }
        redirect('admin_reset.php');
    }
}

$stat   = statistik();
$integ  = integritas_data();
$jPaslon = (int) nilai('SELECT COUNT(*) FROM paslon');

page_top('Reset Data', [
    'shell' => 'admin',
    'sub'   => 'Kosongkan perolehan suara atau seluruh data pemilihan',
    'aksi'  => '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Papan Hasil</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk mb-4">
    <?= ikon('info') ?>
    <div>
      <?php foreach ($galat as $g): ?><p class="font-bold"><?= e($g) ?></p><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Keadaan data saat ini -->
<section class="grid gap-4 sm:grid-cols-4">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('paslon') ?></span>
    <div><p class="stat-label">Pasangan Calon</p><p class="stat-angka"><?= e(angka($jPaslon)) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
    <div><p class="stat-label">Pemilih</p><p class="stat-angka"><?= e(angka($stat['dpt'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
    <div><p class="stat-label">Surat Suara</p><p class="stat-angka text-emerald-700"><?= e(angka($integ['suara'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-amber-100 text-amber-700"><?= ikon('jam') ?></span>
    <div><p class="stat-label">Belum Memilih</p><p class="stat-angka text-amber-700"><?= e(angka($stat['belum'])) ?></p></div>
  </div>
</section>

<div class="mt-6 notis-kuning">
  <?= ikon('info') ?>
  <p><b>Perhatian.</b> Tindakan pada halaman ini tidak dapat dibatalkan. Gunakan hanya sebelum pemungutan suara
    dimulai, saat gladi bersih, atau setelah pemungutan selesai dan berita acara sudah dicetak.</p>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-3">

  <!-- 1. Kosongkan perolehan suara -->
  <section class="kartu kartu-pad flex flex-col">
    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-amber-100 text-amber-700"><?= ikon('reset') ?></span>
    <h2 class="kartu-judul mt-3">Kosongkan Perolehan Suara</h2>
    <p class="kartu-ket mt-1">Menghapus seluruh surat suara dan mengembalikan <b>semua pemilih</b> ke status
      &ldquo;belum memilih&rdquo;, sehingga mereka dapat memilih lagi.</p>
    <ul class="mt-3 space-y-1.5 text-xs text-slate-600">
      <li>&bull; Paslon tetap ada (tidak dihapus).</li>
      <li>&bull; DPT tetap lengkap dengan token yang sama.</li>
      <li>&bull; Papan hasil kembali ke 0 suara.</li>
    </ul>
    <form method="post" action="admin_reset.php" class="mt-4 space-y-3" perolehan suara? Seluruh surat suara akan dihapus dan semua pemilih kembali berstatus belum memilih.">
      <?= csrf_field() ?>
      <input type="hidden" name="jenis" value="suara">
      <div>
        <label class="label" for="konfirmasi1">Ketik <b>HAPUS</b> untuk melanjutkan</label>
        <input class="isian uppercase" type="text" id="konfirmasi1" name="konfirmasi" required placeholder="HAPUS">
      </div>
      <button type="submit" class="tombol-bahaya w-full"><?= ikon('reset', 'h-4 w-4') ?> Kosongkan Suara</button>
    </form>
  </section>

  <!-- 2. Hapus seluruh data -->
  <section class="kartu kartu-pad flex flex-col">
    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-100 text-rose-700"><?= ikon('hapus') ?></span>
    <h2 class="kartu-judul mt-3">Hapus Seluruh Data Pemilihan</h2>
    <p class="kartu-ket mt-1">Mengosongkan paslon, DPT, dan surat suara sehingga panitia dapat mengisi
      semuanya dari awal (tanpa data contoh).</p>
    <ul class="mt-3 space-y-1.5 text-xs text-slate-600">
      <li>&bull; Akun panitia &amp; pengaturan tetap dipertahankan.</li>
      <li>&bull; Semua token lama tidak berlaku lagi.</li>
      <li>&bull; Cocok untuk menyiapkan pemilihan periode baru.</li>
    </ul>
    <form method="post" action="admin_reset.php" class="mt-4 space-y-3" data pemilihan (paslon, pemilih, dan suara)? Tindakan ini tidak dapat dibatalkan.">
      <?= csrf_field() ?>
      <input type="hidden" name="jenis" value="semua">
      <div>
        <label class="label" for="konfirmasi2">Ketik <b>HAPUS</b> untuk melanjutkan</label>
        <input class="isian uppercase" type="text" id="konfirmasi2" name="konfirmasi" required placeholder="HAPUS">
      </div>
      <button type="submit" class="tombol-bahaya w-full"><?= ikon('hapus', 'h-4 w-4') ?> Hapus Semua Data</button>
    </form>
  </section>

  <!-- 3. Muat ulang data contoh -->
  <section class="kartu kartu-pad flex flex-col">
    <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brand-100 text-brand-700"><?= ikon('tambah') ?></span>
    <h2 class="kartu-judul mt-3">Muat Ulang Data Contoh</h2>
    <p class="kartu-ket mt-1">Mengembalikan aplikasi ke keadaan awal: <b>3 pasangan calon</b> dan
      <b>20 pemilih contoh</b> beserta token barunya.</p>
    <ul class="mt-3 space-y-1.5 text-xs text-slate-600">
      <li>&bull; Berguna untuk uji coba atau pelatihan panitia.</li>
      <li>&bull; Seluruh data yang ada saat ini akan digantikan.</li>
      <li>&bull; Token contoh tercatat pada menu DPT Pemilih.</li>
    </ul>
    <form method="post" action="admin_reset.php" class="mt-4 space-y-3" data contoh? Semua paslon, pemilih, dan suara yang ada saat ini akan digantikan data contoh.">
      <?= csrf_field() ?>
      <input type="hidden" name="jenis" value="contoh">
      <div>
        <label class="label" for="konfirmasi3">Ketik <b>HAPUS</b> untuk melanjutkan</label>
        <input class="isian uppercase" type="text" id="konfirmasi3" name="konfirmasi" required placeholder="HAPUS">
      </div>
      <button type="submit" class="tombol-utama w-full"><?= ikon('reset', 'h-4 w-4') ?> Muat Data Contoh</button>
    </form>
  </section>
</div>

<section class="kartu kartu-pad mt-5">
  <h2 class="kartu-judul">Sebelum melakukan reset</h2>
  <div class="mt-3 grid gap-3 sm:grid-cols-3">
    <?php foreach ([
        ['Pastikan tidak ada pemilih di bilik suara', 'Muat ulang halaman bilik suara pada perangkat lain setelah reset.'],
        ['Sudah ada salinan hasil', 'Gunakan papan hasil & Mode Layar untuk mencatat/memotret hasil akhir.'],
        ['Sudah ada berita acara', 'Cetak atau catat hasil akhir sebelum data dikosongkan untuk periode berikutnya.'],
    ] as $k): ?>
      <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="text-sm font-bold text-slate-800"><?= e($k[0]) ?></p>
        <p class="mt-1 text-xs text-slate-500"><?= e($k[1]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php page_bottom(['shell' => 'admin']); ?>
