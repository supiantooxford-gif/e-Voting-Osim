<?php
/**
 * admin_paslon.php — MANAJEMEN PASANGAN CALON (panel panitia).
 * ---------------------------------------------------------------------------
 * - Tambah / ubah paslon: nomor urut, nama & kelas ketua-wakil, visi, misi,
 *   program kerja, dan foto (unggah dari komputer atau tempel tautan).
 * - Aktif/nonaktifkan paslon tanpa menghapus datanya.
 * - Hapus hanya boleh bila paslon itu BELUM punya suara, supaya hasil
 *   pemilihan tidak pernah berubah setelah surat suara masuk.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

$galat        = [];
$isian        = null;   /* isian yang sedang disunting (dikembalikan bila galat) */
$tampilkanForm = false;
$editId       = (int) ($_GET['edit'] ?? 0);

/* =========================== PEMROSESAN POST =========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ---------------------------- Simpan paslon ---------------------------- */
    if ($aksi === 'simpan') {
        $id = (int) ($_POST['id'] ?? 0);
        $isian = [
            'id'          => $id,
            'nomor'       => (int) ($_POST['nomor'] ?? 0),
            'ketua'       => batas_teks($_POST['ketua'] ?? '', 120),
            'wakil'       => batas_teks($_POST['wakil'] ?? '', 120),
            'kelas_ketua' => batas_teks($_POST['kelas_ketua'] ?? '', 60),
            'kelas_wakil' => batas_teks($_POST['kelas_wakil'] ?? '', 60),
            'visi'        => batas_teks($_POST['visi'] ?? '', 600),
            'misi'        => batas_teks($_POST['misi'] ?? '', 4000),
            'program'     => batas_teks($_POST['program'] ?? '', 4000),
            'foto_url'    => trim((string) ($_POST['foto_url'] ?? '')),
            'aktif'       => isset($_POST['aktif']) ? 1 : 0,
            'hapus_foto'  => isset($_POST['hapus_foto']),
        ];

        if ($isian['nomor'] < 1) {
            $galat[] = 'Nomor urut wajib diisi dengan angka 1 atau lebih.';
        } elseif ((int) nilai('SELECT COUNT(*) FROM paslon WHERE nomor = ? AND id <> ?', [$isian['nomor'], $id]) > 0) {
            $galat[] = 'Nomor urut ' . $isian['nomor'] . ' sudah dipakai pasangan calon lain.';
        }
        if ($isian['ketua'] === '') {
            $galat[] = 'Nama calon ketua wajib diisi.';
        }
        if ($isian['wakil'] === '') {
            $galat[] = 'Nama calon wakil ketua wajib diisi.';
        }
        if ($isian['foto_url'] !== '' && !tautan_aman($isian['foto_url'])) {
            $galat[] = 'Tautan foto harus dimulai dengan http:// atau https://';
        }

        /* Foto: mulai dari foto lama, lalu diganti bila ada unggahan/tautan baru. */
        $lama = $id > 0 ? paslon_by_id($id) : false;
        $foto = $lama !== false ? (string) ($lama['foto'] ?? '') : '';
        if ($isian['hapus_foto']) {
            $foto = '';
        }

        $adaBerkas = isset($_FILES['foto']) && (int) ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($adaBerkas) {
            $periksa = validasi_gambar($_FILES['foto']);
            if (!$periksa['ok']) {
                $galat[] = (string) $periksa['pesan'];
            } else {
                $unggah = media_upload((string) $_FILES['foto']['tmp_name'], basename((string) $_FILES['foto']['name']));
                if ($unggah['ok']) {
                    $foto = (string) $unggah['url'];
                } else {
                    $galat[] = (string) $unggah['pesan'];
                }
            }
        } elseif (!$isian['hapus_foto'] && $isian['foto_url'] !== '') {
            $foto = $isian['foto_url'];
        }

        if ($galat) {
            /* Kembalikan isian ke formulir (tanpa mengalihkan halaman). */
            $tampilkanForm = true;
            $editId = $id;
        } else {
            $waktu = date('Y-m-d H:i:s');
            if ($id > 0) {
                jalankan(
                    'UPDATE paslon SET nomor = ?, ketua = ?, wakil = ?, kelas_ketua = ?, kelas_wakil = ?,
                            visi = ?, misi = ?, program = ?, foto = ?, aktif = ?, diubah = ? WHERE id = ?',
                    [$isian['nomor'], $isian['ketua'], $isian['wakil'], $isian['kelas_ketua'], $isian['kelas_wakil'],
                     $isian['visi'], $isian['misi'], $isian['program'], $foto, $isian['aktif'], $waktu, $id]
                );
                catat_aktivitas('paslon_ubah', 'Paslon nomor ' . $isian['nomor'] . ' diubah');
                flash('ok', 'Data pasangan calon nomor ' . $isian['nomor'] . ' diperbarui.');
            } else {
                q(
                    'INSERT INTO paslon (nomor, ketua, wakil, kelas_ketua, kelas_wakil, visi, misi, program, foto, aktif, dibuat)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$isian['nomor'], $isian['ketua'], $isian['wakil'], $isian['kelas_ketua'], $isian['kelas_wakil'],
                     $isian['visi'], $isian['misi'], $isian['program'], $foto, $isian['aktif'], $waktu]
                );
                catat_aktivitas('paslon_tambah', 'Paslon nomor ' . $isian['nomor'] . ' ditambahkan');
                flash('ok', 'Pasangan calon nomor ' . $isian['nomor'] . ' berhasil ditambahkan.');
            }
            redirect('admin_paslon.php');
        }
    }

    /* ------------------------ Aktifkan / nonaktifkan ------------------------ */
    if ($aksi === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $p  = paslon_by_id($id);
        if ($p === false) {
            flash('galat', 'Pasangan calon tidak ditemukan.');
        } else {
            $baru = (int) $p['aktif'] === 1 ? 0 : 1;
            jalankan('UPDATE paslon SET aktif = ?, diubah = ? WHERE id = ?', [$baru, date('Y-m-d H:i:s'), $id]);
            catat_aktivitas('paslon_status', 'Paslon nomor ' . $p['nomor'] . ' → ' . ($baru ? 'aktif' : 'nonaktif'));
            flash('ok', 'Paslon nomor ' . $p['nomor'] . ($baru ? ' diaktifkan kembali.' : ' dinonaktifkan (tidak muncul di bilik suara).'));
        }
        redirect('admin_paslon.php');
    }

    /* -------------------------------- Hapus -------------------------------- */
    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $p  = paslon_by_id($id);
        if ($p === false) {
            flash('galat', 'Pasangan calon tidak ditemukan.');
        } elseif ((int) nilai('SELECT COUNT(*) FROM suara WHERE paslon_id = ?', [$id]) > 0) {
            flash('galat', 'Paslon nomor ' . $p['nomor'] . ' sudah memiliki suara dan tidak dapat dihapus. '
                . 'Gunakan tombol Nonaktifkan agar tidak muncul lagi di bilik suara.');
        } else {
            jalankan('DELETE FROM paslon WHERE id = ?', [$id]);
            catat_aktivitas('paslon_hapus', 'Paslon nomor ' . $p['nomor'] . ' dihapus');
            flash('ok', 'Pasangan calon nomor ' . $p['nomor'] . ' dihapus.');
        }
        redirect('admin_paslon.php');
    }
}

/* ============================== PENYIAPAN ============================== */
$paslon = paslon_list(true);
$suara  = suara_per_paslon();

/* Mode ubah: ambil data dari basis data (kecuali sedang menampilkan galat). */
if ($editId > 0 && !$tampilkanForm) {
    $p = paslon_by_id($editId);
    if ($p !== false) {
        $isian = [
            'id' => (int) $p['id'], 'nomor' => (int) $p['nomor'], 'ketua' => (string) $p['ketua'],
            'wakil' => (string) $p['wakil'], 'kelas_ketua' => (string) $p['kelas_ketua'],
            'kelas_wakil' => (string) $p['kelas_wakil'], 'visi' => (string) $p['visi'],
            'misi' => (string) $p['misi'], 'program' => (string) $p['program'],
            'foto_url' => (string) $p['foto'], 'aktif' => (int) $p['aktif'], 'hapus_foto' => false,
        ];
    }
}
if ($isian === null) {
    $isian = ['id' => 0, 'nomor' => nomor_urut_baru(), 'ketua' => '', 'wakil' => '',
        'kelas_ketua' => '', 'kelas_wakil' => '', 'visi' => '', 'misi' => '', 'program' => '',
        'foto_url' => '', 'aktif' => 1, 'hapus_foto' => false];
}
$sedangUbah = (int) $isian['id'] > 0;
$fotoKini   = null;
if ($sedangUbah) {
    $p = paslon_by_id((int) $isian['id']);
    $fotoKini = $p !== false ? foto_paslon($p) : null;
}

page_top('Manajemen Pasangan Calon', [
    'shell' => 'admin',
    'sub'   => count($paslon) . ' pasangan calon terdaftar',
    'aksi'  => '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Papan Hasil</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk">
    <?= ikon('info') ?>
    <div>
      <p class="font-bold">Ada isian yang perlu diperbaiki:</p>
      <ul class="mt-1 list-inside list-disc space-y-0.5">
        <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="mt-3 grid gap-5 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">

  <!-- ======================== FORMULIR PASLON ======================== -->
  <section class="kartu kartu-pad h-fit xl:sticky xl:top-24" id="formPaslon">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul"><?= $sedangUbah ? 'Ubah Pasangan Calon' : 'Tambah Pasangan Calon' ?></h2>
        <p class="kartu-ket">Nomor urut, nama, visi-misi, program, dan foto.</p>
      </div>
      <?php if ($sedangUbah): ?>
        <a href="admin_paslon.php" class="tombol-putih tombol-kecil">Batal</a>
      <?php endif; ?>
    </div>

    <form method="post" action="admin_paslon.php" enctype="multipart/form-data" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) $isian['id'] ?>">

      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-1">
          <label class="label" for="nomor">Nomor Urut</label>
          <input class="isian text-center text-lg font-black" type="number" id="nomor" name="nomor" min="1" max="99"
                 required value="<?= e((string) $isian['nomor']) ?>">
        </div>
        <div class="col-span-2">
          <label class="label" for="ketua">Nama Calon Ketua</label>
          <input class="isian" type="text" id="ketua" name="ketua" required maxlength="120" value="<?= e((string) $isian['ketua']) ?>"
                 placeholder="mis. Ahmad Fauzi">
        </div>
      </div>

      <div>
        <label class="label" for="kelas_ketua">Kelas Calon Ketua</label>
        <input class="isian" type="text" id="kelas_ketua" name="kelas_ketua" maxlength="60" value="<?= e((string) $isian['kelas_ketua']) ?>"
               placeholder="mis. XI IPA 1">
      </div>

      <div>
        <label class="label" for="wakil">Nama Calon Wakil Ketua</label>
        <input class="isian" type="text" id="wakil" name="wakil" required maxlength="120" value="<?= e((string) $isian['wakil']) ?>"
               placeholder="mis. Nurul Hidayah">
      </div>

      <div>
        <label class="label" for="kelas_wakil">Kelas Calon Wakil</label>
        <input class="isian" type="text" id="kelas_wakil" name="kelas_wakil" maxlength="60" value="<?= e((string) $isian['kelas_wakil']) ?>"
               placeholder="mis. XI IPS 1">
      </div>

      <div>
        <label class="label" for="visi">Visi</label>
        <textarea class="isian" id="visi" name="visi" rows="3" maxlength="600"
                  placeholder="Satu kalimat visi pasangan calon"><?= e((string) $isian['visi']) ?></textarea>
      </div>

      <div>
        <label class="label" for="misi">Misi <span class="font-normal text-slate-500">(satu baris = satu poin)</span></label>
        <textarea class="isian" id="misi" name="misi" rows="5" placeholder="Mengadakan kegiatan untuk seluruh kelas&#10;Membuka kanal aspirasi siswa"><?= e((string) $isian['misi']) ?></textarea>
      </div>

      <div>
        <label class="label" for="program">Program Kerja Unggulan <span class="font-normal text-slate-500">(satu baris = satu poin)</span></label>
        <textarea class="isian" id="program" name="program" rows="5" placeholder="Festival Seni &amp; Kreativitas Siswa&#10;Kelas Aspirasi"><?= e((string) $isian['program']) ?></textarea>
      </div>

      <!-- Foto kandidat -->
      <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="label">Foto Pasangan Calon</p>
        <div class="flex items-start gap-4">
          <div class="h-24 w-20 shrink-0 overflow-hidden rounded-xl bg-white ring-1 ring-slate-300">
            <?php if ($fotoKini !== null && !$isian['hapus_foto']): ?>
              <img src="<?= e($fotoKini) ?>" alt="Foto paslon saat ini" class="h-full w-full object-cover" data-pratinjau-foto>
            <?php else: ?>
              <div class="grid h-full w-full place-items-center text-slate-400" data-pratinjau-kosong><?= ikon('paslon', 'h-8 w-8') ?></div>
              <img src="" alt="" class="hidden h-full w-full object-cover" data-pratinjau-foto>
            <?php endif; ?>
          </div>
          <div class="min-w-0 flex-1 space-y-3">
            <input class="isian !py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white"
                   type="file" id="foto" name="foto" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" data-foto>
            <input class="isian !py-2 text-xs" type="url" id="foto_url" name="foto_url"
                   value="<?= e((string) $isian['foto_url']) ?>" placeholder="atau tempel tautan foto (https://...)">
            <?php if ($fotoKini !== null): ?>
              <label class="flex items-center gap-2 text-xs font-semibold text-rose-700">
                <input type="checkbox" name="hapus_foto" value="1" <?= $isian['hapus_foto'] ? 'checked' : '' ?>
                       class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-200">
                Hapus foto yang sekarang
              </label>
            <?php endif; ?>
            <p class="text-[11px] text-slate-500">JPG/PNG/WEBP/SVG, maks 15 MB. Foto akan disimpan di server penyimpanan aplikasi.</p>
          </div>
        </div>
      </div>

      <label class="flex items-center gap-2.5 rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-200">
        <input type="checkbox" name="aktif" value="1" <?= (int) $isian['aktif'] === 1 ? 'checked' : '' ?>
               class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200">
        <span class="text-sm font-semibold text-emerald-800">Tampilkan di bilik suara (aktif)</span>
      </label>

      <button type="submit" class="tombol-utama w-full py-3">
        <?= ikon($sedangUbah ? 'cek' : 'tambah') ?> <?= $sedangUbah ? 'Simpan Perubahan' : 'Tambahkan Paslon' ?>
      </button>
    </form>
  </section>

  <!-- ========================= DAFTAR PASLON ========================= -->
  <section class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="kartu-judul">Daftar Pasangan Calon</h2>
      <span class="pill-abu"><?= ikon('kotak', 'h-3.5 w-3.5') ?> <?= e(angka(count($paslon))) ?> paslon</span>
    </div>

    <?php if (!$paslon): ?>
      <div class="kartu kartu-pad text-center text-sm text-slate-500">
        Belum ada pasangan calon. Gunakan formulir di samping untuk menambahkan.
      </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
      <?php foreach ($paslon as $i => $p):
          $jml  = $suara[(int) $p['id']] ?? 0;
          $foto = foto_paslon($p); ?>
        <article class="kartu overflow-hidden">
          <div class="relative flex items-center gap-3 bg-gradient-to-r from-brand-800 to-brand-900 p-4 text-white">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white/15 text-lg font-black ring-1 ring-white/25">
              <?= e((string) $p['nomor']) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-black"><?= e((string) $p['ketua']) ?></p>
              <p class="truncate text-xs text-brand-200">&amp; <?= e((string) $p['wakil']) ?></p>
            </div>
            <?php if ((int) $p['aktif'] === 1): ?>
              <span class="pill-ok !bg-emerald-500/25 !text-emerald-50 !ring-emerald-300/30">Aktif</span>
            <?php else: ?>
              <span class="pill-abu !bg-white/15 !text-white !ring-white/20">Nonaktif</span>
            <?php endif; ?>
          </div>

          <div class="flex gap-4 p-4">
            <?php if ($foto !== null): ?>
              <img src="<?= e($foto) ?>" alt="Foto paslon <?= e((string) $p['nomor']) ?>"
                   class="h-24 w-20 shrink-0 rounded-xl object-cover ring-1 ring-slate-200"
                   onerror="this.classList.add('hidden')">
            <?php else: ?>
              <div class="grid h-24 w-20 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400 ring-1 ring-slate-200">
                <?= ikon('paslon', 'h-8 w-8') ?>
              </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1 text-sm">
              <dl class="space-y-1.5">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Kelas ketua</dt><dd class="truncate font-semibold"><?= e((string) ($p['kelas_ketua'] ?: '-')) ?></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Kelas wakil</dt><dd class="truncate font-semibold"><?= e((string) ($p['kelas_wakil'] ?: '-')) ?></dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">Perolehan suara</dt><dd class="font-black text-emerald-700"><?= e(angka($jml)) ?></dd></div>
              </dl>
              <?php if (trim((string) $p['visi']) !== ''): ?>
                <p class="mt-2 line-clamp-2 text-xs italic text-slate-500">&ldquo;<?= e((string) $p['visi']) ?>&rdquo;</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50 p-3">
            <a href="admin_paslon.php?edit=<?= (int) $p['id'] ?>#formPaslon" class="tombol-putih tombol-kecil">
              <?= ikon('edit', 'h-4 w-4') ?> Ubah
            </a>
            <form method="post" action="admin_paslon.php">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="tombol-putih tombol-kecil">
                <?= ikon((int) $p['aktif'] === 1 ? 'tutup' : 'cek', 'h-4 w-4') ?>
                <?= (int) $p['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
            </form>
            <?php if ($jml === 0): ?>
              <form method="post" action="admin_paslon.php" data-konfirmasi
                    data-pesan="Hapus pasangan calon nomor <?= (int) $p['nomor'] ?> (<?= e((string) $p['ketua']) ?> &amp; <?= e((string) $p['wakil']) ?>)? Tindakan ini tidak dapat dibatalkan.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="tombol-bahaya tombol-kecil"><?= ikon('hapus', 'h-4 w-4') ?> Hapus</button>
              </form>
            <?php else: ?>
              <span class="ml-auto self-center text-[11px] font-semibold text-slate-500">Sudah bersuara &mdash; tidak dapat dihapus</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="notis-info">
      <?= ikon('info') ?>
      <p>Paslon yang <b>dinonaktifkan</b> tidak muncul di bilik suara, tetapi suara yang sudah diperolehnya tetap dihitung
        pada papan hasil. Paslon yang sudah punya suara sengaja tidak dapat dihapus agar hasil pemilihan tidak berubah.</p>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'admin']); ?>
