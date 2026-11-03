<?php
/**
 * admin.php — DASHBOARD PANEL PANITIA.
 * ---------------------------------------------------------------------------
 * Ringkasan cepat: statistik, keutuhan data, perolehan sementara, jejak
 * aktivitas terakhir, dan pintasan ke menu pengelolaan.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

$h      = hasil_lengkap();
$integ  = integritas_data();
$paslon = paslon_list(true);
$jejak  = semua('SELECT * FROM aktivitas ORDER BY id DESC LIMIT 8');

page_top('Dashboard Panitia', [
    'shell' => 'admin',
    'sub'   => setting('nama_pemilihan') . ' &middot; ' . setting('periode'),
    'aksi'  => '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Papan Hasil</a>'
        . '<a href="admin_dpt.php" class="tombol-utama tombol-kecil">' . ikon('dpt', 'h-4 w-4') . ' Kelola DPT</a>',
]);
?>

<!-- ============================== STATISTIK ============================== -->
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik pemilihan">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
    <div><p class="stat-label">Pemilih Terdaftar</p><p class="stat-angka"><?= e(angka($h['dpt'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
    <div><p class="stat-label">Sudah Memilih</p><p class="stat-angka text-emerald-700"><?= e(angka($h['masuk'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-amber-100 text-amber-700"><?= ikon('jam') ?></span>
    <div><p class="stat-label">Belum Memilih</p><p class="stat-angka text-amber-700"><?= e(angka($h['belum'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-900 text-white"><?= ikon('trophy') ?></span>
    <div class="min-w-0 flex-1">
      <p class="stat-label">Partisipasi</p>
      <p class="stat-angka"><?= e(persen($h['turnout'])) ?></p>
      <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-brand-600" style="width: <?= e((string) min(100, $h['turnout'])) ?>%"></div>
      </div>
    </div>
  </div>
</section>

<div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
  <div class="space-y-5">

    <!-- ======================= PEROLEHAN SEMENTARA ======================= -->
    <section class="kartu kartu-pad">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="kartu-judul">Perolehan Suara Sementara</h2>
        <span class="pill-abu"><?= ikon('jam', 'h-3.5 w-3.5') ?> Diperbarui <?= e(date('H:i:s')) ?> <?= e(LABEL_ZONA) ?></span>
      </div>

      <div class="mt-4 space-y-3">
        <?php if (!$h['paslon']): ?>
          <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
            Belum ada pasangan calon. Tambahkan lewat menu <a class="font-bold text-brand-700 underline" href="admin_paslon.php">Pasangan Calon</a>.
          </p>
        <?php endif; ?>
        <?php foreach ($h['paslon'] as $p): ?>
          <div class="baris-hasil">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-sm font-black text-white" style="background: <?= e($p['warna']) ?>">
              <?= e((string) $p['nomor']) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800"><?= e($p['ketua']) ?> &amp; <?= e($p['wakil']) ?></p>
              <div class="bar-luar mt-1.5">
                <div class="bar-dalam" style="width: <?= e((string) $p['persen']) ?>%; background: <?= e($p['warna']) ?>"></div>
              </div>
            </div>
            <div class="w-20 shrink-0 text-right">
              <p class="text-base font-black text-slate-900"><?= e(angka($p['suara'])) ?></p>
              <p class="text-xs font-semibold text-slate-500"><?= e(persen($p['persen'])) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ========================= JEJAK AKTIVITAS ========================= -->
    <section class="kartu kartu-pad">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="kartu-judul">Aktivitas Terakhir</h2>
        <span class="text-xs font-semibold text-slate-500">8 catatan terbaru</span>
      </div>
      <div class="bungkus-tabel mt-4">
        <table class="tabel">
          <thead>
            <tr><th class="w-44">Waktu</th><th>Kegiatan</th><th class="w-44">IP</th><th class="w-48">Peramban</th></tr>
          </thead>
          <tbody>
            <?php if (!$jejak): ?>
              <tr><td colspan="4" class="py-6 text-center text-slate-500">Belum ada aktivitas tercatat.</td></tr>
            <?php endif; ?>
            <?php foreach ($jejak as $j): ?>
              <tr>
                <td class="whitespace-nowrap text-slate-500"><?= e(tanggal_id((string) $j['waktu'], false, true)) ?></td>
                <td>
                  <span class="font-semibold text-slate-700"><?= e(str_replace('_', ' ', (string) $j['jenis'])) ?></span>
                  <?php if (!empty($j['keterangan'])): ?><span class="block text-xs text-slate-500"><?= e((string) $j['keterangan']) ?></span><?php endif; ?>
                </td>
                <td class="text-slate-500"><?= e((string) $j['ip']) ?></td>
                <td class="text-slate-500"><?= e((string) $j['peramban']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="catatan-kecil">Jejak surat suara dicatat <b>tanpa</b> memuat pilihan maupun identitas pemilih.</p>
    </section>
  </div>

  <!-- ============================ KOLOM KANAN ============================ -->
  <div class="space-y-5">
    <!-- Keutuhan data -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Keutuhan Data</h2>
      <div class="<?= $integ['cocok'] ? 'notis-ok' : 'notis-galat' ?> mt-3">
        <?= ikon($integ['cocok'] ? 'cek' : 'info') ?>
        <p>
          <?php if ($integ['cocok']): ?>
            Jumlah surat suara (<?= e(angka($integ['suara'])) ?>) sama dengan jumlah pemilih bertanda
            sudah memilih (<?= e(angka($integ['pemilih'])) ?>). Tidak ada suara hilang atau berlebih.
          <?php else: ?>
            Selisih terdeteksi: surat suara <?= e(angka($integ['suara'])) ?>,
            pemilih bertanda sudah memilih <?= e(angka($integ['pemilih'])) ?>.
            Periksa halaman DPT lalu laporkan ke pengembang.
          <?php endif; ?>
        </p>
      </div>

      <dl class="mt-4 space-y-2 text-sm">
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Pasangan calon</dt><dd class="font-bold"><?= e(angka(count($paslon))) ?></dd>
        </div>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Paslon aktif</dt><dd class="font-bold"><?= e(angka(paslon_aktif_jumlah())) ?></dd>
        </div>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Hasil untuk publik</dt>
          <dd class="font-bold"><?= hasil_publik() ? '<span class="pill-ok">Dibuka</span>' : '<span class="pill-kuning">Ditutup</span>' ?></dd>
        </div>
      </dl>
    </section>

    <!-- Pintasan -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Pintasan</h2>
      <div class="mt-3 grid gap-2.5">
        <?php foreach ([
            ['admin_paslon.php', 'paslon', 'Kelola pasangan calon', 'Tambah, ubah, urutkan, unggah foto'],
            ['admin_dpt.php', 'dpt', 'Kelola DPT pemilih', 'Tambah siswa, token, status memilih'],
            ['hasil.php', 'chart', 'Papan hasil real-count', 'Grafik & partisipasi berjalan'],
            ['admin_pengaturan.php', 'setelan', 'Pengaturan & keamanan', 'Identitas, ganti sandi panitia'],
            ['admin_reset.php', 'reset', 'Reset data', 'Kosongkan perolehan suara'],
        ] as $p): ?>
          <a href="<?= e($p[0]) ?>" class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-soft">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700"><?= ikon($p[1]) ?></span>
            <span class="min-w-0">
              <span class="block text-sm font-bold text-slate-800"><?= e($p[2]) ?></span>
              <span class="block text-xs text-slate-500"><?= e($p[3]) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Cara pembagian token -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Alur Pemungutan Suara</h2>
      <ol class="mt-3 space-y-2 text-sm text-slate-600">
        <?php foreach ([
            'Bagikan NISN &amp; token kepada siswa (lihat menu DPT).',
            'Siswa masuk, membaca visi-misi, lalu menekan PILIH PASLON.',
            'Suara tersimpan; siswa otomatis keluar (siap pemilih berikutnya).',
            'Pantau papan hasil real-count sampai pemungutan selesai.',
        ] as $i => $t): ?>
          <li class="flex gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-brand-600 text-xs font-black text-white"><?= $i + 1 ?></span>
            <span><?= $t ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>

<?php page_bottom(['shell' => 'admin']); ?>
