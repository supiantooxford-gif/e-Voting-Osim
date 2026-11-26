<?php
/**
 * super_akun.php — KELOLA AKUN PANITIA SETIAP LEMBAGA (khusus Super Admin).
 * ---------------------------------------------------------------------------
 * Permintaan pemilik: akun panitia dibuat & diatur oleh Super Admin.
 * Karena itu halaman ini menyediakan:
 *   - membuat akun panitia untuk lembaga tertentu;
 *   - mengubah nama / nama pengguna / status aktifnya;
 *   - menetapkan kata sandi baru (tanpa perlu tahu sandi lama);
 *   - mengganti kata sandi Super Admin sendiri.
 * Tidak ada pendaftaran mandiri: akun hanya lahir dari halaman ini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_super();

$galat  = [];
$editId = (int) ($_GET['edit'] ?? 0);
$filter = (int) ($_GET['lembaga'] ?? 0);

/* ============================ PEMROSESAN POST ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ------------------------ Tambah akun panitia ----------------------- */
    if ($aksi === 'tambah') {
        $lembagaId = (int) ($_POST['lembaga_id'] ?? 0);
        $hasil = akun_panitia_tambah($lembagaId, [
            'nama'     => $_POST['nama'] ?? '',
            'username' => $_POST['username'] ?? '',
            'sandi'    => $_POST['sandi'] ?? '',
        ]);
        if (!$hasil['ok']) {
            $galat[] = (string) $hasil['pesan'];
        } else {
            catat_aktivitas('akun_tambah', $hasil['pesan'], $lembagaId);
            flash('ok', (string) $hasil['pesan']);
            redirect('super_akun.php?lembaga=' . $lembagaId);
        }
    }

    /* --------------------------- Ubah akun ------------------------------ */
    if ($aksi === 'ubah') {
        $id = (int) ($_POST['id'] ?? 0);
        $hasil = akun_panitia_ubah($id, [
            'nama'     => $_POST['nama'] ?? '',
            'username' => $_POST['username'] ?? '',
            'aktif'    => $_POST['aktif'] ?? '',
        ]);
        if (!$hasil['ok']) {
            $galat[] = (string) $hasil['pesan'];
            $editId  = $id;
        } else {
            catat_aktivitas('akun_ubah', (string) $hasil['pesan']);
            flash('ok', (string) $hasil['pesan']);
            redirect('super_akun.php' . ($filter > 0 ? '?lembaga=' . $filter : ''));
        }
    }

    /* ------------------------ Sandi akun panitia ------------------------ */
    if ($aksi === 'sandi') {
        $id    = (int) ($_POST['id'] ?? 0);
        $sandi = (string) ($_POST['sandi'] ?? '');
        $a     = satu("SELECT * FROM admin WHERE id = ? AND peran = 'panitia'", [$id]);
        if ($a === false) {
            $galat[] = 'Akun panitia tidak ditemukan.';
        } else {
            $hasil = akun_sandi_set($id, $sandi);
            if (!$hasil['ok']) {
                $galat[] = (string) $hasil['pesan'];
                $editId  = $id;
            } else {
                catat_aktivitas('akun_sandi', 'Sandi akun ' . $a['username'] . ' diganti Super Admin', (int) $a['lembaga_id']);
                flash('ok', 'Kata sandi akun "' . (string) $a['username'] . '" berhasil diganti. Berikan sandi baru itu kepada panitia.');
                redirect('super_akun.php' . ($filter > 0 ? '?lembaga=' . $filter : ''));
            }
        }
    }

    /* ---------------------- Sandi Super Admin saya ---------------------- */
    if ($aksi === 'sandi_saya') {
        $lama  = (string) ($_POST['sandi_lama'] ?? '');
        $baru  = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');
        $saya  = admin_kini();

        if ($saya === false || !password_verify($lama, (string) $saya['sandi'])) {
            $galat[] = 'Kata sandi lama tidak sesuai.';
        }
        if (strlen($baru) < SANDI_MIN) {
            $galat[] = 'Kata sandi baru minimal ' . SANDI_MIN . ' karakter.';
        }
        if ($baru !== $ulang) {
            $galat[] = 'Konfirmasi kata sandi baru tidak sama.';
        }
        if ($lama !== '' && $lama === $baru) {
            $galat[] = 'Kata sandi baru tidak boleh sama dengan yang lama.';
        }
        if (!$galat && $saya !== false) {
            $hasil = akun_sandi_set((int) $saya['id'], $baru);
            if (!$hasil['ok']) {
                $galat[] = (string) $hasil['pesan'];
            } else {
                catat_aktivitas('sandi_super', 'Kata sandi Super Admin diganti');
                flash('ok', 'Kata sandi Super Admin berhasil diganti. Gunakan sandi baru pada masuk berikutnya.');
                redirect('super_akun.php');
            }
        }
    }
}

/* ================================ PENYIAPAN ================================ */
$lembaga = lembaga_semua(true);
$daftar  = akun_panitia_semua($filter > 0 ? $filter : null);
$saya    = admin_kini();
$edit    = $editId > 0 ? satu("SELECT * FROM admin WHERE id = ? AND peran = 'panitia'", [$editId]) : false;
$namaFilter = $filter > 0 ? lembaga_by_id($filter) : false;

page_top('Akun Panitia', [
    'shell' => 'super',
    'sub'   => count($daftar) . ' akun panitia' . ($namaFilter !== false ? ' &middot; ' . e((string) $namaFilter['nama']) : ' di seluruh lembaga'),
    'aksi'  => '<a href="super.php" class="tombol-putih tombol-kecil">' . ikon('dashboard', 'h-4 w-4') . ' Ringkasan</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk mb-4">
    <?= ikon('info') ?>
    <div>
      <p class="font-bold">Periksa kembali isian berikut:</p>
      <ul class="mt-1 list-inside list-disc space-y-0.5">
        <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="grid gap-5 xl:grid-cols-[minmax(0,27rem)_minmax(0,1fr)]">

  <div class="min-w-0 space-y-5">
    <!-- ===================== TAMBAH / UBAH AKUN ===================== -->
    <section class="kartu kartu-pad" id="formAkun">
      <?php if ($edit !== false): ?>
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="kartu-judul">Ubah Akun Panitia</h2>
            <p class="kartu-ket"><?= e((string) $edit['username']) ?></p>
          </div>
          <a href="super_akun.php" class="tombol-putih tombol-kecil">Batal</a>
        </div>

        <form method="post" action="super_akun.php" class="mt-4 space-y-4">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="ubah">
          <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
          <div>
            <label class="label" for="nama">Nama Pengelola</label>
            <input class="isian" type="text" id="nama" name="nama" required maxlength="120" value="<?= e((string) $edit['nama']) ?>">
          </div>
          <div>
            <label class="label" for="username">Nama Pengguna</label>
            <input class="isian" type="text" id="username" name="username" required maxlength="60"
                   value="<?= e((string) $edit['username']) ?>" spellcheck="false" autocomplete="off">
          </div>
          <label class="flex items-center gap-2.5 rounded-2xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-200">
            <input type="checkbox" name="aktif" value="1" <?= (int) $edit['aktif'] === 1 ? 'checked' : '' ?>
                   class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200">
            <span class="text-sm font-semibold text-emerald-800">Akun aktif (boleh masuk)</span>
          </label>
          <button type="submit" class="tombol-utama w-full"><?= ikon('cek', 'h-4 w-4') ?> Simpan Perubahan</button>
        </form>

        <div class="mt-5 border-t border-slate-200 pt-5">
          <h3 class="kartu-judul">Kata Sandi Baru</h3>
          <p class="kartu-ket">Tetapkan sandi baru tanpa perlu tahu sandi lamanya.</p>
          <form method="post" action="super_akun.php" class="mt-3 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="sandi">
            <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
            <div>
              <label class="label" for="sandiReset">Kata Sandi Baru</label>
              <input class="isian" type="text" id="sandiReset" name="sandi" required minlength="<?= SANDI_MIN ?>"
                     autocomplete="off" placeholder="min. <?= SANDI_MIN ?> karakter">
            </div>
            <button type="submit" class="tombol-ok w-full"><?= ikon('kunci', 'h-4 w-4') ?> Tetapkan Sandi Baru</button>
          </form>
        </div>
      <?php else: ?>
        <h2 class="kartu-judul">Tambah Akun Panitia</h2>
        <p class="kartu-ket">Satu akun hanya untuk satu lembaga. Panitia hanya melihat lembaganya sendiri.</p>

        <form method="post" action="super_akun.php" class="mt-4 space-y-4" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="tambah">
          <div>
            <label class="label" for="lembaga_id">Madrasah</label>
            <select class="isian" id="lembaga_id" name="lembaga_id" required>
              <option value="">— pilih lembaga —</option>
              <?php foreach ($lembaga as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= $filter === (int) $l['id'] ? 'selected' : '' ?>>
                  <?= e((string) $l['nama']) ?><?= (int) $l['aktif'] === 1 ? '' : ' (nonaktif)' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="nama">Nama Pengelola</label>
            <input class="isian" type="text" id="nama" name="nama" required maxlength="120" placeholder="mis. Panitia Pemilihan">
          </div>
          <div>
            <label class="label" for="username">Nama Pengguna</label>
            <input class="isian" type="text" id="username" name="username" required maxlength="60"
                   spellcheck="false" placeholder="mis. panitia-ma">
            <p class="catatan-kecil">Huruf, angka, titik, garis bawah, atau tanda hubung — tanpa spasi.</p>
          </div>
          <div>
            <label class="label" for="sandi">Kata Sandi</label>
            <input class="isian" type="text" id="sandi" name="sandi" required minlength="<?= SANDI_MIN ?>"
                   placeholder="min. <?= SANDI_MIN ?> karakter">
            <p class="catatan-kecil">Tampil sebagai teks supaya mudah disalin &amp; diserahkan ke panitia.</p>
          </div>
          <button type="submit" class="tombol-utama w-full py-3"><?= ikon('tambah', 'h-4 w-4') ?> Buat Akun Panitia</button>
        </form>
      <?php endif; ?>
    </section>

    <!-- ===================== KATA SANDI SUPER ADMIN ===================== -->
    <section class="kartu kartu-pad" id="sandiSaya">
      <h2 class="kartu-judul">Kata Sandi Saya (Super Admin)</h2>
      <p class="kartu-ket">Masuk sebagai <b><?= e((string) ($saya['username'] ?? '-')) ?></b>. Ganti sandi bawaan segera.</p>
      <form method="post" action="super_akun.php" class="mt-4 space-y-3" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="sandi_saya">
        <div>
          <label class="label" for="sandi_lama">Kata Sandi Sekarang</label>
          <input class="isian" type="password" id="sandi_lama" name="sandi_lama" required autocomplete="current-password">
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="label" for="sandi_baru">Kata Sandi Baru</label>
            <input class="isian" type="password" id="sandi_baru" name="sandi_baru" required minlength="<?= SANDI_MIN ?>" autocomplete="new-password">
          </div>
          <div>
            <label class="label" for="sandi_ulang">Ulangi Sandi Baru</label>
            <input class="isian" type="password" id="sandi_ulang" name="sandi_ulang" required minlength="<?= SANDI_MIN ?>" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="tombol-putih w-full"><?= ikon('kunci', 'h-4 w-4') ?> Ganti Sandi Super Admin</button>
      </form>
    </section>
  </div>

  <!-- ======================== DAFTAR AKUN PANITIA ======================== -->
  <section class="kartu kartu-pad min-w-0">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="kartu-judul">Daftar Akun Panitia</h2>
      <form method="get" action="super_akun.php" class="flex flex-wrap items-center gap-2">
        <select class="isian !py-2 sm:!w-64" name="lembaga" aria-label="Saring menurut lembaga" onchange="this.form.submit()">
          <option value="0">Semua lembaga</option>
          <?php foreach ($lembaga as $l): ?>
            <option value="<?= (int) $l['id'] ?>" <?= $filter === (int) $l['id'] ? 'selected' : '' ?>>
              <?= e((string) $l['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="tombol-putih tombol-kecil">Saring</button></noscript>
      </form>
    </div>

    <div class="bungkus-tabel mt-4">
      <table class="tabel !min-w-[52rem]">
        <thead>
          <tr>
            <th class="w-12">No</th>
            <th>Nama Pengelola</th>
            <th class="w-40">Nama Pengguna</th>
            <th class="w-56">Madrasah</th>
            <th class="w-24">Status</th>
            <th class="w-28">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$daftar): ?>
            <tr><td colspan="6" class="py-8 text-center text-slate-500">
              Belum ada akun panitia. Buat akun pertama lewat formulir di samping.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($daftar as $i => $a): ?>
            <tr>
              <td class="text-slate-400"><?= $i + 1 ?></td>
              <td>
                <span class="block text-sm font-bold text-slate-800"><?= e((string) $a['nama']) ?></span>
                <span class="block text-[11px] text-slate-500">dibuat <?= e(tanggal_id((string) $a['dibuat'])) ?></span>
              </td>
              <td class="font-mono text-xs font-semibold text-slate-700"><?= e((string) $a['username']) ?></td>
              <td class="text-sm text-slate-600"><?= e((string) ($a['lembaga_nama'] ?? '-')) ?></td>
              <td><?= (int) $a['aktif'] === 1 ? '<span class="pill-ok">Aktif</span>' : '<span class="pill-abu">Nonaktif</span>' ?></td>
              <td>
                <a href="super_akun.php?edit=<?= (int) $a['id'] ?><?= $filter > 0 ? '&amp;lembaga=' . $filter : '' ?>#formAkun"
                   class="tombol-putih tombol-kecil"><?= ikon('edit', 'h-3.5 w-3.5') ?> Ubah</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="notis-kuning mt-4">
      <?= ikon('info') ?>
      <p>Akun panitia <b>tidak dapat mendaftar sendiri</b>. Bila panitia lupa sandinya, buka akun di atas lalu
        tetapkan kata sandi baru — tidak perlu menghapus akunnya.</p>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'super']); ?>
