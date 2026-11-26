<?php
/**
 * admin_pengaturan.php — PENGATURAN PEMILIHAN & KEAMANAN AKUN PANITIA.
 * ---------------------------------------------------------------------------
 * Identitas pemilihan dipakai pada halaman masuk, bilik suara, dan papan hasil.
 * Panitia juga dapat membuka/menutup papan hasil untuk publik serta mengganti
 * kata sandinya sendiri.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

$galat = [];
$tab   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ------------------------- Identitas pemilihan ------------------------- */
    if ($aksi === 'simpan_identitas') {
        $tab = 'identitas';
        $namaPemilihan = batas_teks($_POST['nama_pemilihan'] ?? '', 150);
        $periode       = batas_teks($_POST['periode'] ?? '', 80);
        $tanggal       = batas_teks($_POST['tanggal_pemilihan'] ?? '', 80);

        if ($namaPemilihan === '') { $galat[] = 'Nama pemilihan wajib diisi.'; }

        if (!$galat) {
            set_setting('nama_pemilihan', $namaPemilihan);
            set_setting('periode', $periode);
            set_setting('tanggal_pemilihan', $tanggal);
            catat_aktivitas('pengaturan_identitas', 'Identitas pemilihan diperbarui');
            flash('ok', 'Identitas pemilihan disimpan.');
            redirect('admin_pengaturan.php');
        }
    }

    /* ---------------------- Tampilkan hasil ke publik ---------------------- */
    if ($aksi === 'simpan_tampilan') {
        $tab   = 'tampilan';
        $buka  = isset($_POST['tampilkan_hasil']) ? '1' : '0';
        set_setting('tampilkan_hasil', $buka);
        catat_aktivitas('pengaturan_hasil', 'Papan hasil publik: ' . ($buka === '1' ? 'dibuka' : 'ditutup'));
        flash('ok', $buka === '1'
            ? 'Papan hasil kini dapat dilihat publik.'
            : 'Papan hasil ditutup untuk publik (hanya panitia yang dapat melihat).');
        redirect('admin_pengaturan.php');
    }

    /* ---------------------------- Ganti sandi ---------------------------- */
    if ($aksi === 'ganti_sandi') {
        $tab       = 'sandi';
        $lama      = (string) ($_POST['sandi_lama'] ?? '');
        $baru      = (string) ($_POST['sandi_baru'] ?? '');
        $ulang     = (string) ($_POST['sandi_ulang'] ?? '');
        $admin     = admin_kini();

        if ($admin === false || !password_verify($lama, (string) $admin['sandi'])) {
            $galat[] = 'Kata sandi lama tidak sesuai.';
        }
        if (strlen($baru) < 8) {
            $galat[] = 'Kata sandi baru minimal 8 karakter.';
        }
        if ($baru !== $ulang) {
            $galat[] = 'Konfirmasi kata sandi baru tidak sama.';
        }
        if ($baru !== '' && strlen($baru) >= 8 && ($admin === false || $lama === $baru)) {
            $galat[] = 'Kata sandi baru tidak boleh sama dengan yang lama.';
        }

        if (!$galat) {
            jalankan('UPDATE admin SET sandi = ? WHERE id = ?',
                [password_hash($baru, PASSWORD_DEFAULT), (int) $admin['id']]);
            catat_aktivitas('ganti_sandi', 'Kata sandi panitia diganti');
            flash('ok', 'Kata sandi panitia berhasil diganti. Gunakan sandi baru pada masuk berikutnya.');
            redirect('admin_pengaturan.php');
        }
    }
}

$admin    = admin_kini();
$stat     = statistik();
$versiPhp = PHP_VERSION;

page_top('Pengaturan & Keamanan', [
    'shell' => 'admin',
    'sub'   => 'Identitas pemilihan, tampilan hasil, dan akun panitia',
    'aksi'  => '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Papan Hasil</a>',
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

<div class="grid gap-5 xl:grid-cols-2">

  <!-- ========================== IDENTITAS ========================== -->
  <section class="kartu kartu-pad" id="identitas">
    <h2 class="kartu-judul">Identitas Pemilihan</h2>
    <p class="kartu-ket">Tampil pada halaman masuk, bilik suara, dan papan hasil.</p>

    <form method="post" action="admin_pengaturan.php" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan_identitas">
      <div>
        <label class="label" for="nama_pemilihan">Nama Pemilihan</label>
        <input class="isian" type="text" id="nama_pemilihan" name="nama_pemilihan" required maxlength="150"
               value="<?= e(setting('nama_pemilihan')) ?>">
      </div>
      <div class="rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200">
        <p class="label !mb-1">Nama Madrasah</p>
        <p class="text-sm font-bold text-slate-800"><?= e(setting('nama_lembaga')) ?></p>
        <p class="catatan-kecil">Nama lembaga diatur oleh <b>Super Admin</b> aplikasi.</p>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="periode">Periode / Tahun</label>
          <input class="isian" type="text" id="periode" name="periode" maxlength="80"
                 value="<?= e(setting('periode')) ?>" placeholder="mis. Tahun Pelajaran 2025/2026">
        </div>
        <div>
          <label class="label" for="tanggal_pemilihan">Tanggal Pemungutan Suara</label>
          <input class="isian" type="text" id="tanggal_pemilihan" name="tanggal_pemilihan" maxlength="80"
                 value="<?= e(setting('tanggal_pemilihan')) ?>" placeholder="mis. Selasa, 20 Januari 2026">
        </div>
      </div>
      <button type="submit" class="tombol-utama w-full"><?= ikon('cek', 'h-4 w-4') ?> Simpan Identitas</button>
    </form>

    <!-- ======================= IDENTITAS LEMBAGA ======================= -->
    <div class="mt-5 border-t border-slate-200 pt-5" id="jenisLembaga">
      <h3 class="kartu-judul">Lembaga</h3>
      <p class="kartu-ket">
        Aplikasi ini khusus <b>Madrasah</b>; istilah kegiatan yang dipakai seluruh halaman adalah
        <b><?= e(ISTILAH) ?></b>.
      </p>

      <div class="mt-3 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200">
        <p class="text-sm font-bold text-brand-900"><?= e(jenis_lembaga_label()) ?></p>
        <p class="mt-1 text-xs font-semibold text-brand-800">
          Istilah yang berlaku pada lembaga ini: <b><?= e(istilah_kegiatan()) ?></b>
          (contoh: &ldquo;Pemilihan Ketua &amp; Wakil Ketua <?= e(istilah_kegiatan()) ?>&rdquo;).
        </p>
      </div>

      <?php if (is_super()): ?>
        <a href="super_lembaga.php?edit=<?= (int) lembaga_id_aktif() ?>#formLembaga" class="tombol-putih mt-3 w-full">
          <?= ikon('lembaga', 'h-4 w-4') ?> Ubah Nama Madrasah di Panel Super Admin
        </a>
      <?php else: ?>
        <p class="catatan-kecil">
          Ingin mengubah nama lembaga? Hubungi <b>Super Admin</b> aplikasi.
        </p>
      <?php endif; ?>
    </div>
  </section>

  <!-- ====================== TAMPILAN HASIL & INFO ====================== -->
  <div class="space-y-5">
    <section class="kartu kartu-pad" id="tampilan">
      <h2 class="kartu-judul">Tampilan Papan Hasil</h2>
      <p class="kartu-ket">Atur apakah hasil pemilihan boleh dilihat publik tanpa masuk panel.</p>

      <form method="post" action="admin_pengaturan.php" class="mt-4 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_tampilan">
        <label class="flex items-start gap-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
          <input type="checkbox" name="tampilkan_hasil" value="1" <?= hasil_publik() ? 'checked' : '' ?>
                 class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200">
          <span>
            <span class="block text-sm font-bold text-slate-800">Buka hasil untuk publik</span>
            <span class="block text-xs text-slate-500">
              Bila dicentang, halaman <b>hasil.php</b> dapat dibuka siapa saja (termasuk diproyeksikan pada
              <b>Mode Layar</b>). Bila tidak dicentang, hasil hanya terlihat oleh panitia yang sudah masuk.
            </span>
          </span>
        </label>
        <button type="submit" class="tombol-utama w-full"><?= ikon('cek', 'h-4 w-4') ?> Simpan Pengaturan Hasil</button>
      </form>
    </section>

    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Informasi Sistem</h2>
      <dl class="mt-3 space-y-2 text-sm">
        <?php foreach ([
            ['Versi aplikasi', APP_NAME . ' ' . APP_VERSION],
            ['Pemilih terdaftar', angka($stat['dpt']) . ' orang'],
            ['Surat suara masuk', angka($stat['masuk']) . ' suara'],
            ['Versi PHP server', $versiPhp],
        ] as $baris): ?>
          <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
            <dt class="text-slate-600"><?= e($baris[0]) ?></dt>
            <dd class="font-bold text-slate-800"><?= e($baris[1]) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <div class="notis-info mt-4">
        <?= ikon('kunci') ?>
        <p>Surat suara disimpan <b>tanpa identitas pemilih</b>, sehingga panitia tidak dapat mengetahui
          pilihan seseorang. Yang tercatat hanyalah status <i>sudah/belum</i> memilih.</p>
      </div>
    </section>
  </div>

  <!-- ========================== KEAMANAN AKUN ========================== -->
  <section class="kartu kartu-pad xl:col-span-2" id="sandi">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2 class="kartu-judul">Akun Panitia &amp; Keamanan</h2>
        <p class="kartu-ket">
          Masuk sebagai <b><?= e((string) ($admin['username'] ?? '-')) ?></b> (<?= e((string) ($admin['nama'] ?? '-')) ?>).
          Ganti kata sandi bawaan segera setelah pemasangan.
        </p>
      </div>
      <span class="pill-ok"><?= ikon('kunci', 'h-3.5 w-3.5') ?> Sesi terkunci ke cookie aman</span>
    </div>

    <form method="post" action="admin_pengaturan.php" class="mt-4 grid gap-4 sm:grid-cols-3" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="ganti_sandi">
      <div>
        <label class="label" for="sandi_lama">Kata Sandi Sekarang</label>
        <input class="isian" type="password" id="sandi_lama" name="sandi_lama" required autocomplete="current-password">
      </div>
      <div>
        <label class="label" for="sandi_baru">Kata Sandi Baru</label>
        <input class="isian" type="password" id="sandi_baru" name="sandi_baru" required minlength="8" autocomplete="new-password">
        <p class="catatan-kecil">Minimal 8 karakter.</p>
      </div>
      <div>
        <label class="label" for="sandi_ulang">Ulangi Kata Sandi Baru</label>
        <input class="isian" type="password" id="sandi_ulang" name="sandi_ulang" required minlength="8" autocomplete="new-password">
      </div>
      <div class="sm:col-span-3">
        <button type="submit" class="tombol-utama"><?= ikon('kunci', 'h-4 w-4') ?> Ganti Kata Sandi</button>
      </div>
    </form>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
      <?php foreach ([
          ['Percobaan masuk', 'Maksimal ' . LOGIN_MAKS_COBA . ' kali per 10 menit untuk setiap sesi.'],
          ['Kerahasiaan', 'Tabel suara tidak menyimpan identitas pemilih.'],
          ['Jejak aktivitas', 'Masuk, token baru, dan surat suara tercatat (tanpa pilihan).'],
      ] as $k): ?>
        <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
          <p class="text-sm font-bold text-slate-800"><?= e($k[0]) ?></p>
          <p class="mt-1 text-xs text-slate-500"><?= e($k[1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'admin']); ?>
