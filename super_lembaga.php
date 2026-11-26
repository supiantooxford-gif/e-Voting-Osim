<?php
/**
 * super_lembaga.php — KELOLA SEKOLAH / MADRASAH (khusus Super Admin).
 * ---------------------------------------------------------------------------
 * Super Admin (pengontrol utama) menambah lembaga, mengubah identitasnya,
 * membuat akun panitia pertamanya, menonaktifkan, atau menghapusnya.
 *
 * Aturan yang dijaga di server:
 *   - nama & jenis lembaga hanya dapat diubah di sini (bukan oleh panitia),
 *     nama lembaga menentukan identitas seluruh halaman lembaga itu
 *     (aplikasi ini khusus madrasah, istilahnya selalu OSIM);
 *   - lembaga yang sudah memiliki surat suara TIDAK dapat dihapus (hanya
 *     dinonaktifkan) supaya hasil pemilihan tidak hilang;
 *   - penghapusan wajib mengetik nama lembaga (huruf besar/kecil bebas).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$galat   = [];
$isian   = null;
$editId  = (int) ($_GET['edit'] ?? 0);
$tampilkanForm = false;

/* ============================ PEMROSESAN POST ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ------------------------------ Simpan ------------------------------ */
    if ($aksi === 'simpan') {
        $id = (int) ($_POST['id'] ?? 0);
        $isian = [
            'id'     => $id,
            'nama'   => batas_teks($_POST['nama'] ?? '', LEMBAGA_NAMA_MAKS),
            'jenis'  => (string) ($_POST['jenis'] ?? JENIS_LEMBAGA_BAWAAN),
            'npsn'   => (string) ($_POST['npsn'] ?? ''),
            'alamat' => batas_teks($_POST['alamat'] ?? '', 255),
        ];
        $akun = null;
        if ($id === 0) {
            $namaAkun = batas_teks($_POST['akun_nama'] ?? '', 120);
            $userAkun = akun_username_bersih($_POST['akun_username'] ?? '');
            $sandi    = (string) ($_POST['akun_sandi'] ?? '');
            if ($namaAkun !== '' || $userAkun !== '' || $sandi !== '') {
                $akun = ['nama' => $namaAkun, 'username' => $userAkun, 'sandi' => $sandi];
            }
        }

        if ($id > 0) {
            $hasil = lembaga_ubah($id, $isian);
        } else {
            $hasil = lembaga_tambah($isian, $akun, isset($_POST['dengan_contoh']));
        }

        if (!$hasil['ok']) {
            $galat[] = (string) $hasil['pesan'];
            $tampilkanForm = true;
            $editId = $id;
        } else {
            catat_aktivitas($id > 0 ? 'lembaga_ubah' : 'lembaga_tambah',
                $hasil['pesan'], $hasil['id'] > 0 ? $hasil['id'] : null);
            flash('ok', $hasil['pesan'] . ($akun !== null && $id === 0 ? ' Akun panitia juga sudah dibuat.' : ''));
            redirect('super_lembaga.php');
        }
    }

    /* ----------------------- Aktifkan / nonaktifkan ---------------------- */
    if ($aksi === 'aktif_set') {
        $id    = (int) ($_POST['id'] ?? 0);
        $l     = lembaga_by_id($id);
        $baru  = ((string) ($_POST['aktif'] ?? '0')) === '1';
        if ($l === false) {
            flash('galat', 'Lembaga tidak ditemukan.');
        } elseif (!$baru && (int) nilai('SELECT COUNT(*) FROM lembaga WHERE aktif = 1') <= 1) {
            flash('galat', 'Lembaga aktif terakhir tidak dapat dinonaktifkan — aplikasi harus memiliki minimal satu lembaga aktif.');
        } else {
            lembaga_aktif_set($id, $baru);
            catat_aktivitas('lembaga_status', ($baru ? 'Mengaktifkan' : 'Menonaktifkan') . ' lembaga ' . $l['nama'], $id);
            flash('ok', 'Lembaga "' . $l['nama'] . '" ' . ($baru ? 'diaktifkan.' : 'dinonaktifkan. Pemilih & panitianya tidak dapat masuk sampai diaktifkan lagi.'));
        }
        redirect('super_lembaga.php');
    }

    /* ---------------------------- Logo madrasah -------------------------- */
    /* Logo Komisi Pemilihan OSIM: diunggah lewat media proxy platform, dan
       yang disimpan ke basis data HANYA URL CDN hasil respons. */
    if ($aksi === 'logo_unggah' || $aksi === 'logo_hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $l  = lembaga_by_id($id);
        if ($l === false) {
            flash('galat', 'Lembaga tidak ditemukan.');
            redirect('super_lembaga.php');
        }

        if ($aksi === 'logo_hapus') {
            lembaga_logo_set($id, null);
            catat_aktivitas('lembaga_logo', 'Logo lembaga ' . $l['nama'] . ' dihapus.', $id);
            flash('ok', 'Logo lembaga "' . $l['nama'] . '" dihapus. Kartu kembali memakai inisial.');
            redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
        }

        $berkas = $_FILES['logo'] ?? [];
        if ((int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('galat', 'Pilih berkas logo terlebih dahulu, lalu tekan Unggah Logo.');
            redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
        }
        if ((int) ($berkas['size'] ?? 0) > LOGO_MAKS_BYTE) {
            flash('galat', 'Ukuran logo melebihi 2 MB. Perkecil gambar lalu unggah lagi.');
            redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
        }
        $periksa = validasi_gambar($berkas);
        if (!$periksa['ok']) {
            flash('galat', (string) $periksa['pesan']);
            redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
        }
        $unggah = media_upload((string) $berkas['tmp_name'], (string) $berkas['name']);
        if (!$unggah['ok']) {
            flash('galat', (string) $unggah['pesan']);
            redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
        }
        lembaga_logo_set($id, (string) $unggah['url']);
        catat_aktivitas('lembaga_logo', 'Logo lembaga ' . $l['nama'] . ' diperbarui.', $id);
        flash('ok', 'Logo lembaga "' . $l['nama'] . '" berhasil disimpan.');
        redirect('super_lembaga.php?edit=' . $id . '#zonaLogo');
    }

    /* ------------------------------- Hapus ------------------------------ */
    if ($aksi === 'hapus') {
        $id    = (int) ($_POST['id'] ?? 0);
        $ketik = trim((string) ($_POST['konfirmasi'] ?? ''));
        $l     = lembaga_by_id($id);
        if ($l === false) {
            flash('galat', 'Lembaga tidak ditemukan.');
        } elseif (mb_strtolower($ketik) !== mb_strtolower(trim((string) $l['nama']))) {
            flash('galat', 'Ketik nama lembaga dengan tepat pada kolom konfirmasi untuk menghapusnya.');
            redirect('super_lembaga.php?edit=' . $id . '#zonaHapus');
        } else {
            $hasil = lembaga_hapus($id);
            catat_aktivitas('lembaga_hapus', $hasil['pesan']);
            flash($hasil['ok'] ? 'ok' : 'galat', (string) $hasil['pesan']);
        }
        redirect('super_lembaga.php');
    }
}

/* ================================ PENYIAPAN ================================ */
$lembaga = lembaga_semua(true);
$stat    = lembaga_statistik_semua();
$akun    = akun_panitia_jumlah();

if ($editId > 0 && !$tampilkanForm) {
    $l = lembaga_by_id($editId);
    if ($l !== false) {
        $isian = [
            'id' => (int) $l['id'], 'nama' => (string) $l['nama'], 'jenis' => (string) $l['jenis'],
            'npsn' => (string) ($l['npsn'] ?? ''), 'alamat' => (string) ($l['alamat'] ?? ''),
        ];
    }
}
if ($isian === null) {
    $isian = ['id' => 0, 'nama' => '', 'jenis' => JENIS_LEMBAGA_BAWAAN, 'npsn' => '', 'alamat' => ''];
}
$sedangUbah = (int) $isian['id'] > 0;
$dataUbah   = $sedangUbah ? lembaga_by_id((int) $isian['id']) : false;

page_top('Kelola Lembaga', [
    'shell' => 'super',
    'sub'   => count($lembaga) . ' madrasah terdaftar',
    'aksi'  => '<a href="super.php" class="tombol-putih tombol-kecil">' . ikon('dashboard', 'h-4 w-4') . ' Ringkasan</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk mb-4">
    <?= ikon('info') ?>
    <div>
      <p class="font-bold">Ada isian yang perlu diperbaiki:</p>
      <ul class="mt-1 list-inside list-disc space-y-0.5">
        <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="grid gap-5 xl:grid-cols-[minmax(0,27rem)_minmax(0,1fr)]">

  <!-- ========================= FORMULIR LEMBAGA ========================= -->
  <div class="min-w-0 space-y-5">
    <section class="kartu kartu-pad" id="formLembaga">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="kartu-judul"><?= $sedangUbah ? 'Ubah Lembaga' : 'Tambah Lembaga' ?></h2>
          <p class="kartu-ket">Aplikasi ini khusus madrasah; istilah kegiatan selalu OSIM.</p>
        </div>
        <?php if ($sedangUbah): ?>
          <a href="super_lembaga.php" class="tombol-putih tombol-kecil">Batal</a>
        <?php endif; ?>
      </div>

      <form method="post" action="super_lembaga.php" class="mt-4 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) $isian['id'] ?>">

        <div>
          <label class="label" for="nama">Nama Madrasah</label>
          <input class="isian" type="text" id="nama" name="nama" required maxlength="<?= LEMBAGA_NAMA_MAKS ?>"
                 value="<?= e((string) $isian['nama']) ?>" placeholder="mis. MA. Sirojuth Tholibiin Mootilango">
        </div>

        <div class="rounded-2xl bg-brand-50 p-3.5 ring-1 ring-brand-200">
          <p class="label !mb-1">Jenis Lembaga</p>
          <p class="text-sm font-bold text-brand-900"><?= e(JENIS_LEMBAGA_NAMA[JENIS_LEMBAGA_BAWAAN]) ?></p>
          <p class="catatan-kecil">Aplikasi ini khusus madrasah, jadi jenisnya tidak perlu dipilih.</p>
        </div>

        <div>
          <label class="label" for="npsn">NPSN <span class="font-normal text-slate-500">(opsional, 8 angka)</span></label>
          <input class="isian" type="text" id="npsn" name="npsn" inputmode="numeric" maxlength="8"
                 value="<?= e((string) $isian['npsn']) ?>" placeholder="mis. 20212345">
          <p class="catatan-kecil">Dipakai untuk mengenali lembaga; harus unik antar lembaga.</p>
        </div>

        <div>
          <label class="label" for="alamat">Alamat <span class="font-normal text-slate-500">(opsional)</span></label>
          <textarea class="isian" id="alamat" name="alamat" rows="2" maxlength="255"
                    placeholder="mis. Jl. Trans Sulawesi, Mootilango"><?= e((string) $isian['alamat']) ?></textarea>
        </div>

        <?php if (!$sedangUbah): ?>
          <!-- Akun panitia pertama (opsional) — dibuat bersama lembaganya. -->
          <div class="rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200">
            <p class="text-sm font-bold text-brand-900"><?= ikon('akun', 'h-4 w-4 inline') ?> Akun Panitia Pertama (opsional)</p>
            <p class="mt-1 text-xs text-brand-800">
              Isi ketiga kolom di bawah untuk langsung membuat akun panitia lembaga ini. Bila dikosongkan,
              lembaga tetap dibuat dan akun panitia dapat ditambahkan kemudian lewat menu <b>Akun Panitia</b>.
            </p>
            <div class="mt-3 space-y-3">
              <div>
                <label class="label" for="akun_nama">Nama Pengelola</label>
                <input class="isian" type="text" id="akun_nama" name="akun_nama" maxlength="120"
                       placeholder="mis. Panitia Pemilihan">
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="label" for="akun_username">Nama Pengguna</label>
                  <input class="isian" type="text" id="akun_username" name="akun_username" maxlength="60"
                         autocomplete="off" spellcheck="false" placeholder="mis. panitia-ma">
                </div>
                <div>
                  <label class="label" for="akun_sandi">Kata Sandi</label>
                  <input class="isian" type="text" id="akun_sandi" name="akun_sandi" maxlength="100"
                         autocomplete="off" placeholder="min. <?= SANDI_MIN ?> karakter">
                </div>
              </div>
              <p class="catatan-kecil">Sandi ditampilkan sebagai teks biasa di sini supaya mudah dicatat &amp;
                diserahkan kepada panitia. Mintalah panitia menggantinya setelah masuk.</p>
            </div>
          </div>

          <label class="flex items-start gap-2.5 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200">
            <input type="checkbox" name="dengan_contoh" value="1"
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200">
            <span class="text-xs text-slate-600">
              <b class="text-slate-800">Sertakan data contoh</b><br>
              3 pasangan calon + 20 pemilih contoh (untuk uji coba/gladi bersih). Dapat dihapus lagi lewat
              menu <b>Reset Data</b> lembaga tersebut.
            </span>
          </label>
        <?php endif; ?>

        <button type="submit" class="tombol-utama w-full py-3">
          <?= ikon($sedangUbah ? 'cek' : 'tambah') ?> <?= $sedangUbah ? 'Simpan Perubahan' : 'Tambahkan Lembaga' ?>
        </button>
      </form>
    </section>

    <?php if ($sedangUbah && $dataUbah !== false):
        $logoKini = lembaga_logo($dataUbah); ?>
      <!-- ========================== LOGO MADRASAH ========================== -->
      <section class="kartu kartu-pad" id="zonaLogo">
        <h2 class="kartu-judul">Logo Madrasah</h2>
        <p class="kartu-ket">
          Logo Komisi Pemilihan OSIM lembaga ini. Tampil pada kartu di halaman depan dan pada
          kepala halaman masuk lembaga.
        </p>

        <div class="mt-4 flex items-center gap-4">
          <span class="lembaga-logo !h-16 !w-16">
            <?php if ($logoKini !== null): ?>
              <img src="<?= e($logoKini) ?>" alt="Logo <?= e((string) $dataUbah['nama']) ?>">
            <?php else: ?>
              <?= e(lembaga_inisial($dataUbah)) ?>
            <?php endif; ?>
          </span>
          <p class="min-w-0 flex-1 text-xs leading-relaxed text-slate-500">
            <?= $logoKini !== null
                ? 'Logo sudah terpasang. Unggah berkas baru untuk menggantinya.'
                : 'Belum ada logo — kartu memakai inisial nama madrasah.' ?>
          </p>
        </div>

        <form method="post" action="super_lembaga.php" enctype="multipart/form-data" class="mt-4 space-y-3">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="logo_unggah">
          <input type="hidden" name="id" value="<?= (int) $dataUbah['id'] ?>">
          <div>
            <label class="label" for="logo">Berkas Logo <span class="font-normal text-slate-500">(maks. 2 MB)</span></label>
            <input class="isian !py-2 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-brand-700"
                   type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
            <p class="catatan-kecil">Format JPG, PNG, GIF, WEBP, atau SVG. Sebaiknya gambar persegi dengan latar transparan.</p>
          </div>
          <button type="submit" class="tombol-utama w-full"><?= ikon('tambah', 'h-4 w-4') ?> Unggah Logo</button>
        </form>

        <?php if ($logoKini !== null): ?>
          <form method="post" action="super_lembaga.php" class="mt-2"
                data-konfirmasi data-pesan="Hapus logo lembaga &quot;<?= e((string) $dataUbah['nama']) ?>&quot;?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="logo_hapus">
            <input type="hidden" name="id" value="<?= (int) $dataUbah['id'] ?>">
            <button type="submit" class="tombol-putih w-full"><?= ikon('hapus', 'h-4 w-4') ?> Hapus Logo</button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($sedangUbah && $dataUbah !== false): ?>
      <!-- ======================== ZONA STATUS & HAPUS ======================== -->
      <section class="kartu kartu-pad">
        <h2 class="kartu-judul">Status Lembaga</h2>
        <p class="kartu-ket">
          Lembaga nonaktif tidak muncul di halaman depan; pemilih &amp; panitianya tidak dapat masuk,
          tetapi seluruh datanya tetap tersimpan.
        </p>
        <form method="post" action="super_lembaga.php" class="mt-3">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="aktif_set">
          <input type="hidden" name="id" value="<?= (int) $dataUbah['id'] ?>">
          <input type="hidden" name="aktif" value="<?= (int) $dataUbah['aktif'] === 1 ? '0' : '1' ?>">
          <button type="submit" class="<?= (int) $dataUbah['aktif'] === 1 ? 'tombol-putih' : 'tombol-ok' ?> w-full">
            <?= ikon((int) $dataUbah['aktif'] === 1 ? 'tutup' : 'cek', 'h-4 w-4') ?>
            <?= (int) $dataUbah['aktif'] === 1 ? 'Nonaktifkan Lembaga Ini' : 'Aktifkan Lembaga Ini' ?>
          </button>
        </form>
        <div class="mt-3 flex flex-wrap gap-2">
          <a href="super_akun.php?lembaga=<?= (int) $dataUbah['id'] ?>" class="tombol-putih tombol-kecil">
            <?= ikon('akun', 'h-4 w-4') ?> Akun Panitia (<?= e(angka($akun[(int) $dataUbah['id']] ?? 0)) ?>)
          </a>
          <?php if ((int) $dataUbah['aktif'] === 1): ?>
            <form method="post" action="super_masuk_lembaga.php">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="masuk">
              <input type="hidden" name="lembaga" value="<?= (int) $dataUbah['id'] ?>">
              <input type="hidden" name="tujuan" value="admin.php">
              <button type="submit" class="tombol-utama tombol-kecil"><?= ikon('setelan', 'h-4 w-4') ?> Kelola Lembaga Ini</button>
            </form>
          <?php endif; ?>
        </div>
      </section>

      <section class="kartu kartu-pad" id="zonaHapus">
        <h2 class="kartu-judul text-rose-700">Hapus Lembaga</h2>
        <p class="kartu-ket">
          Menghapus lembaga <b><?= e((string) $dataUbah['nama']) ?></b> beserta seluruh paslon, DPT, surat suara,
          pengaturan, dan akun panitianya. Tindakan ini tidak dapat dibatalkan.
        </p>
        <form method="post" action="super_lembaga.php" class="mt-3 space-y-3"
              data-konfirmasi data-pesan="Hapus lembaga &quot;<?= e((string) $dataUbah['nama']) ?>&quot; beserta SELURUH datanya? Tindakan ini tidak dapat dibatalkan.">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int) $dataUbah['id'] ?>">
          <div>
            <label class="label" for="konfirmasiHapus">
              Ketik nama lembaga untuk melanjutkan: <b><?= e((string) $dataUbah['nama']) ?></b>
            </label>
            <input class="isian" type="text" id="konfirmasiHapus" name="konfirmasi" required
                   placeholder="Ketik nama lembaga" autocomplete="off">
          </div>
          <button type="submit" class="tombol-bahaya w-full"><?= ikon('hapus', 'h-4 w-4') ?> Hapus Lembaga</button>
          <p class="catatan-kecil">
            Lembaga yang sudah memiliki surat suara tidak dapat dihapus &mdash; gunakan <b>Nonaktifkan</b>
            agar hasil pemilihannya tetap tersimpan.
          </p>
        </form>
      </section>
    <?php endif; ?>
  </div>

  <!-- ========================== DAFTAR LEMBAGA ========================== -->
  <section class="kartu kartu-pad min-w-0">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="kartu-judul">Daftar Madrasah</h2>
      <span class="pill-abu"><?= ikon('lembaga', 'h-3.5 w-3.5') ?> <?= e(angka(count($lembaga))) ?> lembaga</span>
    </div>

    <div class="bungkus-tabel mt-4">
      <table class="tabel !min-w-[52rem]">
        <thead>
          <tr>
            <th class="w-12">No</th>
            <th>Madrasah</th>
            <th class="w-24">Jenis</th>
            <th class="w-32">NPSN</th>
            <th class="w-16">DPT</th>
            <th class="w-24">Panitia</th>
            <th class="w-24">Status</th>
            <th class="w-32">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$lembaga): ?>
            <tr><td colspan="8" class="py-8 text-center text-slate-500">
              Belum ada lembaga. Gunakan formulir di samping untuk menambahkan yang pertama.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($lembaga as $i => $l):
              $s = $stat[(int) $l['id']] ?? ['dpt' => 0]; ?>
            <tr>
              <td class="text-slate-400"><?= $i + 1 ?></td>
              <td>
                <div class="flex items-center gap-2.5">
                  <?php $logoBaris = lembaga_logo($l); ?>
                  <span class="lembaga-logo !h-9 !w-9 !rounded-xl !text-[10px]">
                    <?php if ($logoBaris !== null): ?>
                      <img src="<?= e($logoBaris) ?>" alt="Logo <?= e((string) $l['nama']) ?>" loading="lazy">
                    <?php else: ?>
                      <?= e(lembaga_inisial($l)) ?>
                    <?php endif; ?>
                  </span>
                  <span class="min-w-0">
                    <span class="block text-sm font-bold text-slate-800"><?= e((string) $l['nama']) ?></span>
                    <?php if (trim((string) ($l['alamat'] ?? '')) !== ''): ?>
                      <span class="block text-[11px] text-slate-500"><?= e((string) $l['alamat']) ?></span>
                    <?php endif; ?>
                  </span>
                </div>
              </td>
              <td>
                <span class="<?= lembaga_jenis_sah((string) $l['jenis']) === 'madrasah' ? 'pill-abu' : 'pill-ok' ?>">
                  <?= e(JENIS_LEMBAGA_PENDEK[lembaga_jenis_sah((string) $l['jenis'])]) ?>
                </span>
              </td>
              <td class="font-mono text-xs text-slate-600"><?= e((string) ($l['npsn'] ?: '-')) ?></td>
              <td class="text-sm font-semibold text-slate-700"><?= e(angka((int) $s['dpt'])) ?></td>
              <td class="text-sm font-semibold text-slate-700"><?= e(angka($akun[(int) $l['id']] ?? 0)) ?></td>
              <td><?= (int) $l['aktif'] === 1 ? '<span class="pill-ok">Aktif</span>' : '<span class="pill-abu">Nonaktif</span>' ?></td>
              <td>
                <div class="flex flex-wrap gap-1.5">
                  <a href="super_lembaga.php?edit=<?= (int) $l['id'] ?>#formLembaga" class="tombol-putih tombol-kecil">
                    <?= ikon('edit', 'h-3.5 w-3.5') ?> Ubah
                  </a>
                  <?php if ((int) $l['aktif'] === 1): ?>
                    <a href="masuk.php?lembaga=<?= (int) $l['id'] ?>" class="tombol-putih tombol-kecil" title="Halaman masuk lembaga">
                      <?= ikon('panah', 'h-3.5 w-3.5') ?>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="notis-info mt-4">
      <?= ikon('info') ?>
      <p>Data setiap lembaga <b>terpisah</b>: paslon, DPT, token, dan surat suara tidak pernah bercampur
        antar madrasah, meskipun nomor induk pemilihnya sama.</p>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'super']); ?>
