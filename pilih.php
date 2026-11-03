<?php
/**
 * pilih.php — HALAMAN PEMILIHAN (antarmuka pemilih/siswa).
 * ---------------------------------------------------------------------------
 * - Kartu setiap pasangan calon (nomor urut, foto, nama ketua & wakil).
 * - Tombol "Lihat Visi & Misi" membuka modal (satu <dialog> per paslon,
 *   isinya sudah ada di HTML sehingga tetap terbaca tanpa JavaScript).
 * - Tombol "PILIH PASLON" mengirim formulir ke simpan_suara.php; JavaScript
 *   menyisipkan modal konfirmasi lebih dulu. Tanpa JavaScript pun tombolnya
 *   tetap berfungsi (langsung mengirim suara), jadi tidak ada siswa yang
 *   kehilangan hak pilih karena skrip gagal dimuat.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_pemilih();

$pemilih = pemilih_kini();
$paslon  = paslon_list();
$stat    = statistik();

$aksiKanan = '<span class="hidden rounded-xl bg-white/15 px-3 py-1.5 text-xs font-bold ring-1 ring-white/25 sm:inline-flex">'
    . e((string) $pemilih['nama']) . ' &middot; ' . e((string) $pemilih['kelas']) . '</span>'
    . '<a href="logout.php" class="tombol-putih tombol-kecil !bg-white/10 !text-white !ring-white/25 hover:!bg-white/20">'
    . ikon('keluar', 'h-4 w-4') . ' Keluar</a>';

page_top('Pilih Pasangan Calon', ['shell' => 'publik', 'aksi' => $aksiKanan]);
?>

<!-- ======================== SAMBUTAN & PETUNJUK ======================== -->
<section class="animate-masuk overflow-hidden rounded-3xl bg-gradient-to-r from-brand-800 via-brand-900 to-brand-800 p-6 text-white shadow-naik sm:p-8">
  <div class="flex flex-wrap items-center gap-4">
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-emerald-500/25 text-emerald-100 ring-1 ring-emerald-300/30">
      <?= ikon('kotak', 'h-7 w-7') ?>
    </span>
    <div class="min-w-0 flex-1">
      <h1 class="text-xl font-black sm:text-2xl">Selamat datang, <?= e((string) $pemilih['nama']) ?>!</h1>
      <p class="mt-1 text-sm text-brand-100">
        Pelajari visi, misi, dan program setiap pasangan calon, lalu tekan <b>PILIH PASLON</b> pada pilihan Anda.
        Suara hanya dapat diberikan satu kali dan bersifat <b>rahasia</b>.
      </p>
    </div>
    <?php if (hasil_publik()): ?>
      <div class="rounded-2xl bg-white/10 px-4 py-3 ring-1 ring-white/15" data-partisipasi
           data-dpt="<?= (int) $stat['dpt'] ?>" data-masuk="<?= (int) $stat['masuk'] ?>">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-200">Pemilih sudah memberi suara</p>
        <p class="text-lg font-black"><span data-partisipasi-masuk><?= e(angka($stat['masuk'])) ?></span>
          <span class="text-sm font-semibold text-brand-200">dari <?= e(angka($stat['dpt'])) ?></span></p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!$paslon): ?>
  <div class="notis-kuning mt-6">
    <?= ikon('info') ?>
    <p>Belum ada pasangan calon yang dibuka oleh panitia. Silakan hubungi panitia OSIS.</p>
  </div>
<?php else: ?>

<!-- ============================ KARTU PASLON ============================ -->
<section class="mt-6" aria-label="Daftar pasangan calon">
  <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($paslon as $i => $p):
        $foto  = foto_paslon($p);
        $nomor = (int) $p['nomor'];
        $nama  = $p['ketua'] . ' & ' . $p['wakil'];
        $warna = warna_paslon($i); ?>
      <article class="paslon-kartu animate-masuk" style="animation-delay: <?= $i * 70 ?>ms">
        <span class="paslon-nomor" style="color: <?= e($warna) ?>">
          <?= e((string) $nomor) ?>
        </span>
        <span class="absolute right-4 top-4 z-20 rounded-full bg-brand-900/80 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white backdrop-blur">
          Paslon <?= e((string) $nomor) ?>
        </span>

        <!-- Foto kandidat (atau inisial bila belum ada) -->
        <div class="relative bg-gradient-to-br from-brand-800 to-brand-950">
          <?php if ($foto !== null): ?>
            <img src="<?= e($foto) ?>" alt="Foto pasangan calon nomor <?= e((string) $nomor) ?>"
                 class="paslon-foto" loading="lazy" onerror="this.classList.add('hidden')">
          <?php else: ?>
            <div class="paslon-foto grid place-items-center">
              <div class="text-center text-white">
                <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-white/15 text-3xl font-black ring-2 ring-white/25">
                  <?= e(strtoupper(substr((string) $p['ketua'], 0, 1) . substr((string) $p['wakil'], 0, 1))) ?>
                </span>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-brand-200">Foto belum diunggah</p>
              </div>
            </div>
          <?php endif; ?>
          <span class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-900/85 to-transparent"></span>
        </div>

        <div class="flex flex-1 flex-col p-5">
          <h2 class="text-lg font-black leading-snug text-slate-900"><?= e((string) $p['ketua']) ?></h2>
          <p class="text-sm font-semibold text-slate-500">
            Calon Ketua<?= $p['kelas_ketua'] ? ' &middot; ' . e((string) $p['kelas_ketua']) : '' ?>
          </p>

          <div class="mt-3 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200">
            <p class="text-sm font-bold text-slate-800"><?= e((string) $p['wakil']) ?></p>
            <p class="text-xs font-semibold text-slate-500">
              Calon Wakil Ketua<?= $p['kelas_wakil'] ? ' &middot; ' . e((string) $p['kelas_wakil']) : '' ?>
            </p>
          </div>

          <?php if (trim((string) $p['visi']) !== ''): ?>
            <p class="mt-4 line-clamp-3 text-sm italic leading-relaxed text-slate-600">
              &ldquo;<?= e(potong_teks((string) $p['visi'], 130)) ?>&rdquo;
            </p>
          <?php endif; ?>

          <div class="mt-5 space-y-3">
            <button type="button" class="tombol-putih w-full" data-modal-buka="visi-<?= (int) $p['id'] ?>">
              <?= ikon('info', 'h-5 w-5') ?> Lihat Visi &amp; Misi
            </button>

            <form method="post" action="simpan_suara.php" data-pilih-form
                  data-nomor="<?= e((string) $nomor) ?>" data-nama="<?= e($nama) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="paslon_id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="paslon-pilih">
                <?= ikon('cek', 'h-6 w-6') ?> PILIH PASLON <?= e((string) $nomor) ?>
              </button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- ================= MODAL VISI & MISI (satu per paslon) ================= -->
<?php foreach ($paslon as $p):
    $nomor = (int) $p['nomor']; ?>
  <dialog id="visi-<?= (int) $p['id'] ?>" class="modal" aria-labelledby="judulVisi<?= (int) $p['id'] ?>">
    <div class="flex max-h-[92vh] flex-col">
      <header class="flex items-start gap-3 border-b border-slate-200 bg-gradient-to-r from-brand-800 to-brand-900 p-5 text-white">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/15 text-lg font-black ring-1 ring-white/25">
          <?= e((string) $nomor) ?>
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-[11px] font-bold uppercase tracking-wide text-brand-200">Pasangan Calon <?= e((string) $nomor) ?></p>
          <h3 id="judulVisi<?= (int) $p['id'] ?>" class="text-base font-black leading-snug">
            <?= e((string) $p['ketua']) ?> &amp; <?= e((string) $p['wakil']) ?>
          </h3>
        </div>
        <button type="button" class="rounded-xl p-2 transition hover:bg-white/15" data-modal-tutup aria-label="Tutup">
          <?= ikon('tutup') ?>
        </button>
      </header>

      <div class="space-y-5 overflow-y-auto p-5">
        <section>
          <h4 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-brand-700">
            <?= ikon('piala', 'h-4 w-4') ?> Visi
          </h4>
          <p class="mt-2 rounded-2xl bg-brand-50 p-4 text-sm italic leading-relaxed text-brand-900 ring-1 ring-brand-100">
            <?= e((string) $p['visi']) !== '' ? e((string) $p['visi']) : 'Belum diisi.' ?>
          </p>
        </section>

        <section>
          <h4 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-emerald-700">
            <?= ikon('cek', 'h-4 w-4') ?> Misi
          </h4>
          <?php $misi = poin($p['misi']); ?>
          <?php if ($misi): ?>
            <ul class="mt-2 space-y-2">
              <?php foreach ($misi as $m): ?>
                <li class="flex gap-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-700 ring-1 ring-slate-200">
                  <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-600 text-[11px] font-black text-white">&#10003;</span>
                  <span><?= e($m) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="mt-2 text-sm text-slate-500">Belum diisi.</p>
          <?php endif; ?>
        </section>

        <section>
          <h4 class="flex items-center gap-2 text-sm font-black uppercase tracking-wide text-amber-700">
            <?= ikon('dashboard', 'h-4 w-4') ?> Program Kerja Unggulan
          </h4>
          <?php $program = poin($p['program']); ?>
          <?php if ($program): ?>
            <ol class="mt-2 space-y-2">
              <?php foreach ($program as $n => $pr): ?>
                <li class="flex gap-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-100">
                  <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-amber-500 text-[11px] font-black text-white"><?= $n + 1 ?></span>
                  <span><?= e($pr) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="mt-2 text-sm text-slate-500">Belum diisi.</p>
          <?php endif; ?>
        </section>
      </div>

      <footer class="flex flex-wrap gap-3 border-t border-slate-200 bg-slate-50 p-4">
        <button type="button" class="tombol-putih flex-1" data-modal-tutup>Tutup</button>
        <button type="button" class="tombol-ok flex-1" data-pilih-dari-modal="<?= (int) $p['id'] ?>">
          <?= ikon('cek', 'h-5 w-5') ?> Pilih Paslon <?= e((string) $nomor) ?>
        </button>
      </footer>
    </div>
  </dialog>
<?php endforeach; ?>

<!-- ====================== MODAL KONFIRMASI PILIHAN ====================== -->
<dialog id="modalKonfirmasi" class="modal !w-[min(94vw,30rem)]" aria-labelledby="judulKonfirmasi">
  <div class="p-6 text-center">
    <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-amber-100 text-amber-600">
      <?= ikon('info', 'h-8 w-8') ?>
    </span>
    <h3 id="judulKonfirmasi" class="mt-4 text-xl font-black text-slate-900">Apakah Anda yakin dengan pilihan Anda?</h3>
    <p class="mt-2 text-sm text-slate-600">
      Anda akan memilih <b id="konfPaslon" class="text-brand-700">Paslon 1</b>.
    </p>
    <div class="mt-4 rounded-2xl bg-rose-50 p-4 text-left text-xs font-semibold text-rose-800 ring-1 ring-rose-200">
      <p>Suara yang sudah dikirim <b>tidak dapat diubah atau dibatalkan</b>.</p>
      <p class="mt-1">Setelah dikirim, Anda otomatis keluar dan tidak dapat memilih lagi.</p>
    </div>
    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
      <button type="button" class="tombol-putih flex-1" data-konf-batal>Batal</button>
      <button type="button" class="tombol-ok flex-1" data-konf-lanjut>
        <?= ikon('cek', 'h-5 w-5') ?> Ya, Pilih Sekarang
      </button>
    </div>
  </div>
</dialog>

<?php endif; ?>

<p class="mx-auto mt-8 max-w-3xl rounded-2xl bg-white px-4 py-3 text-center text-xs text-slate-500 ring-1 ring-slate-200">
  <?= ikon('kunci', 'h-4 w-4 inline') ?>
  Sistem hanya mencatat <b>bahwa</b> Anda sudah memilih, bukan <b>siapa</b> yang Anda pilih.
  Kerahasiaan pilihan dijamin oleh panitia OSIS.
</p>

<?php page_bottom(['shell' => 'publik']); ?>
