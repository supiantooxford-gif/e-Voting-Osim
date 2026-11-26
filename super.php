<?php
/**
 * super.php — DASHBOARD SUPER ADMIN (ringkasan semua madrasah).
 * ---------------------------------------------------------------------------
 * Pengontrol utama aplikasi:
 *   - ringkasan seluruh lembaga (jumlah, pemilih, partisipasi, akun panitia);
 *   - tabel status tiap lembaga + pintasan mengelola / memantau hasil;
 *   - pintasan menambah lembaga & mengatur akun panitia.
 * Halaman ini LINTAS lembaga: tidak pernah menampilkan data satu lembaga saja
 * tanpa memilihnya lebih dulu lewat super_masuk_lembaga.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$lembaga  = lembaga_semua(true);
$stat     = lembaga_statistik_semua();
$akun     = akun_panitia_jumlah();
$jmlAktif = 0;
$totalDpt = 0;
$totalSuara = 0;
foreach ($lembaga as $l) {
    $s = $stat[(int) $l['id']] ?? [];
    if ((int) $l['aktif'] === 1) {
        $jmlAktif++;
    }
    $totalDpt   += (int) ($s['dpt'] ?? 0);
    $totalSuara += (int) ($s['masuk'] ?? 0);
}
$partisipasi = $totalDpt > 0 ? round(($totalSuara / $totalDpt) * 100, 1) : 0.0;
$totalAkun   = array_sum($akun);
$admin       = admin_kini();
/* Histori aktivitas pindah ke akun Super Admin (v2.5.0) — dulu ada di
   dashboard panitia. Di sini hanya cuplikan; daftar lengkap + penyaring ada di
   super_histori.php. */
$jejak       = histori_aktivitas(['lembaga' => 'semua', 'cari' => ''], 8);

page_top('Ringkasan Semua Lembaga', [
    'shell' => 'super',
    'sub'   => 'Super Admin &middot; ' . $jmlAktif . ' lembaga aktif dari ' . count($lembaga),
    'aksi'  => '<a href="super_lembaga.php#formLembaga" class="tombol-utama tombol-kecil">'
        . ikon('tambah', 'h-4 w-4') . ' Tambah Lembaga</a>',
]);
?>

<!-- ============================== STATISTIK ============================== -->
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan aplikasi">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('lembaga') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Madrasah</p>
      <p class="stat-angka"><?= e(angka(count($lembaga))) ?></p>
      <p class="mt-1 text-xs font-semibold text-slate-500"><?= e(angka($jmlAktif)) ?> aktif</p>
    </div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Pemilih Terdaftar</p>
      <p class="stat-angka"><?= e(angka($totalDpt)) ?></p>
      <p class="mt-1 text-xs font-semibold text-slate-500">seluruh lembaga</p>
    </div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
    <div class="min-w-0">
      <p class="stat-label">Suara Masuk</p>
      <p class="stat-angka text-emerald-700"><?= e(angka($totalSuara)) ?></p>
      <p class="mt-1 text-xs font-semibold text-slate-500"><?= e(angka(max(0, $totalDpt - $totalSuara))) ?> belum memilih</p>
    </div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-900 text-white"><?= ikon('trophy') ?></span>
    <div class="min-w-0 flex-1">
      <p class="stat-label">Partisipasi Keseluruhan</p>
      <p class="stat-angka"><?= e(persen($partisipasi)) ?></p>
      <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-brand-600" style="width: <?= e((string) min(100, $partisipasi)) ?>%"></div>
      </div>
    </div>
  </div>
</section>

<!-- ======================= PINTASAN PENGELOLAAN ======================= -->
<section class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ([
      ['super_depan.php', 'setelan', 'Halaman Depan', 'Ubah judul & keterangan besar yang tampil di halaman depan.'],
      ['super_lembaga.php', 'lembaga', 'Kelola Lembaga', 'Tambah, ubah, aktifkan/nonaktifkan, atau hapus madrasah.'],
      ['super_akun.php', 'akun', 'Akun Panitia', angka($totalAkun) . ' akun panitia aktif di seluruh lembaga.'],
      ['super_hasil.php', 'chart', 'Pantau Hasil', 'Bandingkan perolehan suara & partisipasi semua lembaga.'],
  ] as $p): ?>
    <a href="<?= e($p[0]) ?>" class="kartu kartu-pad group flex items-start gap-4 transition hover:-translate-y-0.5 hover:shadow-naik">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700"><?= ikon($p[1]) ?></span>
      <span class="min-w-0">
        <span class="block text-sm font-black text-slate-900"><?= e($p[2]) ?></span>
        <span class="mt-0.5 block text-xs leading-relaxed text-slate-500"><?= e($p[3]) ?></span>
      </span>
      <span class="ml-auto self-center text-slate-300 transition group-hover:text-brand-600"><?= ikon('panah', 'h-5 w-5') ?></span>
    </a>
  <?php endforeach; ?>
</section>

<!-- ====================== TABEL STATUS TIAP LEMBAGA ====================== -->
<section class="kartu kartu-pad mt-6">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="kartu-judul">Status Setiap Madrasah</h2>
      <p class="kartu-ket">Klik <b>Kelola</b> untuk membuka panel panitia lembaga tersebut.</p>
    </div>
    <span class="pill-abu"><?= ikon('lembaga', 'h-3.5 w-3.5') ?> <?= e(angka(count($lembaga))) ?> lembaga</span>
  </div>

  <?php if (!$lembaga): ?>
    <div class="mt-4 rounded-2xl bg-slate-50 p-6 text-center ring-1 ring-slate-200">
      <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white text-slate-400 ring-1 ring-slate-200"><?= ikon('lembaga', 'h-7 w-7') ?></span>
      <p class="mt-3 text-sm font-bold text-slate-800">Belum ada madrasah</p>
      <p class="mt-1 text-xs text-slate-500">Tambahkan lembaga pertama lewat tombol <b>Tambah Lembaga</b>.</p>
      <a href="super_lembaga.php#formLembaga" class="tombol-utama tombol-kecil mt-3"><?= ikon('tambah', 'h-4 w-4') ?> Tambah Lembaga</a>
    </div>
  <?php else: ?>
    <div class="bungkus-tabel mt-4">
      <table class="tabel !min-w-[64rem]" id="tblLembaga">
        <thead>
          <tr>
            <th>Madrasah</th>
            <th class="w-20">Paslon</th>
            <th class="w-20">DPT</th>
            <th class="w-20">Suara</th>
            <th class="w-40">Partisipasi</th>
            <th class="w-24">Panitia</th>
            <th class="w-28">Hasil</th>
            <th class="w-24">Status</th>
            <th class="w-64">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lembaga as $l):
              $id  = (int) $l['id'];
              $s   = $stat[$id] ?? ['paslon_aktif' => 0, 'dpt' => 0, 'masuk' => 0, 'belum' => 0, 'turnout' => 0.0];
              $buka = hasil_publik($id);
              $aktif = (int) $l['aktif'] === 1; ?>
            <tr class="<?= $aktif ? '' : 'opacity-60' ?>">
              <td>
                <div class="flex items-center gap-3">
                  <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-xs font-black text-brand-800">
                    <?= e(lembaga_inisial($l)) ?>
                  </span>
                  <span class="min-w-0">
                    <span class="block truncate text-sm font-bold text-slate-800"><?= e((string) $l['nama']) ?></span>
                    <span class="block text-[11px] text-slate-500">
                      <?= e(JENIS_LEMBAGA_PENDEK[lembaga_jenis_sah((string) $l['jenis'])]) ?>
                      <?= $l['npsn'] ? ' &middot; NPSN ' . e((string) $l['npsn']) : '' ?>
                    </span>
                  </span>
                </div>
              </td>
              <td class="text-sm font-semibold text-slate-700"><?= e(angka((int) $s['paslon_aktif'])) ?></td>
              <td class="text-sm font-semibold text-slate-700"><?= e(angka((int) $s['dpt'])) ?></td>
              <td class="text-sm font-black text-emerald-700"><?= e(angka((int) $s['masuk'])) ?></td>
              <td>
                <p class="text-sm font-bold text-slate-800"><?= e(persen((float) $s['turnout'])) ?></p>
                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-emerald-500" style="width: <?= e((string) min(100, (float) $s['turnout'])) ?>%"></div>
                </div>
              </td>
              <td>
                <?php if (($akun[$id] ?? 0) > 0): ?>
                  <span class="text-sm font-semibold text-slate-700"><?= e(angka($akun[$id])) ?></span>
                <?php else: ?>
                  <a href="super_akun.php?lembaga=<?= $id ?>" class="pill-kuning" title="Buat akun panitia">
                    <?= ikon('tambah', 'h-3 w-3') ?> belum ada
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <?= $buka
                    ? '<span class="pill-ok">Publik</span>'
                    : '<span class="pill-kuning">Ditutup</span>' ?>
              </td>
              <td>
                <?= $aktif ? '<span class="pill-ok">Aktif</span>' : '<span class="pill-abu">Nonaktif</span>' ?>
              </td>
              <td>
                <div class="flex flex-wrap gap-1.5">
                  <?php if ($aktif): ?>
                    <form method="post" action="super_masuk_lembaga.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="masuk">
                      <input type="hidden" name="lembaga" value="<?= $id ?>">
                      <input type="hidden" name="tujuan" value="admin.php">
                      <button type="submit" class="tombol-utama tombol-kecil"><?= ikon('setelan', 'h-3.5 w-3.5') ?> Kelola</button>
                    </form>
                    <form method="post" action="super_masuk_lembaga.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="masuk">
                      <input type="hidden" name="lembaga" value="<?= $id ?>">
                      <input type="hidden" name="tujuan" value="hasil.php">
                      <button type="submit" class="tombol-putih tombol-kecil"><?= ikon('chart', 'h-3.5 w-3.5') ?> Hasil</button>
                    </form>
                  <?php endif; ?>
                  <a href="super_lembaga.php?edit=<?= $id ?>#formLembaga" class="tombol-putih tombol-kecil" title="Ubah lembaga">
                    <?= ikon('edit', 'h-3.5 w-3.5') ?>
                  </a>
                  <a href="super_akun.php?lembaga=<?= $id ?>" class="tombol-putih tombol-kecil" title="Akun panitia">
                    <?= ikon('akun', 'h-3.5 w-3.5') ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="border-t border-slate-200 px-4 py-3 font-black">JUMLAH</td>
            <td class="border-t border-slate-200 px-4 py-3 font-black"><?= e(angka(array_sum(array_column($stat, 'paslon_aktif')))) ?></td>
            <td class="border-t border-slate-200 px-4 py-3 font-black"><?= e(angka($totalDpt)) ?></td>
            <td class="border-t border-slate-200 px-4 py-3 font-black text-emerald-700"><?= e(angka($totalSuara)) ?></td>
            <td class="border-t border-slate-200 px-4 py-3 font-black" colspan="5"><?= e(persen($partisipasi)) ?> partisipasi keseluruhan</td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ======================= HISTORI AKTIVITAS ======================= -->
<section class="kartu kartu-pad mt-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="kartu-judul">Aktivitas Terakhir</h2>
      <p class="kartu-ket">Jejak kegiatan seluruh madrasah, terbaru lebih dulu.</p>
    </div>
    <a href="super_histori.php" class="tombol-putih tombol-kecil"><?= ikon('jam', 'h-4 w-4') ?> Histori Lengkap</a>
  </div>

  <?php if (!$jejak): ?>
    <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
      Belum ada aktivitas tercatat.
    </p>
  <?php else: ?>
    <div class="bungkus-tabel mt-4">
      <table class="tabel !min-w-[52rem]">
        <thead>
          <tr><th class="w-48">Waktu</th><th class="w-56">Madrasah</th><th>Kegiatan</th><th class="w-36">IP</th></tr>
        </thead>
        <tbody>
          <?php foreach ($jejak as $j):
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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="catatan-kecil">
    Jejak surat suara dicatat <b>tanpa</b> memuat pilihan maupun identitas pemilih.
    Data ini hanya terbuka untuk Super Admin.
  </p>
</section>

<!-- ============================ KEAMANAN ============================ -->
<section class="kartu kartu-pad mt-5">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h2 class="kartu-judul">Akun Super Admin</h2>
      <p class="kartu-ket">
        Masuk sebagai <b><?= e((string) ($admin['username'] ?? '-')) ?></b>
        (<?= e((string) ($admin['nama'] ?? '-')) ?>). Super Admin dapat mengelola seluruh lembaga,
        membuat akun panitia, dan membuka panel lembaga mana pun.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="super_lembaga.php" class="tombol-putih tombol-kecil"><?= ikon('lembaga', 'h-4 w-4') ?> Kelola Lembaga</a>
      <a href="super_akun.php" class="tombol-putih tombol-kecil"><?= ikon('akun', 'h-4 w-4') ?> Akun Panitia</a>
    </div>
  </div>

  <div class="notis-info mt-4">
    <?= ikon('kunci') ?>
    <p>Kata sandi Super Admin dapat diganti pada menu <b>Akun Panitia</b> (bagian “Kata Sandi Saya”).
      Setiap lembaga hanya dapat dilihat panitia lembaga itu sendiri.</p>
  </div>
</section>

<?php page_bottom(['shell' => 'super']); ?>
