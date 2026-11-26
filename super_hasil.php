<?php
/**
 * super_hasil.php — PANTAU HASIL SELURUH LEMBAGA (khusus Super Admin).
 * ---------------------------------------------------------------------------
 * Membandingkan perolehan suara & partisipasi semua madrasah dalam satu
 * halaman. Untuk membuka papan hasil lengkap (grafik, mode layar) Super Admin
 * memilih lembaga itu lebih dulu — tombolnya mengirim POST ke
 * super_masuk_lembaga.php dengan tujuan hasil.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$lembaga = lembaga_semua(true);

/* Hasil tiap lembaga dihitung dengan SATU sumber aturan (hasil_lengkap()). */
$baris = [];
$total = ['dpt' => 0, 'masuk' => 0, 'belum' => 0, 'paslon' => 0];
foreach ($lembaga as $l) {
    $id = (int) $l['id'];
    $h  = hasil_lengkap($id);
    $baris[] = ['lembaga' => $l, 'hasil' => $h];
    $total['dpt']    += (int) $h['dpt'];
    $total['masuk']  += (int) $h['masuk'];
    $total['belum']  += (int) $h['belum'];
    $total['paslon'] += count($h['paslon']);
}
$turnout = $total['dpt'] > 0 ? round(($total['masuk'] / $total['dpt']) * 100, 1) : 0.0;
$sudahSelesai = $total['dpt'] > 0 && $total['belum'] === 0;

page_top('Pantau Hasil Semua Lembaga', [
    'shell' => 'super',
    'sub'   => 'Diperbarui ' . date('H:i:s') . ' ' . LABEL_ZONA . ' &middot; ' . count($lembaga) . ' lembaga',
    'aksi'  => '<a href="super.php" class="tombol-putih tombol-kecil">' . ikon('dashboard', 'h-4 w-4') . ' Ringkasan</a>',
]);
?>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan hasil">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('paslon') ?></span>
    <div class="min-w-0"><p class="stat-label">Pasangan Calon</p><p class="stat-angka"><?= e(angka($total['paslon'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
    <div class="min-w-0"><p class="stat-label">Pemilih Terdaftar</p><p class="stat-angka"><?= e(angka($total['dpt'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
    <div class="min-w-0"><p class="stat-label">Suara Masuk</p><p class="stat-angka text-emerald-700"><?= e(angka($total['masuk'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-900 text-white"><?= ikon('trophy') ?></span>
    <div class="min-w-0 flex-1">
      <p class="stat-label">Partisipasi</p>
      <p class="stat-angka"><?= e(persen($turnout)) ?></p>
      <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-brand-600" style="width: <?= e((string) min(100, $turnout)) ?>%"></div>
      </div>
    </div>
  </div>
</section>

<div class="notis-info mt-5">
  <?= ikon('info') ?>
  <p><?= $sudahSelesai
      ? 'Seluruh pemilih terdaftar di semua lembaga sudah memberikan suaranya.'
      : 'Masih ada ' . e(angka($total['belum'])) . ' pemilih yang belum memberikan suara. Angka di halaman ini dihitung saat halaman dimuat — muat ulang untuk memperbarui.' ?></p>
</div>

<section class="kartu kartu-pad mt-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="kartu-judul">Perolehan Suara per Lembaga</h2>
      <p class="kartu-ket">Perolehan tertinggi sementara di setiap madrasah.</p>
    </div>
    <span class="pill-abu"><?= ikon('jam', 'h-3.5 w-3.5') ?> <?= e(date('H:i:s')) ?> <?= e(LABEL_ZONA) ?></span>
  </div>

  <?php if (!$lembaga): ?>
    <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
      Belum ada lembaga yang terdaftar.
    </p>
  <?php endif; ?>

  <div class="mt-4 grid gap-5 lg:grid-cols-2">
    <?php foreach ($baris as $b):
        $l = $b['lembaga'];
        $h = $b['hasil'];
        $id = (int) $l['id'];
        $buka = hasil_publik($id); ?>
      <article class="kartu overflow-hidden">
        <div class="flex items-center gap-3 bg-gradient-to-r from-brand-800 to-brand-900 p-4 text-white">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/15 text-xs font-black ring-1 ring-white/25">
            <?= e(lembaga_inisial($l)) ?>
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-black"><?= e((string) $l['nama']) ?></p>
            <p class="text-[11px] text-brand-200">
              <?= e(JENIS_LEMBAGA_PENDEK[lembaga_jenis_sah((string) $l['jenis'])]) ?>
              &middot; <?= e(persen((float) $h['turnout'])) ?> partisipasi
              <?= $buka ? '' : ' &middot; hasil belum publik' ?>
            </p>
          </div>
          <?php if ((int) $l['aktif'] !== 1): ?><span class="pill-abu !bg-white/15 !text-white !ring-white/20">Nonaktif</span><?php endif; ?>
        </div>

        <div class="p-4">
          <dl class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-slate-50 p-2.5 ring-1 ring-slate-200">
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">DPT</dt>
              <dd class="text-base font-black text-slate-900"><?= e(angka((int) $h['dpt'])) ?></dd>
            </div>
            <div class="rounded-xl bg-emerald-50 p-2.5 ring-1 ring-emerald-200">
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700">Masuk</dt>
              <dd class="text-base font-black text-emerald-800"><?= e(angka((int) $h['masuk'])) ?></dd>
            </div>
            <div class="rounded-xl bg-amber-50 p-2.5 ring-1 ring-amber-200">
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Belum</dt>
              <dd class="text-base font-black text-amber-800"><?= e(angka((int) $h['belum'])) ?></dd>
            </div>
          </dl>

          <?php if ($h['teratas'] !== null): ?>
            <div class="mt-3 rounded-2xl bg-gradient-to-r from-emerald-50 to-brand-50 p-3 ring-1 ring-emerald-200">
              <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Perolehan tertinggi sementara</p>
              <p class="mt-0.5 truncate text-sm font-black text-slate-900">
                <?= e($h['teratas']['ketua'] . ' & ' . $h['teratas']['wakil']) ?>
                <span class="font-semibold text-slate-500">(Paslon <?= e((string) $h['teratas']['nomor']) ?>)</span>
              </p>
              <p class="text-xs font-semibold text-slate-600">
                <?= e(angka((int) $h['teratas']['suara'])) ?> suara &middot; <?= e(persen((float) $h['teratas']['persen'])) ?>
              </p>
            </div>
          <?php else: ?>
            <p class="mt-3 rounded-2xl bg-slate-50 p-3 text-xs text-slate-500 ring-1 ring-slate-200">
              Belum ada surat suara yang masuk.
            </p>
          <?php endif; ?>

          <div class="mt-3 space-y-2">
            <?php foreach ($h['paslon'] as $p): ?>
              <div class="flex items-center gap-2.5">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-[11px] font-black text-white"
                      style="background: <?= e($p['warna']) ?>"><?= e((string) $p['nomor']) ?></span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-xs font-semibold text-slate-700"><?= e($p['ketua']) ?> &amp; <?= e($p['wakil']) ?></span>
                  <span class="mt-1 block h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                    <span class="block h-full rounded-full" style="width: <?= e((string) $p['persen']) ?>%; background: <?= e($p['warna']) ?>"></span>
                  </span>
                </span>
                <span class="w-16 shrink-0 text-right text-xs font-bold text-slate-700"><?= e(persen((float) $p['persen'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ((int) $l['aktif'] === 1): ?>
            <form method="post" action="super_masuk_lembaga.php" class="mt-4">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="masuk">
              <input type="hidden" name="lembaga" value="<?= $id ?>">
              <input type="hidden" name="tujuan" value="hasil.php">
              <button type="submit" class="tombol-putih w-full">
                <?= ikon('chart', 'h-4 w-4') ?> Buka Papan Hasil Lengkap
              </button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php page_bottom(['shell' => 'super']); ?>
