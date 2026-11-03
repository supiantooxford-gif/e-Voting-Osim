<?php
/**
 * sukses.php — halaman setelah suara tersimpan.
 * ---------------------------------------------------------------------------
 * Menampilkan notifikasi sukses (toast + animasi), lalu otomatis mengeluarkan
 * pemilih supaya perangkat siap dipakai pemilih berikutnya (bilik suara).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

if (empty($_SESSION['sukses_suara'])) {
    redirect('index.php');
}

/* Ambil nama pemilih untuk ucapan, lalu AKHIRI sesi memilihnya. */
$pemilih = pemilih_kini();
$nama    = $pemilih !== false ? (string) $pemilih['nama'] : 'Pemilih';
unset($_SESSION['sukses_suara'], $_SESSION['sukses_waktu'], $_SESSION['pemilih_id'], $_SESSION['peran']);

$stat = statistik();

page_top('Suara Berhasil Dikirim', ['shell' => 'publik', 'aksi' =>
    '<a href="index.php" class="tombol-putih tombol-kecil">' . ikon('dashboard', 'h-4 w-4') . ' Ke Halaman Masuk</a>']);
?>

<div class="mx-auto max-w-2xl">
  <section class="kartu kartu-pad animate-pop text-center">
    <div class="relative mx-auto grid h-24 w-24 place-items-center">
      <span class="absolute inset-0 rounded-full bg-emerald-400/40 animate-denyut"></span>
      <span class="relative grid h-24 w-24 place-items-center rounded-full bg-emerald-500 text-white shadow-naik">
        <?= ikon('cek', 'h-12 w-12') ?>
      </span>
    </div>

    <h1 class="mt-6 text-2xl font-black text-slate-900 sm:text-3xl">Suara Anda Berhasil Direkam!</h1>
    <p class="mt-2 text-sm text-slate-600">
      Terima kasih, <b><?= e($nama) ?></b>. Partisipasi Anda menentukan arah OSIS ke depan.
    </p>

    <div class="mt-6 grid gap-3 text-left sm:grid-cols-3">
      <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="stat-label">Waktu</p>
        <p class="mt-1 text-sm font-bold text-slate-800"><?= e(date('H:i') . ' ' . LABEL_ZONA) ?></p>
      </div>
      <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="stat-label">Suara masuk</p>
        <p class="mt-1 text-sm font-bold text-slate-800"><?= e(angka($stat['masuk'])) ?> suara</p>
      </div>
      <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
        <p class="stat-label text-emerald-700">Partisipasi</p>
        <p class="mt-1 text-sm font-bold text-emerald-800"><?= e(persen($stat['turnout'])) ?></p>
      </div>
    </div>

    <p class="mt-6 rounded-2xl bg-brand-50 p-4 text-xs leading-relaxed text-brand-800 ring-1 ring-brand-100">
      Pilihan Anda <b>tidak tercatat atas nama Anda</b> &mdash; sistem hanya menyimpan bahwa Anda sudah memilih.
      Silakan serahkan perangkat ini kepada pemilih berikutnya.
    </p>

    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
      <a href="logout.php?selesai=1" class="tombol-utama flex-1 py-3 text-base" data-keluar-sekarang>
        <?= ikon('keluar', 'h-5 w-5') ?> Selesai &amp; Keluar
      </a>
      <?php if (hasil_publik()): ?>
        <a href="hasil.php" class="tombol-ok flex-1 py-3 text-base">
          <?= ikon('chart', 'h-5 w-5') ?> Lihat Hasil Sementara
        </a>
      <?php endif; ?>
    </div>

    <p class="mt-4 text-xs font-semibold text-slate-500" data-hitung-mundur-keluar data-detik="8">
      Halaman ini menutup sesi Anda otomatis dalam <span data-sisa>8</span> detik&hellip;
    </p>
  </section>
</div>

<?php page_bottom(['shell' => 'publik']); ?>
