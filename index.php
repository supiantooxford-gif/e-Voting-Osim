<?php
/**
 * index.php — HALAMAN MASUK.
 * ---------------------------------------------------------------------------
 * Dua peran dalam satu halaman:
 *   - Pemilih (siswa) : NISN + Token unik dari panitia.
 *   - Panitia/Pembina : nama pengguna + kata sandi.
 * Tanpa JavaScript kedua panel tetap tampil & formulirnya tetap bisa dikirim.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

/* Sudah masuk? Langsung ke halaman yang sesuai. */
if (is_admin()) {
    redirect('admin.php');
}
if (pemilih_kini() !== false) {
    redirect('pilih.php');
}

$tab = ($_GET['tab'] ?? '') === 'panitia' ? 'panitia' : 'pemilih';

/* Foto pasangan calon untuk slide di halaman masuk (HANYA foto — permintaan
   pemilik). Kartu statistik & kartu fitur yang lama DIHAPUS dari blok merah. */
$paslonTampil = paslon_list();

page_top('Masuk', ['shell' => 'publik', 'aksi' => hasil_publik()
    ? '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Hasil Sementara</a>'
    : '']);
?>

<div class="grid gap-6 lg:grid-cols-[1.1fr_minmax(0,27rem)] lg:gap-10">

  <!-- ======================== KOLOM KIRI: PENJELASAN ======================== -->
  <section class="animate-masuk">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 p-6 text-white shadow-naik sm:p-9">
      <span class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-emerald-400/20 blur-3xl"></span>
      <span class="pointer-events-none absolute -bottom-24 -left-12 h-56 w-56 rounded-full bg-brand-400/20 blur-3xl"></span>

      <span class="pill bg-white/15 text-white ring-white/25"><?= ikon('kotak', 'h-4 w-4') ?> Pemilihan Elektronik</span>
      <h1 class="mt-4 text-3xl font-black leading-tight sm:text-4xl"><?= e(setting('nama_pemilihan')) ?></h1>
      <p class="mt-2 text-sm font-semibold text-brand-100"><?= e(setting('nama_lembaga')) ?></p>
      <p class="mt-1 text-sm text-brand-200"><?= e(setting('periode')) ?> &middot; <?= e(setting('tanggal_pemilihan')) ?></p>

      <!-- ====== FOTO PASANGAN CALON: SEMUA FOTO TAMPIL SEKALIGUS ======
           Permintaan pemilik: bukan slider — semua foto paslon langsung tampil
           bersamaan, jumlah kolomnya mengikuti banyaknya paslon supaya ukuran
           fotonya menyesuaikan ruang yang tersedia pada kartu biru ini. -->
      <?php
      $jmlPaslon   = count($paslonTampil);
      $kolomBesar  = min(3, max(1, $jmlPaslon));   /* layar lebar: maks 3 per baris */
      $kolomSedang = min(2, max(1, $jmlPaslon));   /* tablet: maks 2 per baris      */
      ?>
      <div class="mt-6" aria-label="Foto pasangan calon">
        <?php if ($paslonTampil): ?>
          <div class="foto-grid<?= $jmlPaslon === 1 ? ' satu' : '' ?>"
               style="--kolom:<?= (int) $kolomBesar ?>;--kolom-2:<?= (int) $kolomSedang ?>">
            <?php foreach ($paslonTampil as $i => $p):
                $foto = foto_paslon($p); ?>
              <figure class="foto-paslon">
                <?php if ($foto !== null): ?>
                  <img src="<?= e($foto) ?>" alt="Foto pasangan calon nomor <?= (int) $p['nomor'] ?>"
                       loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                       onerror="this.classList.add('hidden')">
                <?php else: ?>
                  <div class="grid h-full w-full place-items-center bg-gradient-to-br from-brand-700 to-brand-900">
                    <span class="grid h-16 w-16 place-items-center rounded-full bg-white/15 text-xl font-black ring-2 ring-white/25">
                      <?= e(strtoupper(substr((string) $p['ketua'], 0, 1) . substr((string) $p['wakil'], 0, 1))) ?>
                    </span>
                  </div>
                <?php endif; ?>
                <figcaption class="foto-paslon-nomor">Paslon <?= (int) $p['nomor'] ?></figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
          <p class="mt-3 text-center text-xs font-semibold text-brand-200">
            <?= e(angka($jmlPaslon)) ?> pasangan calon &mdash; baca visi &amp; misinya di bilik suara
            sebelum memberikan suara.
          </p>
        <?php else: ?>
          <div class="grid aspect-[16/10] w-full place-items-center rounded-2xl bg-white/5 text-center ring-1 ring-white/10">
            <div class="px-6">
              <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-white/10 text-brand-100"><?= ikon('paslon', 'h-7 w-7') ?></span>
              <p class="mt-3 text-sm font-bold">Foto pasangan calon akan tampil di sini</p>
              <p class="mt-1 text-xs text-brand-200">Panitia belum menambahkan pasangan calon.</p>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- ============== CEPAT &middot; RAHASIA &middot; TRANSPARAN ============== -->
      <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <?php
        $keunggulan = [
            ['ikon' => 'jam',    'judul' => 'Cepat',      'teks' => 'Satu ketukan, suara langsung tercatat.'],
            ['ikon' => 'kunci',  'judul' => 'Rahasia',    'teks' => 'Surat suara tidak menyimpan identitas pemilih.'],
            ['ikon' => 'chart',  'judul' => 'Transparan', 'teks' => 'Hasil dihitung otomatis & dapat dipantau.'],
        ];
        foreach ($keunggulan as $k): ?>
          <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-500/25 text-emerald-100"><?= ikon($k['ikon'], 'h-5 w-5') ?></span>
            <p class="mt-2 text-sm font-bold"><?= e($k['judul']) ?></p>
            <p class="mt-1 text-xs leading-relaxed text-brand-200"><?= e($k['teks']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Petunjuk pemakaian -->
    <div class="kartu kartu-pad mt-6">
      <h2 class="kartu-judul">Cara memberikan suara</h2>
      <ol class="mt-4 grid gap-3 sm:grid-cols-2">
        <?php
        $langkah = [
            'Minta <b>token</b> kepada panitia ' . e(istilah_osis()) . ' (satu token untuk satu pemilih).',
            'Masukkan <b>NISN</b> (siswa) atau <b>NIK</b> (guru) beserta token, lalu tekan Masuk.',
            'Baca visi, misi, dan program tiap pasangan calon.',
            'Tekan <b>PILIH PASLON</b>, konfirmasi, dan suara Anda langsung tersimpan.',
        ];
        foreach ($langkah as $i => $l): ?>
          <li class="flex gap-3 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-brand-600 text-sm font-black text-white"><?= $i + 1 ?></span>
            <p class="text-sm leading-relaxed text-slate-600"><?= $l ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ========================= KOLOM KANAN: MASUK ========================= -->
  <section id="masuk" class="animate-masuk">
    <div class="kartu kartu-pad lg:sticky lg:top-24">
      <div class="flex rounded-2xl bg-slate-100 p-1" role="tablist" aria-label="Pilih jenis pengguna"
           data-tab-awal="<?= e($tab) ?>">
        <button type="button" role="tab" data-tab="pemilih" aria-controls="panelPemilih"
                class="flex-1 rounded-xl px-3 py-2 text-sm font-bold transition">Pemilih</button>
        <button type="button" role="tab" data-tab="panitia" aria-controls="panelPanitia"
                class="flex-1 rounded-xl px-3 py-2 text-sm font-bold transition">Panitia</button>
      </div>

      <!-- Panel PEMILIH -->
      <div id="panelPemilih" class="mt-5" role="tabpanel">
        <h2 class="kartu-judul">Masuk Pemilih</h2>
        <p class="kartu-ket">Gunakan NISN (siswa) atau NIK (guru &amp; tendik) beserta token dari panitia.</p>

        <form method="post" action="login.php?peran=pemilih" class="mt-4 space-y-4" autocomplete="off">
          <?= csrf_field() ?>
          <div>
            <label class="label" for="nisn">NISN / NIK</label>
            <input class="isian" type="text" id="nisn" name="nisn" required inputmode="numeric"
                   placeholder="Contoh: 0071234501" autocomplete="off" spellcheck="false">
          </div>
          <div>
            <label class="label" for="token">Token Pemilih</label>
            <input class="isian isian-rapat text-center text-lg font-black uppercase" type="text" id="token" name="token"
                   required placeholder="XXXX-XXXX" maxlength="12" autocomplete="off" spellcheck="false"
                   data-token>
            <p class="catatan-kecil">Huruf besar/kecil dan tanda hubung tidak masalah.</p>
          </div>
          <button type="submit" class="tombol-utama w-full py-3 text-base">
            <?= ikon('kotak') ?> Masuk &amp; Beri Suara
          </button>
        </form>

        <p class="mt-4 rounded-xl bg-amber-50 px-3.5 py-3 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
          Token hanya bisa dipakai <b>satu kali</b>. Setelah memilih, Anda otomatis keluar dan tidak dapat memilih lagi.
        </p>
      </div>

      <!-- Panel PANITIA -->
      <div id="panelPanitia" class="mt-5 border-t border-slate-200 pt-5 lg:border-t-0 lg:pt-0" role="tabpanel">
        <h2 class="kartu-judul">Masuk Panitia / Pembina</h2>
        <p class="kartu-ket">Halaman ini untuk mengelola paslon, DPT, dan hasil pemilihan.</p>

        <form method="post" action="login.php?peran=admin" class="mt-4 space-y-4">
          <?= csrf_field() ?>
          <div>
            <label class="label" for="username">Nama Pengguna</label>
            <input class="isian" type="text" id="username" name="username" required autocomplete="username"
                   placeholder="admin" spellcheck="false">
          </div>
          <div>
            <label class="label" for="sandi">Kata Sandi</label>
            <input class="isian" type="password" id="sandi" name="sandi" required autocomplete="current-password"
                   placeholder="Kata sandi panitia">
          </div>
          <button type="submit" class="tombol-putih w-full py-3 text-base">
            <?= ikon('kunci') ?> Masuk Panel Panitia
          </button>
        </form>

        <p class="catatan-kecil">
          Akun bawaan: <b>admin</b> / <b>admin123</b> &mdash; segera ganti pada menu Pengaturan.
        </p>
      </div>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'publik']); ?>
