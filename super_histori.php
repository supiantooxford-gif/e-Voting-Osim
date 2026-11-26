<?php
/**
 * super_histori.php — HISTORI AKTIVITAS SELURUH LEMBAGA (khusus Super Admin).
 * ---------------------------------------------------------------------------
 * Permintaan pemilik v2.5.0: tabel "Aktivitas Terakhir" DIHAPUS dari dashboard
 * panitia dan tempatnya dipindah ke akun Super Admin. Halaman ini menampilkan
 * jejak kegiatan seluruh madrasah (masuk/keluar, perubahan paslon & DPT,
 * perubahan pengaturan, reset data, dsb.) beserta penyaring madrasah,
 * pencarian, dan paginasi.
 *
 * Satu sumber aturan: histori_saring_get() · histori_aktivitas() ·
 * histori_aktivitas_jumlah() (inc/helpers.php). Jejak TIDAK pernah memuat
 * pilihan suara maupun identitas pemilih (lihat catat_aktivitas()).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$saring  = histori_saring_get();
$perHal  = 20;
$total   = histori_aktivitas_jumlah($saring);
$halaman = max(1, (int) ceil($total / $perHal));
$hal     = min(max(1, (int) ($_GET['hal'] ?? 1)), $halaman);
$baris   = histori_aktivitas($saring, $perHal, ($hal - 1) * $perHal);
$pilihan = histori_lembaga_pilihan();

/* Ringkasan singkat penyaring yang sedang dipakai. */
$labelSaring = 'Semua madrasah';
if ($saring['lembaga'] === 'lintas') {
    $labelSaring = 'Catatan lintas madrasah (tanpa lembaga)';
} elseif (preg_match('/^[0-9]{1,9}$/', (string) $saring['lembaga'])) {
    $labelSaring = $pilihan[(int) $saring['lembaga']] ?? 'Madrasah tidak ditemukan';
}

/** Tautan halaman dengan penyaring yang tetap terbawa. */
$tautan = static function (int $n) use ($saring): string {
    $q = ['lembaga' => $saring['lembaga']];
    if ($saring['cari'] !== '') {
        $q['cari'] = $saring['cari'];
    }
    if ($n > 1) {
        $q['hal'] = $n;
    }

    return 'super_histori.php?' . http_build_query($q);
};

page_top('Histori Aktivitas', [
    'shell' => 'super',
    'sub'   => 'Super Admin &middot; jejak kegiatan seluruh madrasah',
    'aksi'  => '<a href="super.php" class="tombol-putih tombol-kecil">' . ikon('dashboard', 'h-4 w-4') . ' Ringkasan</a>',
]);
?>

<section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan histori">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('jam') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Catatan Tersaring</p>
      <p class="stat-angka"><?= e(angka($total)) ?></p>
    </div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('lembaga') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Penyaring Madrasah</p>
      <p class="mt-1 truncate text-sm font-bold text-slate-800"><?= e($labelSaring) ?></p>
    </div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-900 text-white"><?= ikon('dpt') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Halaman</p>
      <p class="stat-angka"><?= e(angka($hal)) ?><span class="text-sm font-bold text-slate-500"> / <?= e(angka($halaman)) ?></span></p>
    </div>
  </div>
</section>

<!-- ============================ PENYARING ============================ -->
<section class="kartu kartu-pad mt-5">
  <h2 class="kartu-judul">Saring Histori</h2>
  <p class="kartu-ket">Pilih madrasah atau cari kata pada kegiatan, keterangan, IP, peramban, dan nama madrasah.</p>

  <form method="get" action="super_histori.php" class="mt-4 flex flex-wrap items-end gap-3">
    <label class="min-w-[13rem] flex-1">
      <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Madrasah</span>
      <select name="lembaga" class="isian">
        <option value="semua"<?= $saring['lembaga'] === 'semua' ? ' selected' : '' ?>>Semua madrasah</option>
        <option value="lintas"<?= $saring['lembaga'] === 'lintas' ? ' selected' : '' ?>>Lintas madrasah (tanpa lembaga)</option>
        <?php foreach ($pilihan as $id => $nama): ?>
          <option value="<?= (int) $id ?>"<?= $saring['lembaga'] === (string) $id ? ' selected' : '' ?>><?= e($nama) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="min-w-[13rem] flex-1">
      <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-slate-500">Cari</span>
      <input type="search" name="cari" value="<?= e($saring['cari']) ?>" placeholder="mis. masuk panitia, 172.70, Chrome" class="isian">
    </label>
    <button type="submit" class="tombol-utama"><?= ikon('cari', 'h-4 w-4') ?> Saring</button>
    <?php if ($saring['lembaga'] !== 'semua' || $saring['cari'] !== ''): ?>
      <a href="super_histori.php" class="tombol-putih"><?= ikon('reset', 'h-4 w-4') ?> Bersihkan</a>
    <?php endif; ?>
  </form>
</section>

<!-- ============================== TABEL ============================== -->
<section class="kartu kartu-pad mt-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="kartu-judul">Riwayat Kegiatan</h2>
      <p class="kartu-ket">Terbaru lebih dulu &middot; <?= e(angka($perHal)) ?> catatan per halaman.</p>
    </div>
    <span class="pill-abu"><?= ikon('jam', 'h-3.5 w-3.5') ?> <?= e(angka($total)) ?> catatan</span>
  </div>

  <?php if (!$baris): ?>
    <div class="mt-4 rounded-2xl bg-slate-50 p-6 text-center ring-1 ring-slate-200">
      <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-slate-400 ring-1 ring-slate-200"><?= ikon('jam', 'h-7 w-7') ?></span>
      <p class="mt-3 text-sm font-bold text-slate-800">Belum ada catatan yang cocok</p>
      <p class="mt-1 text-xs text-slate-500">Ubah penyaring atau bersihkan pencarian untuk melihat seluruh jejak kegiatan.</p>
      <a href="super_histori.php" class="tombol-putih tombol-kecil mt-3"><?= ikon('reset', 'h-4 w-4') ?> Lihat semua</a>
    </div>
  <?php else: ?>
    <div class="bungkus-tabel mt-4">
      <table class="tabel !min-w-[64rem]">
        <thead>
          <tr>
            <th class="w-48">Waktu</th>
            <th class="w-64">Madrasah</th>
            <th>Kegiatan</th>
            <th class="w-36">IP</th>
            <th class="w-44">Peramban</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($baris as $j):
              $namaLembaga = trim((string) ($j['lembaga_nama'] ?? '')); ?>
            <tr>
              <td class="whitespace-nowrap text-slate-500"><?= e(tanggal_id((string) $j['waktu'], false, true)) ?></td>
              <td>
                <?php if ($namaLembaga !== ''): ?>
                  <span class="text-sm font-semibold text-slate-700"><?= e($namaLembaga) ?></span>
                <?php else: ?>
                  <span class="pill-abu">Lintas madrasah</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="font-semibold text-slate-700"><?= e(histori_jenis_label((string) $j['jenis'])) ?></span>
                <?php if (!empty($j['keterangan'])): ?>
                  <span class="block text-xs text-slate-500"><?= e((string) $j['keterangan']) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-slate-500"><?= e((string) ($j['ip'] ?? '-')) ?></td>
              <td class="text-slate-500"><?= e((string) ($j['peramban'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($halaman > 1): ?>
      <nav class="mt-4 flex flex-wrap items-center justify-between gap-3" aria-label="Halaman histori">
        <p class="text-xs font-semibold text-slate-500">
          Menampilkan <?= e(angka(($hal - 1) * $perHal + 1)) ?>&ndash;<?= e(angka(min($total, $hal * $perHal))) ?>
          dari <?= e(angka($total)) ?> catatan
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <?php if ($hal > 1): ?>
            <a href="<?= e($tautan($hal - 1)) ?>" class="tombol-putih tombol-kecil"><?= ikon('kembali', 'h-4 w-4') ?> Sebelumnya</a>
          <?php endif; ?>
          <span class="pill-abu">Halaman <?= e(angka($hal)) ?> / <?= e(angka($halaman)) ?></span>
          <?php if ($hal < $halaman): ?>
            <a href="<?= e($tautan($hal + 1)) ?>" class="tombol-putih tombol-kecil">Berikutnya <?= ikon('panah', 'h-4 w-4') ?></a>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <p class="catatan-kecil">
    Jejak surat suara dicatat <b>tanpa</b> memuat pilihan maupun identitas pemilih.
    Catatan paling lama dipangkas otomatis (2000 catatan terbaru per madrasah).
  </p>
</section>

<?php page_bottom(['shell' => 'super']); ?>
