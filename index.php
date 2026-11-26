<?php
/**
 * index.php — HALAMAN DEPAN: DAFTAR MADRASAH (multi lembaga).
 * ---------------------------------------------------------------------------
 * Permintaan pemilik: pemilih memilih lembaganya lewat DAFTAR KARTU di halaman
 * depan, lalu menekan Masuk. Setelah itu barulah NISN/NIK + token dimasukkan di
 * halaman masuk lembaga tersebut (masuk.php?lembaga=ID).
 *
 * Tampilan (v2.3.0): hijau + kuning keemasan di atas latar putih bersih.
 * Judul & keterangan hero dapat diubah Super Admin lewat menu Halaman Depan
 * (super_depan.php) — jangan menulis teks itu mati di sini lagi.
 *
 * Aplikasi ini khusus madrasah; istilah kegiatan selalu OSIM.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

/* =========================================================================
   ALAMAT KHUSUS HALAMAN MASUK SUPER ADMIN:  /<aplikasi>/admin
   -------------------------------------------------------------------------
   Server aplikasi ini (Caddy) menyerahkan setiap jalur yang BUKAN berkas nyata
   ke index.php, jadi alamat itu tidak memerlukan aturan server / .htaccess.
   Alamat ini sengaja TANPA garis miring di akhir supaya seluruh tautan relatif
   pada halaman (aset, formulir, pengalihan) menuju AKAR APLIKASI — bukan ke
   folder /admin/ — dan tetap benar bila aplikasi dipasang pada domain sendiri.
   ========================================================================= */
$jalur = jalur_permintaan();
if (jalur_sama($jalur, 'admin')) {
    /* /…/admin/ -> /…/admin (alamat resmi tanpa garis miring akhir). */
    if (substr($jalur, -1) === '/') {
        header('Location: ' . rtrim($jalur, '/'), true, 301);
        exit;
    }
    define('EV_SUPER_MASUK', true);
    require __DIR__ . '/super_masuk.php';
    exit;
}

/* Sudah masuk? Langsung ke halaman yang sesuai. */
if (is_super()) {
    redirect('super.php');
}
if (is_admin()) {
    redirect('admin.php');
}
if (pemilih_kini() !== false) {
    redirect('pilih.php');
}

/* Halaman depan selalu lintas lembaga. */

$lembaga = lembaga_semua(false);
$stat    = lembaga_statistik_semua();
$jumlah  = count($lembaga);

/* Teks hero dapat diubah Super Admin (menu Halaman Depan). */
$depanJudul = trim(setting('depan_judul'));$depanKet   = trim(setting('depan_ket'));
if ($depanJudul === '') {
    $depanJudul = PENGATURAN_GLOBAL_BAWAAN['depan_judul'];
}
if ($depanKet === '') {
    $depanKet = PENGATURAN_GLOBAL_BAWAAN['depan_ket'];
}

/* Logo Komisi Pemilihan OSIM (diunggah Super Admin lewat menu Halaman Depan).
   Belum diunggah = panel logo memakai monogram OSIM bawaan. */
$logoDepan = depan_logo();

/* Tanpa tombol di bilah atas: halaman masuk Super Admin punya alamat sendiri
   (/<aplikasi>/admin) dan tidak ditautkan dari halaman depan (permintaan pemilik). */
page_top('Pilih Madrasah', ['shell' => 'publik']);
?>

<!-- ================================ HERO ================================ -->
<section class="depan-hero animate-masuk">
  <span class="depan-hero-kisi pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></span>
  <span class="depan-sorot -right-24 -top-28 -z-10 h-72 w-72 bg-emas-400/25" aria-hidden="true"></span>
  <span class="depan-sorot -bottom-32 left-1/4 -z-10 h-72 w-72 bg-brand-400/25" aria-hidden="true"></span>

  <div class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_auto]">
    <!-- ---------------------------- Kolom teks ---------------------------- -->
    <div class="min-w-0">
      <span class="depan-eyebrow"><?= ikon('kotak', 'h-3.5 w-3.5') ?> Pemilihan Elektronik</span>

      <h1 class="depan-judul"><?= nl2br(e($depanJudul)) ?></h1>
      <p class="depan-ket"><?= nl2br(e($depanKet)) ?></p>
      <span class="mt-6 block h-1 w-24 rounded-full bg-gradient-to-r from-emas-400 to-emas-600" aria-hidden="true"></span>
    </div>

    <!-- ------------------- Hiasan: kartu surat suara (CSS) ------------------- -->
    <div class="relative hidden justify-self-end pr-6 lg:block" aria-hidden="true">
      <span class="depan-sorot -left-10 top-6 h-40 w-40 bg-emas-400/25"></span>
      <div class="depan-surat">
        <span class="depan-segel"><?= ikon('cek', 'h-7 w-7') ?></span>
        <p class="text-[10px] font-black uppercase tracking-[.22em] text-brand-700">Surat Suara</p>
        <p class="mt-1 text-sm font-black leading-snug text-slate-900">Ketua &amp; Wakil Ketua<br><?= e(ISTILAH) ?></p>
        <div class="mt-4 space-y-2">
          <?php foreach ([1, 2, 3] as $n): ?>
            <div class="depan-surat-baris">
              <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-brand-800 text-[10px] font-black text-white"><?= $n ?></span>
              <span class="h-2 flex-1 rounded-full bg-slate-200"></span>
              <span class="depan-surat-kotak"><?= $n === 1 ? '<span class="h-2.5 w-2.5 rounded-sm bg-emas-500"></span>' : '' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="mt-4 rounded-xl bg-emas-50 px-3 py-2 text-center text-[10px] font-bold text-emas-800 ring-1 ring-emas-200">
          RAHASIA &middot; SATU PEMILIH SATU SUARA
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ============ LOGO KOMISI PEMILIHAN OSIM + DAFTAR KARTU MADRASAH ============ -->
<!-- Permintaan pemilik v2.4.0: logo Komisi Pemilihan OSIM menempati kolom KIRI
     (tanda biru pada tangkapan layar pemilik), sedangkan daftar kartu lembaga
     berada pada kolom KANAN (tanda merah). -->
<section class="mt-10 grid items-start gap-6 lg:grid-cols-[minmax(0,19rem)_minmax(0,1fr)] xl:gap-8"
         aria-label="Logo Komisi Pemilihan OSIM dan daftar madrasah">

  <!-- ------------------------- Kiri: panel logo ------------------------- -->
  <aside class="depan-logo-panel animate-masuk lg:sticky lg:top-6" aria-label="Logo Komisi Pemilihan OSIM">
    <span class="depan-logo-hias" aria-hidden="true"></span>
    <span class="depan-logo-bingkai">
      <?php if ($logoDepan !== null): ?>
        <img src="<?= e($logoDepan) ?>" alt="Logo Komisi Pemilihan OSIM">
      <?php else: ?>
        <span class="depan-logo-kosong">OSIM</span>
      <?php endif; ?>
    </span>
    <p class="depan-logo-label">Komisi Pemilihan</p>
    <p class="depan-logo-nama">OSIM</p>
    <p class="depan-logo-ket">
      Penyelenggara pemilihan ketua &amp; wakil ketua OSIM secara jujur, adil, dan demokratis.
    </p>
  </aside>

  <!-- ---------------------- Kanan: daftar kartu ------------------------ -->
  <div class="min-w-0">
  <div class="flex justify-end">
    <span class="pill-abu"><?= ikon('lembaga', 'h-3.5 w-3.5') ?>
      <span data-cari-jumlah><?= e(angka($jumlah)) ?></span> lembaga
    </span>
  </div>

  <?php if (!$lembaga): ?>
    <div class="kartu kartu-pad mt-4 text-center">
      <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100">
        <?= ikon('lembaga', 'h-8 w-8') ?>
      </span>
      <h3 class="mt-4 text-lg font-black text-slate-900">Belum ada madrasah</h3>
      <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
        Super Admin belum menambahkan lembaga mana pun. Bila Anda pengelola aplikasi, masuk lewat
        halaman Super Admin untuk menambahkan madrasah pertama.
      </p>
      <a href="admin" class="tombol-utama mt-5"><?= ikon('kunci', 'h-4 w-4') ?> Masuk Super Admin</a>
    </div>
  <?php else: ?>
    <div class="mt-4 grid gap-5 sm:grid-cols-2" data-daftar-lembaga>
      <?php foreach ($lembaga as $i => $l):
          $id       = (int) $l['id'];
          $s        = $stat[$id] ?? ['paslon_aktif' => 0, 'dpt' => 0, 'masuk' => 0, 'turnout' => 0.0];
          $jenis    = lembaga_jenis_sah((string) $l['jenis']);
          $istilah  = lembaga_istilah($jenis);
          $buka     = hasil_publik($id);
          $logo     = lembaga_logo($l);
          $adaPaslon = (int) $s['paslon_aktif'] > 0;
      ?>
        <article class="lembaga-kartu animate-masuk" style="animation-delay: <?= $i * 55 ?>ms"
                 data-lembaga-kartu>
          <span class="lembaga-kartu-aksen" aria-hidden="true"></span>

          <!-- Kepala kartu: LOGO madrasah (kotak hitam) + nama berwarna emas -->
          <div class="lembaga-kartu-kepala">
            <span class="lembaga-logo">
              <?php if ($logo !== null): ?>
                <img src="<?= e($logo) ?>" alt="Logo <?= e((string) $l['nama']) ?>" loading="lazy">
              <?php else: ?>
                <span class="text-sm font-black tracking-wide text-emas-300"><?= e(lembaga_inisial($l)) ?></span>
              <?php endif; ?>
            </span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-1.5">
                <span class="lembaga-jenis"><?= e(JENIS_LEMBAGA_PENDEK[$jenis]) ?></span>
                <?php if ($l['npsn']): ?>
                  <span class="lembaga-npsn">NPSN <?= e((string) $l['npsn']) ?></span>
                <?php endif; ?>
              </div>
              <h3 class="lembaga-nama"><?= e((string) $l['nama']) ?></h3>
              <p class="mt-1 text-[11px] font-medium text-brand-200">
                Pemilihan <?= e($istilah) ?><?= trim((string) ($l['alamat'] ?? '')) !== '' ? ' &middot; ' . e((string) $l['alamat']) : '' ?>
              </p>
            </div>
          </div>

          <!-- Badan kartu: angka ringkas + tombol masuk -->
          <div class="flex flex-1 flex-col gap-4 p-5">
            <dl class="grid grid-cols-2 gap-3">
              <div class="lembaga-stat">
                <dt class="lembaga-stat-label"><?= ikon('paslon', 'h-3.5 w-3.5') ?> Pasangan Calon</dt>
                <dd class="lembaga-stat-angka"><?= e(angka((int) $s['paslon_aktif'])) ?></dd>
              </div>
              <div class="lembaga-stat">
                <dt class="lembaga-stat-label"><?= ikon('dpt', 'h-3.5 w-3.5') ?> Pemilih Terdaftar</dt>
                <dd class="lembaga-stat-angka"><?= e(angka((int) $s['dpt'])) ?></dd>
              </div>
            </dl>

            <?php if (!$adaPaslon): ?>
              <p class="rounded-xl bg-emas-50 px-3 py-2 text-[11px] font-semibold text-emas-800 ring-1 ring-emas-200">
                Panitia belum menambahkan pasangan calon pada lembaga ini.
              </p>
            <?php endif; ?>

            <div class="mt-auto space-y-2.5">
              <a href="masuk.php?lembaga=<?= $id ?>" class="tombol-utama w-full py-3">
                <?= ikon('kotak', 'h-5 w-5') ?> Masuk &amp; Beri Suara
              </a>
              <?php if ($buka): ?>
                <a href="hasil.php?lembaga=<?= $id ?>" class="tombol-putih w-full">
                  <?= ikon('chart', 'h-4 w-4') ?> Lihat Hasil Sementara
                </a>
              <?php else: ?>
                <p class="lembaga-catatan">Hasil pemilihan belum dibuka untuk publik</p>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</section>

<!-- ====================== CEPAT &middot; RAHASIA &middot; TRANSPARAN ====================== -->
<section class="mt-8 grid gap-4 sm:grid-cols-3" aria-label="Keunggulan aplikasi">
  <?php foreach ([
      ['ikon' => 'jam',   'judul' => 'Cepat',      'teks' => 'Satu ketukan, suara langsung tercatat.'],
      ['ikon' => 'kunci', 'judul' => 'Rahasia',    'teks' => 'Surat suara tidak menyimpan identitas pemilih.'],
      ['ikon' => 'chart', 'judul' => 'Transparan', 'teks' => 'Hasil dihitung otomatis dan dapat dipantau.'],
  ] as $k): ?>
    <div class="depan-trust">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
        <?= ikon($k['ikon'], 'h-5 w-5') ?>
      </span>
      <div class="min-w-0">
        <p class="text-sm font-black text-slate-900"><?= e($k['judul']) ?></p>
        <p class="text-xs leading-relaxed text-slate-500"><?= e($k['teks']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<!-- ========================= CARA MEMBERIKAN SUARA ========================= -->
<section class="depan-panel mt-8">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul">Cara Memberikan Suara</h2>
        <p class="kartu-ket">Empat langkah, kurang dari dua menit.</p>
      </div>
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 ring-1 ring-brand-100">
        <?= ikon('cek', 'h-5 w-5') ?>
      </span>
    </div>

    <ol class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <?php foreach ([
          'Pilih kartu <b>madrasah</b> Anda pada daftar di atas.',
          'Masukkan <b>NISN</b> (siswa) atau <b>NIK</b> (guru &amp; tendik) beserta <b>token</b> dari panitia.',
          'Baca <b>visi, misi, dan program</b> tiap pasangan calon sebelum memutuskan.',
          'Tekan <b>PILIH PASLON</b>, konfirmasi, dan suara Anda langsung tersimpan.',
      ] as $i => $t): ?>
        <li class="depan-langkah">
          <span class="depan-langkah-nomor"><?= $i + 1 ?></span>
          <p class="text-sm leading-relaxed text-slate-600"><?= $t ?></p>
        </li>
      <?php endforeach; ?>
    </ol>

    <p class="mt-4 flex items-start gap-2 rounded-2xl bg-brand-50 px-4 py-3 text-xs leading-relaxed text-brand-800 ring-1 ring-brand-100">
      <?= ikon('kunci', 'h-4 w-4 shrink-0') ?>
      <span>Token hanya dapat dipakai <b>satu kali</b>. Setelah memilih, Anda otomatis keluar dan
        perangkat siap dipakai pemilih berikutnya.</span>
    </p>
</section>

  </div>

<?php page_bottom(['shell' => 'publik']); ?>
