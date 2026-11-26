<?php
/**
 * super_depan.php — ATUR TEKS HALAMAN DEPAN (khusus Super Admin).
 * ---------------------------------------------------------------------------
 * Permintaan pemilik (v2.3.0): judul dan keterangan besar pada halaman depan
 * dapat diubah sendiri oleh Super Admin, tanpa menyunting kode.
 *
 * Nilainya disimpan sebagai pengaturan TINGKAT APLIKASI
 * (tabel `pengaturan`, lembaga_id = 0) pada kunci `depan_judul` & `depan_ket`
 * yang sudah terdaftar di PENGATURAN_GLOBAL_BAWAAN (inc/config.php).
 * Mengosongkan isian berarti kembali ke teks bawaan.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$galat = [];

/* ============================ PEMROSESAN POST ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $judul = batas_teks($_POST['depan_judul'] ?? '', 120);
        $ket   = batas_teks($_POST['depan_ket'] ?? '', 400);

        set_setting_lembaga(0, 'depan_judul', trim($judul));
        set_setting_lembaga(0, 'depan_ket', trim($ket));
        catat_aktivitas('depan_ubah', 'Teks halaman depan diperbarui.');
        flash('ok', 'Teks halaman depan berhasil disimpan.');
        redirect('super_depan.php');
    }

    if ($aksi === 'bawaan') {
        set_setting_lembaga(0, 'depan_judul', '');
        set_setting_lembaga(0, 'depan_ket', '');
        catat_aktivitas('depan_ubah', 'Teks halaman depan dikembalikan ke bawaan.');
        flash('ok', 'Teks halaman depan dikembalikan ke bawaan.');
        redirect('super_depan.php');
    }

    /* ------------------ LOGO KOMISI PEMILIHAN OSIM (v2.4.0) ------------------
       Diunggah lewat media proxy platform; yang disimpan ke basis data HANYA
       URL CDN hasil respons. Panel logo halaman depan memakai SATU sumber
       aturan: depan_logo() / depan_logo_set() (inc/helpers.php).            */
    if ($aksi === 'logo_unggah' || $aksi === 'logo_hapus') {
        if ($aksi === 'logo_hapus') {
            depan_logo_set(null);
            catat_aktivitas('depan_logo', 'Logo halaman depan dihapus.');
            flash('ok', 'Logo halaman depan dihapus. Panel logo kembali memakai monogram OSIM.');
            redirect('super_depan.php#zonaLogoDepan');
        }

        $berkas = $_FILES['logo'] ?? [];
        if ((int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('galat', 'Pilih berkas logo terlebih dahulu, lalu tekan Unggah Logo.');
            redirect('super_depan.php#zonaLogoDepan');
        }
        if ((int) ($berkas['size'] ?? 0) > LOGO_MAKS_BYTE) {
            flash('galat', 'Ukuran logo melebihi 2 MB. Perkecil gambar lalu unggah lagi.');
            redirect('super_depan.php#zonaLogoDepan');
        }
        $periksa = validasi_gambar($berkas);
        if (!$periksa['ok']) {
            flash('galat', (string) $periksa['pesan']);
            redirect('super_depan.php#zonaLogoDepan');
        }
        $unggah = media_upload((string) $berkas['tmp_name'], (string) $berkas['name']);
        if (!$unggah['ok']) {
            flash('galat', (string) $unggah['pesan']);
            redirect('super_depan.php#zonaLogoDepan');
        }
        depan_logo_set((string) $unggah['url']);
        catat_aktivitas('depan_logo', 'Logo halaman depan diperbarui.');
        flash('ok', 'Logo Komisi Pemilihan OSIM berhasil disimpan.');
        redirect('super_depan.php#zonaLogoDepan');
    }
}

/* ================================ PENYIAPAN ================================ */
$judulTersimpan = setting('depan_judul');
$ketTersimpan   = setting('depan_ket');
$logoKini       = depan_logo();

page_top('Halaman Depan', [
    'shell' => 'super',
    'sub'   => 'Judul & keterangan besar pada halaman depan',
    'aksi'  => '<a href="index.php" target="_blank" rel="noopener" class="tombol-putih tombol-kecil">'
        . ikon('panah', 'h-4 w-4') . ' Lihat Halaman Depan</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk mb-4">
    <?= ikon('info') ?>
    <div>
      <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">

 <div class="min-w-0 space-y-5">

  <!-- ============================ FORMULIR ============================ -->
  <section class="kartu kartu-pad min-w-0" id="formDepan">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul">Teks Halaman Depan</h2>
        <p class="kartu-ket">Perubahan langsung tampil pada halaman depan aplikasi.</p>
      </div>
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emas-50 text-emas-700 ring-1 ring-emas-200">
        <?= ikon('setelan', 'h-5 w-5') ?>
      </span>
    </div>

    <form method="post" action="super_depan.php" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">

      <div>
        <label class="label" for="depan_judul">Judul Besar</label>
        <input class="isian" type="text" id="depan_judul" name="depan_judul" maxlength="120"
               value="<?= e($judulTersimpan) ?>"
               placeholder="<?= e(PENGATURAN_GLOBAL_BAWAAN['depan_judul']) ?>">
        <p class="catatan-kecil">Contoh: <b>Pilih Madrasah Anda</b>. Kosongkan untuk memakai teks bawaan.</p>
      </div>

      <div>
        <label class="label" for="depan_ket">Keterangan</label>
        <textarea class="isian" id="depan_ket" name="depan_ket" rows="5" maxlength="400"
                  placeholder="<?= e(PENGATURAN_GLOBAL_BAWAAN['depan_ket']) ?>"><?= e($ketTersimpan) ?></textarea>
        <p class="catatan-kecil">Baris baru akan ditampilkan sebagai baris baru. Kosongkan untuk teks bawaan.</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <button type="submit" class="tombol-utama"><?= ikon('cek', 'h-4 w-4') ?> Simpan Teks</button>
        <button type="submit" name="aksi" value="bawaan" class="tombol-putih"
                formnovalidate><?= ikon('reset', 'h-4 w-4') ?> Kembalikan ke Bawaan</button>
      </div>
    </form>
  </section>

  <!-- ==================== LOGO KOMISI PEMILIHAN OSIM ==================== -->
  <section class="kartu kartu-pad min-w-0" id="zonaLogoDepan">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul">Logo Komisi Pemilihan OSIM</h2>
        <p class="kartu-ket">
          Tampil besar pada panel logo di sebelah kiri daftar madrasah halaman depan.
        </p>
      </div>
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emas-50 text-emas-700 ring-1 ring-emas-200">
        <?= ikon('lembaga', 'h-5 w-5') ?>
      </span>
    </div>

    <div class="mt-4 flex items-center gap-4">
      <span class="depan-logo-bingkai !mx-0 !w-20 !max-w-[5rem] !shrink-0 !p-1.5">
        <?php if ($logoKini !== null): ?>
          <img src="<?= e($logoKini) ?>" alt="Logo Komisi Pemilihan OSIM">
        <?php else: ?>
          <span class="depan-logo-kosong !text-base">OSIM</span>
        <?php endif; ?>
      </span>
      <p class="min-w-0 flex-1 text-xs leading-relaxed text-slate-500">
        <?= $logoKini !== null
            ? 'Logo sudah terpasang. Unggah berkas baru untuk menggantinya.'
            : 'Belum ada logo — panel memakai monogram OSIM.' ?>
      </p>
    </div>

    <form method="post" action="super_depan.php" enctype="multipart/form-data" class="mt-4 space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="logo_unggah">
      <div>
        <label class="label" for="logo_depan">Berkas Logo <span class="font-normal text-slate-500">(maks. 2 MB)</span></label>
        <input class="isian !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-brand-700"
               type="file" id="logo_depan" name="logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
        <p class="catatan-kecil">Format JPG, PNG, GIF, WEBP, atau SVG. Sebaiknya gambar persegi dengan latar transparan.</p>
      </div>
      <button type="submit" class="tombol-utama"><?= ikon('tambah', 'h-4 w-4') ?> Unggah Logo</button>
    </form>

    <?php if ($logoKini !== null): ?>
      <form method="post" action="super_depan.php" class="mt-2"
            data-konfirmasi data-pesan="Hapus logo Komisi Pemilihan OSIM dari halaman depan?">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="logo_hapus">
        <button type="submit" class="tombol-putih w-full"><?= ikon('hapus', 'h-4 w-4') ?> Hapus Logo</button>
      </form>
    <?php endif; ?>
  </section>

 </div>

  <!-- ============================ PRATINJAU ============================ -->
  <section class="kartu kartu-pad min-w-0">
    <h2 class="kartu-judul">Pratinjau</h2>
    <p class="kartu-ket">Teks yang sekarang tampil pada halaman depan.</p>

    <div class="depan-hero mt-4 !px-5 !py-6">
      <span class="depan-eyebrow"><?= ikon('kotak', 'h-3.5 w-3.5') ?> Pemilihan Elektronik</span>
      <h3 class="depan-judul !text-2xl"><?= nl2br(e($judulTersimpan !== '' ? $judulTersimpan : PENGATURAN_GLOBAL_BAWAAN['depan_judul'])) ?></h3>
      <p class="depan-ket"><?= nl2br(e($ketTersimpan !== '' ? $ketTersimpan : PENGATURAN_GLOBAL_BAWAAN['depan_ket'])) ?></p>
    </div>

    <div class="notis-info mt-4">
      <?= ikon('info') ?>
      <p>Judul dan keterangan ini berlaku untuk <b>semua madrasah</b> (halaman depan bersifat lintas lembaga).</p>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'super']); ?>
