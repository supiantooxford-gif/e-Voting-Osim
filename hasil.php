<?php
/**
 * hasil.php — DASHBOARD HASIL REAL-COUNT (panel panitia & publik).
 * ---------------------------------------------------------------------------
 * Angka & grafik disegarkan otomatis lewat api_hasil.php (tanpa memuat ulang
 * halaman), sehingga papan hasil dapat dipantau berjalan.
 * Akses publik diatur panitia lewat Pengaturan ("Tampilkan hasil ke publik").
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

$publik  = hasil_publik();
$admin   = is_admin();
$layar   = ($_GET['layar'] ?? '') === '1';

/* Hasil disembunyikan → hanya panitia yang boleh melihat. */
if (!$publik && !$admin) {
    page_top('Hasil Belum Dibuka', ['shell' => 'publik']);
    ?>
    <div class="mx-auto max-w-xl">
      <div class="kartu kartu-pad text-center">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-amber-100 text-amber-600"><?= ikon('kunci', 'h-8 w-8') ?></span>
        <h1 class="mt-4 text-xl font-black text-slate-900">Hasil pemilihan belum dibuka</h1>
        <p class="mt-2 text-sm text-slate-600">
          Panitia OSIS belum membuka papan hasil untuk publik. Silakan kembali lagi setelah pemungutan suara selesai.
        </p>
        <div class="mt-5 flex flex-col gap-3 sm:flex-row">
          <a href="index.php" class="tombol-putih flex-1">Kembali ke halaman masuk</a>
          <a href="index.php?tab=panitia" class="tombol-utama flex-1">Masuk sebagai panitia</a>
        </div>
      </div>
    </div>
    <?php
    page_bottom(['shell' => 'publik']);
    exit;
}

$h      = hasil_lengkap();
$paslon = $h['paslon'];

/* Data untuk grafik & penyegaran (dipakai assets/app.js). */
$dataJs = [
    'dpt'     => $h['dpt'],
    'masuk'   => $h['masuk'],
    'belum'   => $h['belum'],
    'turnout' => $h['turnout'],
    'sah'     => $h['sah'],
    'diperbarui' => $h['diperbarui'],
    'paslon'  => array_map(static fn (array $p): array => [
        'id' => $p['id'], 'nomor' => $p['nomor'], 'nama' => $p['ketua'] . ' & ' . $p['wakil'],
        'suara' => $p['suara'], 'persen' => $p['persen'], 'warna' => $p['warna'],
    ], $paslon),
];

$aksiKanan = '';
if ($admin) {
    $aksiKanan = '<a href="admin_reset.php" class="tombol-putih tombol-kecil">' . ikon('reset', 'h-4 w-4') . ' Reset Data</a>';
    if (!$layar) {
        $aksiKanan .= '<a href="?layar=1" class="tombol-utama tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Mode Layar</a>';
    } else {
        $aksiKanan .= '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('tutup', 'h-4 w-4') . ' Keluar Mode Layar</a>';
    }
} elseif (!$layar) {
    $aksiKanan = '<a href="?layar=1" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Mode Layar</a>';
}

$shell = $layar ? 'polos' : ($admin ? 'admin' : 'publik');
page_top('Hasil Real-Count', [
    'shell' => $shell,
    'sub'   => 'Pemungutan suara ' . setting('periode'),
    'aksi'  => $aksiKanan,
]);
?>

<?php if ($layar): ?>
  <header class="bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 px-6 py-5 text-white">
    <div class="mx-auto flex max-w-[90rem] flex-wrap items-center gap-4">
      <div class="min-w-0 flex-1">
        <p class="text-xs font-bold uppercase tracking-[.3em] text-brand-200">Hasil Sementara</p>
        <h1 class="text-2xl font-black sm:text-3xl"><?= e(setting('nama_pemilihan')) ?></h1>
        <p class="text-sm text-brand-200"><?= e(setting('nama_lembaga')) ?> &middot; <?= e(setting('periode')) ?></p>
      </div>
      <div class="text-right">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-200">Partisipasi</p>
        <p class="text-3xl font-black text-emerald-300" data-angka="turnout"><?= e(persen($h['turnout'])) ?></p>
      </div>
    </div>
  </header>
<?php endif; ?>

<!-- Wrapper penyegaran otomatis (dibaca oleh skrip aplikasi) -->
<div data-hasil data-api="api_hasil.php" data-jeda="<?= (int) HASIL_POLLING ?>" class="space-y-6 <?= $layar ? 'mx-auto max-w-[90rem] px-4 py-6 sm:px-6' : 'mt-1' ?>">

  <?php if (!$layar): ?>
  <section class="animate-masuk overflow-hidden rounded-3xl bg-gradient-to-r from-brand-800 via-brand-900 to-brand-800 p-6 text-white shadow-naik sm:p-8">
    <div class="flex flex-wrap items-center gap-4">
      <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/25"><?= ikon('chart', 'h-7 w-7') ?></span>
      <div class="min-w-0 flex-1">
        <h2 class="text-xl font-black sm:text-2xl">Papan Hasil Pemilihan</h2>
        <p class="mt-1 text-sm text-brand-100">
          <?= $h['belum'] > 0
              ? 'Pemungutan suara masih berlangsung — hasil di bawah diperbarui otomatis.'
              : 'Seluruh pemilih terdaftar sudah memberikan suaranya.' ?>
        </p>
      </div>
      <span class="flex items-center gap-2 rounded-2xl bg-white/10 px-4 py-2.5 text-xs font-bold ring-1 ring-white/15">
        <span class="relative flex h-2.5 w-2.5">
          <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 animate-denyut"></span>
          <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
        </span>
        <span data-hasil-status>LIVE</span>
        <span class="font-medium text-brand-200" data-hasil-jam><?= e(date('H:i:s')) ?> <?= e(LABEL_ZONA) ?></span>
      </span>
    </div>
  </section>
  <?php endif; ?>

  <!-- ======================= KARTU STATISTIK UTAMA ======================= -->
  <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik pemilihan">
    <div class="stat-kartu">
      <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
      <div class="min-w-0">
        <p class="stat-label">Pemilih Terdaftar</p>
        <p class="stat-angka" data-angka="dpt"><?= e(angka($h['dpt'])) ?></p>
      </div>
    </div>
    <div class="stat-kartu">
      <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
      <div class="min-w-0">
        <p class="stat-label">Suara Masuk</p>
        <p class="stat-angka" data-angka="masuk"><?= e(angka($h['masuk'])) ?></p>
      </div>
    </div>
    <div class="stat-kartu">
      <span class="stat-ikon bg-amber-100 text-amber-700"><?= ikon('jam') ?></span>
      <div class="min-w-0">
        <p class="stat-label">Belum Memilih</p>
        <p class="stat-angka" data-angka="belum"><?= e(angka($h['belum'])) ?></p>
      </div>
    </div>
    <div class="stat-kartu ring-2 ring-emerald-200">
      <span class="stat-ikon bg-emerald-500 text-white"><?= ikon('trophy') ?></span>
      <div class="min-w-0 flex-1">
        <p class="stat-label">Tingkat Partisipasi</p>
        <p class="stat-angka text-emerald-700" data-angka="turnout"><?= e(persen($h['turnout'])) ?></p>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-emerald-500 transition-[width] duration-700" data-bar="turnout"
               style="width: <?= e((string) min(100, $h['turnout'])) ?>%"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================= GRAFIK (DONAT & BATANG) ========================= -->
  <!-- Dua kolom grafik: tingginya dijaga ringkas (jangan memanjang ke bawah)
       dengan items-start supaya kartu tidak saling meregangkan. -->
  <section class="grid items-start gap-5 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">
    <div class="kartu kartu-pad">
      <div class="flex items-center justify-between gap-3">
        <h2 class="kartu-judul">Persentase Perolehan Suara</h2>
        <?= ikon('chart', 'h-5 w-5 text-slate-400') ?>
      </div>
      <?php if ($paslon): ?>
        <div class="relative mx-auto mt-4 h-[13rem] w-full max-w-[15rem] sm:h-[14rem] sm:max-w-[16rem]">
          <canvas id="chDonat" aria-label="Diagram lingkaran perolehan suara" role="img"></canvas>
        </div>
        <ul class="mt-4 space-y-2">
          <?php foreach ($paslon as $p): ?>
            <li class="flex items-center gap-2.5 text-sm" data-legenda="<?= (int) $p['id'] ?>">
              <span class="h-3 w-3 shrink-0 rounded-full" style="background: <?= e($p['warna']) ?>"></span>
              <span class="truncate font-semibold text-slate-700">Paslon <?= e((string) $p['nomor']) ?></span>
              <span class="ml-auto font-bold" data-legenda-persen><?= e(persen($p['persen'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="mt-4 text-sm text-slate-500">Belum ada pasangan calon yang terdaftar.</p>
      <?php endif; ?>
    </div>

    <div class="space-y-5">
      <!-- Pemimpin sementara (satu baris ringkas) -->
      <div class="kartu kartu-pad animate-masuk">
        <h2 class="kartu-judul">Perolehan Suara Terbanyak</h2>
        <?php if ($h['teratas'] !== null): ?>
          <div class="mt-3 flex flex-wrap items-center gap-4 rounded-2xl bg-gradient-to-r from-emerald-50 to-brand-50 p-4 ring-1 ring-emerald-200">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-emerald-600 text-xl font-black text-white">
              <?= e((string) $h['teratas']['nomor']) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="text-xs font-bold uppercase tracking-wide text-emerald-700" data-pemenang-label>Perolehan tertinggi sementara</p>
              <p class="truncate text-lg font-black text-slate-900" data-pemenang-nama>
                <?= e($h['teratas']['ketua'] . ' & ' . $h['teratas']['wakil']) ?>
              </p>
            </div>
            <div class="text-right">
              <p class="text-2xl font-black text-emerald-700" data-pemenang-suara><?= e(angka($h['teratas']['suara'])) ?></p>
              <p class="text-xs font-semibold text-slate-500">suara</p>
            </div>
          </div>
        <?php else: ?>
          <p class="mt-3 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
            Belum ada surat suara yang masuk.
          </p>
        <?php endif; ?>
      </div>

      <!-- Diagram batang -->
      <div class="kartu kartu-pad">
        <div class="flex items-center justify-between gap-3">
          <h2 class="kartu-judul">Jumlah Suara per Pasangan Calon</h2>
          <?= ikon('dashboard', 'h-5 w-5 text-slate-400') ?>
        </div>
        <?php
        /* Tinggi diagram batang dibatasi: cukup untuk semua batang + sumbu,
           tidak boleh memanjang ke bawah (Chart.js bawaan memakai rasio 1:2
           sehingga tinggi ikut lebar kartu dan jadi sangat panjang). */
        $tinggiBatang = min(300, max(132, 24 + (count($paslon) * 40)));
        ?>
        <div class="mt-4" style="height: <?= (int) $tinggiBatang ?>px">
          <canvas id="chBatang" aria-label="Diagram batang perolehan suara" role="img"></canvas>
        </div>
      </div>
    </div>
  </section>

  <!-- ========================= RINCIAN TIAP PASLON ========================= -->
  <section class="kartu kartu-pad">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="kartu-judul">Rincian Perolehan Suara</h2>
      <p class="text-xs font-semibold text-slate-500">
        Total suara sah: <b data-angka="sah"><?= e(angka($h['sah'])) ?></b>
        &middot; Diperbarui <span data-hasil-jam2><?= e(date('H:i:s')) ?> <?= e(LABEL_ZONA) ?></span>
      </p>
    </div>

    <div class="mt-4 space-y-3">
      <?php if (!$paslon): ?>
        <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">Belum ada data pasangan calon.</p>
      <?php endif; ?>
      <?php foreach ($paslon as $p): ?>
        <article class="baris-hasil" data-baris="<?= (int) $p['id'] ?>">
          <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-base font-black text-white"
                style="background: <?= e($p['warna']) ?>">
            <?= e((string) $p['nomor']) ?>
          </span>
          <?php if ($p['foto'] !== null): ?>
            <img src="<?= e($p['foto']) ?>" alt="Foto paslon <?= e((string) $p['nomor']) ?>"
                 class="hidden h-12 w-12 shrink-0 rounded-xl object-cover ring-1 ring-slate-200 sm:block" onerror="this.classList.add('hidden')">
          <?php endif; ?>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-slate-800">
              <?= e($p['ketua']) ?> &amp; <?= e($p['wakil']) ?>
              <?php if ($p['aktif'] !== 1): ?><span class="pill-abu ml-1">nonaktif</span><?php endif; ?>
            </p>
            <div class="bar-luar mt-2">
              <div class="bar-dalam" data-bar-persen="<?= (int) $p['id'] ?>"
                   style="width: <?= e((string) $p['persen']) ?>%; background: <?= e($p['warna']) ?>"></div>
            </div>
          </div>
          <div class="w-24 shrink-0 text-right sm:w-28">
            <p class="text-lg font-black text-slate-900" data-persen="<?= (int) $p['id'] ?>"><?= e(persen($p['persen'])) ?></p>
            <p class="text-xs font-semibold text-slate-500"><span data-suara="<?= (int) $p['id'] ?>"><?= e(angka($p['suara'])) ?></span> suara</p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <p class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 text-xs text-slate-500 ring-1 ring-slate-200">
      Persentase dihitung dari <b>total suara sah</b>. Papan ini menyegarkan diri setiap
      <?= (int) HASIL_POLLING ?> detik tanpa memuat ulang halaman.
    </p>
  </section>
</div>

<script type="application/json" id="dataHasil"><?= json_encode($dataJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php page_bottom(['shell' => $shell]); ?>
