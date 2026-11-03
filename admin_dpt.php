<?php
/**
 * admin_dpt.php — MANAJEMEN DPT (DAFTAR PEMILIH TETAP) & TOKEN.
 * ---------------------------------------------------------------------------
 * - Tambah/ubah siswa beserta NISN, kelas, dan token pemilih.
 * - Generate token acak (satu siswa satu token, hanya bisa dipakai sekali).
 * - Pencarian cepat (live search) nama/NISN/kelas + penyaring status.
 * - "Batal pilih" mengembalikan hak pilih siswa: statusnya dikembalikan ke
 *   belum memilih DAN satu surat suara terakhir dibatalkan, supaya jumlah
 *   surat suara tetap sama dengan jumlah pemilih bertanda sudah memilih.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

/* PHP membuang SELURUH $_POST/$_FILES bila kiriman melebihi post_max_size —
   gejalanya "tidak terjadi apa-apa". Diberi pesan yang jelas. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('galat', 'Ukuran kiriman melebihi batas server. Pecah berkas impor menjadi beberapa bagian '
        . '(maksimal ±2.000 baris per impor) lalu ulangi.');
    redirect('admin_dpt.php');
}

/* Jenis pemilih yang sedang dibuka (tab Siswa atau Guru & Tendik). */
$jenisHalaman = jenis_pemilih_sah($_GET['jenis'] ?? 'siswa');
$infoHalaman  = jenis_pemilih_info($jenisHalaman);

/* Unduhan contoh format impor (bekerja tanpa JavaScript). */
if (($_GET['contoh'] ?? '') === 'csv') {
    dpt_contoh_csv($jenisHalaman);
}

$galat         = [];
$isian         = null;
$tampilkanForm = false;
$editId        = (int) ($_GET['edit'] ?? 0);
$impor         = null;   /* hasil impor terakhir (ditampilkan sebagai kartu) */

/* ============================ PEMROSESAN POST ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_periksa();
    $aksi = (string) ($_POST['aksi'] ?? '');

    /* ------------------- Impor data pemilih (Excel/CSV) ------------------- */
    if ($aksi === 'impor') {
        $muatan = (string) ($_POST['payload'] ?? '');
        if ($muatan === '') {
            flash('galat', 'Belum ada berkas yang dibaca. Pilih berkas Excel/CSV lebih dulu.');
            redirect('admin_dpt.php');
        }
        $data = json_decode($muatan, true);
        if (!is_array($data) || !isset($data['header']) || !isset($data['rows'])) {
            flash('galat', 'Isi berkas tidak dapat dibaca. Pastikan memakai format contoh yang disediakan.');
            redirect('admin_dpt.php');
        }
        $perbarui = isset($_POST['perbarui']);
        $jenisImpor = jenis_pemilih_sah($_POST['jenis_pemilih'] ?? 'siswa');
        $hasil    = dpt_impor_baris((array) $data['header'], (array) $data['rows'], $perbarui, $jenisImpor);
        $hasil['jenis'] = $jenisImpor;

        catat_aktivitas('impor_pemilih', 'Impor DPT ' . $jenisImpor . ': ' . $hasil['tambah'] . ' baru, '
            . $hasil['ubah'] . ' diperbarui, ' . $hasil['lewat'] . ' dilewati');

        if ($hasil['tambah'] === 0 && $hasil['ubah'] === 0) {
            flash($hasil['masalah'] ? 'galat' : 'kuning', 'Impor selesai tanpa perubahan: '
                . $hasil['lewat'] . ' baris dilewati.');
        } else {
            flash('ok', 'Impor berhasil: ' . $hasil['tambah'] . ' pemilih baru'
                . ($hasil['ubah'] > 0 ? ', ' . $hasil['ubah'] . ' data diperbarui' : '')
                . ($hasil['lewat'] > 0 ? ', ' . $hasil['lewat'] . ' dilewati' : '') . '.');
        }
        /* Ringkasan disimpan di sesi supaya tetap terlihat setelah pengalihan. */
        $_SESSION['hasil_impor'] = $hasil;
        redirect('admin_dpt.php?jenis=' . $jenisImpor);
    }

    /* --------------------------- Simpan pemilih --------------------------- */
    if ($aksi === 'simpan') {
        $id       = (int) ($_POST['id'] ?? 0);
        $tokenKirim = trim((string) ($_POST['token'] ?? ''));
        /* Jenis pemilih ditentukan formulir: Siswa (NISN/Kelas) atau Guru (NIK/Jabatan). */
        $jenisIsian = jenis_pemilih_sah($_POST['jenis_pemilih'] ?? 'siswa');
        $infoIsian  = jenis_pemilih_info($jenisIsian);
        $isian = [
            'id'      => $id,
            'jenis'   => $jenisIsian,
            'nisn'    => nisn_bersih($_POST['nisn'] ?? ''),
            'nama'    => batas_teks($_POST['nama'] ?? '', 120),
            'kelas'   => $jenisIsian === 'guru' ? '' : batas_teks($_POST['kelas'] ?? '', 60),
            'jabatan' => $jenisIsian === 'guru' ? batas_teks($_POST['jabatan'] ?? '', 80) : '',
            'token'   => $tokenKirim !== '' ? strtoupper($tokenKirim) : '',
        ];

        if ($isian['nisn'] === '') {
            $galat[] = $infoIsian['induk'] . ' wajib diisi (hanya angka/huruf).';
        } elseif ((int) nilai('SELECT COUNT(*) FROM pemilih WHERE nisn = ? AND id <> ?', [$isian['nisn'], $id]) > 0) {
            $galat[] = $infoIsian['induk'] . ' ' . $isian['nisn'] . ' sudah terdaftar pada DPT.';
        }
        if ($isian['nama'] === '') {
            $galat[] = 'Nama ' . ($jenisIsian === 'guru' ? 'guru' : 'siswa') . ' wajib diisi.';
        }
        if ($jenisIsian === 'guru' && $isian['jabatan'] === '') {
            $galat[] = 'Jabatan wajib diisi untuk pemilih guru & tendik.';
        }
        if ($isian['token'] !== '' && strlen(token_norm($isian['token'])) < 4) {
            $galat[] = 'Token minimal 4 karakter huruf/angka.';
        } elseif ($isian['token'] !== '' && token_dipakai($isian['token'], $id)) {
            $galat[] = 'Token itu sudah dipakai pemilih lain. Biarkan kosong agar sistem membuatkan token acak.';
        }

        /* Token baru dibuat otomatis bila kolomnya dikosongkan atau tokennya dipakai orang lain. */
        if ($id === 0 && $isian['token'] === '') {
            $isian['token'] = token_unik();
        }

        if ($galat) {
            $tampilkanForm = true;
            $editId = $id;
            $jenisHalaman = $isian['jenis'];
            $infoHalaman  = $infoIsian;
        } else {
            /* Token terpakai bisa terjadi bila dua panitia menyimpan hampir
               bersamaan — jawab dengan pesan jelas, bukan halaman galat. */
            $jabatan = $isian['jabatan'] === '' ? null : $isian['jabatan'];
            try {
                if ($id > 0) {
                    if ($isian['token'] === '') {
                        jalankan('UPDATE pemilih SET nisn = ?, nama = ?, kelas = ?, jabatan = ?, jenis = ? WHERE id = ?',
                            [$isian['nisn'], $isian['nama'], $isian['kelas'], $jabatan, $isian['jenis'], $id]);
                    } else {
                        jalankan('UPDATE pemilih SET nisn = ?, nama = ?, kelas = ?, jabatan = ?, jenis = ?, token = ? WHERE id = ?',
                            [$isian['nisn'], $isian['nama'], $isian['kelas'], $jabatan, $isian['jenis'], $isian['token'], $id]);
                    }
                    catat_aktivitas('pemilih_ubah', 'Data pemilih diubah: ' . $isian['nama']);
                    flash('ok', 'Data pemilih ' . $isian['nama'] . ' diperbarui.');
                } else {
                    q('INSERT INTO pemilih (nisn, nama, kelas, jabatan, jenis, token, sudah, dibuat) VALUES (?, ?, ?, ?, ?, ?, 0, ?)',
                        [$isian['nisn'], $isian['nama'], $isian['kelas'], $jabatan, $isian['jenis'], $isian['token'], date('Y-m-d H:i:s')]);
                    catat_aktivitas('pemilih_tambah', 'Pemilih ditambahkan: ' . $isian['nama']);
                    flash('ok', $infoIsian['nama'] . ' ' . $isian['nama'] . ' ditambahkan dengan token ' . $isian['token'] . '.');
                }
            } catch (PDOException $e) {
                flash('galat', str_contains($e->getMessage(), 'UNIQUE')
                    ? 'Nomor induk atau token itu baru saja dipakai data lain. Muat ulang halaman lalu coba lagi.'
                    : 'Data pemilih gagal disimpan. Muat ulang halaman lalu coba lagi.');
                redirect('admin_dpt.php?jenis=' . $isian['jenis']);
            }
            redirect('admin_dpt.php?jenis=' . $isian['jenis']);
        }
    }

    /* ------------------------ Generate token per siswa ------------------------ */
    if ($aksi === 'token_baru') {
        $id = (int) ($_POST['id'] ?? 0);
        $p  = satu('SELECT * FROM pemilih WHERE id = ?', [$id]);
        if ($p === false) {
            flash('galat', 'Pemilih tidak ditemukan.');
        } elseif ((int) $p['sudah'] === 1) {
            flash('kuning', 'Token ' . $p['nama'] . ' tidak perlu diganti — siswa itu sudah menggunakan hak pilih.');
        } else {
            $baru = token_unik();
            jalankan('UPDATE pemilih SET token = ? WHERE id = ?', [$baru, $id]);
            catat_aktivitas('token_baru', 'Token baru untuk pemilih id ' . $id);
            flash('ok', 'Token baru untuk ' . $p['nama'] . ': ' . $baru);
        }
        redirect('admin_dpt.php?jenis=' . jenis_pemilih_sah($p !== false ? (string) $p['jenis'] : 'siswa'));
    }

    /* ---------------------- Acak token semua yang belum memilih ---------------------- */
    if ($aksi === 'token_massal') {
        /* Diacak hanya untuk jenis yang sedang dibuka supaya akibatnya terduga. */
        $jenisMassal = jenis_pemilih_sah($_POST['jenis_pemilih'] ?? 'siswa');
        $daftar = semua('SELECT id FROM pemilih WHERE sudah = 0 AND jenis = ?', [$jenisMassal]);
        foreach ($daftar as $d) {
            jalankan('UPDATE pemilih SET token = ? WHERE id = ?', [token_unik(), (int) $d['id']]);
        }
        catat_aktivitas('token_massal', 'Token diacak untuk ' . count($daftar) . ' pemilih (' . $jenisMassal . ')');
        flash('ok', count($daftar) . ' token pemilih ' . jenis_pemilih_info($jenisMassal)['nama']
            . ' yang belum memilih sudah diacak ulang.');
        redirect('admin_dpt.php?jenis=' . $jenisMassal);
    }

    /* ---------------------------- Batal pilih ---------------------------- */
    if ($aksi === 'batal_pilih') {
        $id = (int) ($_POST['id'] ?? 0);
        $p  = satu('SELECT * FROM pemilih WHERE id = ?', [$id]);
        if ($p === false) {
            flash('galat', 'Pemilih tidak ditemukan.');
        } elseif ((int) $p['sudah'] !== 1) {
            flash('kuning', 'Pemilih ' . $p['nama'] . ' memang belum memberikan suara.');
        } else {
            $pdo = pdo();
            try {
                $pdo->exec('BEGIN IMMEDIATE');
                /* Batalkan satu surat suara terakhir (anonim) agar jumlahnya tetap seimbang. */
                jalankan('DELETE FROM suara WHERE id = (SELECT MAX(id) FROM suara)');
                jalankan('UPDATE pemilih SET sudah = 0, waktu = NULL WHERE id = ?', [$id]);
                $pdo->exec('COMMIT');
                catat_aktivitas('batal_pilih', 'Hak pilih dikembalikan untuk pemilih id ' . $id);
                flash('ok', 'Hak pilih ' . $p['nama'] . ' dikembalikan. Satu surat suara terakhir dibatalkan, '
                    . 'sehingga pemilih itu dapat memilih lagi dengan token yang sama.');
            } catch (Throwable $e) {
                try { $pdo->exec('ROLLBACK'); } catch (Throwable $abaikan) {}
                flash('galat', 'Gagal mengembalikan hak pilih. Coba lagi.');
            }
        }
        redirect('admin_dpt.php?jenis=' . jenis_pemilih_sah($p !== false ? (string) $p['jenis'] : 'siswa'));
    }

    /* -------------------------------- Hapus -------------------------------- */
    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $p  = satu('SELECT * FROM pemilih WHERE id = ?', [$id]);
        if ($p === false) {
            flash('galat', 'Pemilih tidak ditemukan.');
        } elseif ((int) $p['sudah'] === 1) {
            flash('galat', 'Pemilih ' . $p['nama'] . ' sudah memberikan suara. Kembalikan dulu hak pilihnya '
                . '(tombol Batal pilih) bila memang datanya harus dihapus.');
        } else {
            jalankan('DELETE FROM pemilih WHERE id = ?', [$id]);
            catat_aktivitas('pemilih_hapus', 'Pemilih dihapus: ' . $p['nama']);
            flash('ok', 'Pemilih ' . $p['nama'] . ' dihapus dari DPT.');
        }
        redirect('admin_dpt.php?jenis=' . jenis_pemilih_sah($p !== false ? (string) $p['jenis'] : 'siswa'));
    }
}

/* ================================ PENYIAPAN ================================ */
$urut     = $jenisHalaman === 'guru' ? 'jabatan, nama' : 'kelas, nama';
$pemilih  = semua('SELECT * FROM pemilih WHERE jenis = ? ORDER BY ' . $urut, [$jenisHalaman]);
$jmlJenis = pemilih_jumlah_jenis();
$stat     = statistik();

/* Hasil impor terakhir (sekali tampil). */
if (isset($_SESSION['hasil_impor'])) {
    $impor = $_SESSION['hasil_impor'];
    unset($_SESSION['hasil_impor']);
}

if ($editId > 0 && !$tampilkanForm) {
    $p = satu('SELECT * FROM pemilih WHERE id = ?', [$editId]);
    if ($p !== false) {
        $jenisHalaman = jenis_pemilih_sah((string) $p['jenis']);
        $infoHalaman  = jenis_pemilih_info($jenisHalaman);
        $isian = ['id' => (int) $p['id'], 'jenis' => $jenisHalaman, 'nisn' => (string) $p['nisn'],
            'nama' => (string) $p['nama'], 'kelas' => (string) $p['kelas'],
            'jabatan' => (string) ($p['jabatan'] ?? ''), 'token' => (string) $p['token']];
    }
}
if ($isian === null) {
    $isian = ['id' => 0, 'jenis' => $jenisHalaman, 'nisn' => '', 'nama' => '',
        'kelas' => '', 'jabatan' => '', 'token' => ''];
}
if (empty($isian['jenis'])) {
    $isian['jenis'] = $jenisHalaman;
}
$sedangUbah = (int) $isian['id'] > 0;

page_top('DPT & Token Pemilih', [
    'shell'  => 'admin',
    /* Dipakai skrip saat menyalin seluruh token (judul kolom menyesuaikan). */
    'sub'    => $jmlJenis['siswa'] . ' siswa &middot; ' . $jmlJenis['guru'] . ' guru &amp; tendik'
        . ' &middot; ' . $stat['belum'] . ' belum memilih',
    'aksi'   => '<button type="button" class="tombol-putih tombol-kecil" data-salin-token>'
        . ikon('salin', 'h-4 w-4') . ' Salin Semua Token</button>'
        . '<a href="cetak_kartu_pemilih.php?jenis=' . e($jenisHalaman) . '" class="tombol-utama tombol-kecil">'
        . ikon('kotak', 'h-4 w-4') . ' Cetak Kartu Pemilih</a>',
]);
?>

<?php if ($galat): ?>
  <div class="notis-galat animate-masuk mb-3">
    <?= ikon('info') ?>
    <div>
      <p class="font-bold">Ada isian yang perlu diperbaiki:</p>
      <ul class="mt-1 list-inside list-disc space-y-0.5">
        <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($impor !== null): ?>
  <!-- ======================= HASIL IMPOR DATA PEMILIH ======================= -->
  <section class="kartu kartu-pad animate-masuk mb-5" id="hasilImpor">
    <div class="flex flex-wrap items-center gap-3">
      <span class="grid h-11 w-11 place-items-center rounded-2xl <?= $impor['tambah'] + $impor['ubah'] > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
        <?= ikon($impor['tambah'] + $impor['ubah'] > 0 ? 'cek' : 'info') ?>
      </span>
      <div class="min-w-0 flex-1">
        <h2 class="kartu-judul">Hasil Impor Data Pemilih</h2>
        <p class="kartu-ket">
          <?= e(angka((int) $impor['total'])) ?> baris dibaca
          &middot; kolom dikenali: <?= e($impor['kolom_dikenal'] ? implode(', ', array_map('strtoupper', $impor['kolom_dikenal'])) : 'tidak ada') ?>
        </p>
      </div>
      <button type="button" class="tombol-putih tombol-kecil" data-tutup-kartu="#hasilImpor">&times; Tutup</button>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
      <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
        <p class="stat-label text-emerald-700">Pemilih Baru</p>
        <p class="text-2xl font-black text-emerald-800"><?= e(angka((int) $impor['tambah'])) ?></p>
      </div>
      <div class="rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200">
        <p class="stat-label text-brand-700">Data Diperbarui</p>
        <p class="text-2xl font-black text-brand-800"><?= e(angka((int) $impor['ubah'])) ?></p>
      </div>
      <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
        <p class="stat-label text-amber-700">Dilewati</p>
        <p class="text-2xl font-black text-amber-800"><?= e(angka((int) $impor['lewat'])) ?></p>
      </div>
    </div>

    <?php if ($impor['masalah']): ?>
      <div class="notis-kuning mt-4">
        <?= ikon('info') ?>
        <div>
          <p class="font-bold">Catatan pada baris berikut:</p>
          <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs">
            <?php foreach ($impor['masalah'] as $m): ?><li><?= e((string) $m) ?></li><?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($impor['token_baru']): ?>
      <div class="bungkus-tabel mt-4">
        <table class="tabel !min-w-[36rem]">
          <thead>
            <tr><th class="w-36">NISN</th><th>Nama</th><th class="w-32">Kelas</th><th class="w-32">Token</th></tr>
          </thead>
          <tbody>
            <?php foreach ($impor['token_baru'] as $t): ?>
              <tr>
                <td class="font-mono text-xs font-semibold"><?= e((string) $t['nisn']) ?></td>
                <td class="font-semibold"><?= e((string) $t['nama']) ?></td>
                <td><?= e((string) ($t['kelas'] ?: '-')) ?></td>
                <td>
                  <span class="inline-flex items-center gap-1.5">
                    <code class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-bold tracking-wider"><?= e((string) $t['token']) ?></code>
                    <button type="button" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700"
                            data-salin="<?= e((string) $t['token']) ?>" title="Salin token"><?= ikon('salin', 'h-4 w-4') ?></button>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="catatan-kecil">Token di atas dibuat otomatis oleh sistem. Bagikan kepada siswa atau cetak lewat
        <a href="cetak_kartu_pemilih.php" class="font-bold text-brand-700 underline">Kartu Pemilih</a>.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<span class="hidden" data-kolom-token="<?= e(implode("\t", array_merge(
    [$infoHalaman['induk'], 'Nama', $infoHalaman['kelompok'], 'Token']
))) ?>"></span>

<!-- ==================== TAB JENIS PEMILIH ==================== -->
<section class="kartu kartu-pad">
  <div class="flex flex-wrap items-center gap-3">
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Jenis pemilih">
      <?php foreach (JENIS_PEMILIH as $kunci => $jp):
          $aktif = $jenisHalaman === $kunci;
          $jml   = $jmlJenis[$kunci] ?? 0; ?>
        <a href="admin_dpt.php?jenis=<?= e($kunci) ?>" role="tab" aria-selected="<?= $aktif ? 'true' : 'false' ?>"
           class="<?= $aktif ? 'tombol-utama' : 'tombol-putih' ?>">
          <?= ikon($kunci === 'guru' ? 'paslon' : 'dpt', 'h-4 w-4') ?>
          DPT <?= e($jp['nama']) ?>
          <span class="ml-1 rounded-lg <?= $aktif ? 'bg-white/20' : 'bg-slate-100' ?> px-2 py-0.5 text-xs font-bold"><?= e(angka($jml)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="ml-auto flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-500">
      <span><?= ikon('cek', 'h-3.5 w-3.5 inline') ?> <?= e(angka($stat['masuk'])) ?> sudah memilih</span>
      <span><?= ikon('jam', 'h-3.5 w-3.5 inline') ?> <?= e(angka($stat['belum'])) ?> belum memilih</span>
      <span>Total DPT <?= e(angka($stat['dpt'])) ?></span>
    </div>
  </div>
  <p class="catatan-kecil">
    <b>DPT Siswa</b> memakai <b>NISN</b> &amp; kelas, sedangkan <b>DPT Guru &amp; Tendik</b> memakai
    <b>NIK</b> &amp; jabatan. Keduanya sama-sama satu orang satu suara dan memakai mekanisme token yang sama.
  </p>
</section>

<div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,24rem)_minmax(0,1fr)]">

  <div class="min-w-0 space-y-5 xl:sticky xl:top-24 xl:h-fit">

  <!-- ========================= FORMULIR PEMILIH ========================= -->
  <section class="kartu kartu-pad" id="formPemilih">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul">
          <?= $sedangUbah ? 'Ubah Data Pemilih' : 'Tambah Pemilih ' . e($infoHalaman['nama']) ?>
        </h2>
        <p class="kartu-ket">
          Jenis: <b><?= e($infoHalaman['nama']) ?></b> &middot; token dibuat otomatis bila dikosongkan.
        </p>
      </div>
      <?php if ($sedangUbah): ?><a href="admin_dpt.php" class="tombol-putih tombol-kecil">Batal</a><?php endif; ?>
    </div>

    <form method="post" action="admin_dpt.php" class="mt-4 space-y-4" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) $isian['id'] ?>">
      <input type="hidden" name="jenis_pemilih" value="<?= e((string) $isian['jenis']) ?>">

      <!-- Pemilih jenis ini dibuat pada tab <?= e($infoHalaman['nama']) ?>;
           jenisnya ditentukan tombol tab supaya tidak salah simpan. -->
      <div class="grid gap-2 sm:grid-cols-2">
        <?php foreach (JENIS_PEMILIH as $kunci => $jp): ?>
          <label class="flex cursor-pointer items-center gap-2 rounded-2xl border px-3.5 py-2.5 text-xs font-semibold transition
                        <?= (string) $isian['jenis'] === $kunci ? 'border-brand-500 bg-brand-50 text-brand-800' : 'border-slate-200 bg-slate-50 text-slate-600' ?>">
            <input type="radio" name="jenis_pemilih" value="<?= e($kunci) ?>" data-jenis-pemilih
                   <?= (string) $isian['jenis'] === $kunci ? 'checked' : '' ?>
                   class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-200">
            <?= e($jp['nama']) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div>
        <label class="label" for="nisn" data-label-induk>Nomor Induk Pemilih</label>
        <input class="isian" type="text" id="nisn" name="nisn" required value="<?= e((string) $isian['nisn']) ?>"
               placeholder="Nomor induk" spellcheck="false" data-induk>
        <p class="catatan-kecil" data-ket-induk></p>
      </div>
      <div>
        <label class="label" for="nama">Nama Lengkap</label>
        <input class="isian" type="text" id="nama" name="nama" required maxlength="120" value="<?= e((string) $isian['nama']) ?>"
               placeholder="mis. Aisyah Ramadhani">
      </div>

      <!-- Medan KELAS (siswa) -->
      <div data-medan-kelompok="siswa" class="<?= (string) $isian['jenis'] === 'guru' ? 'hidden' : '' ?>">
        <label class="label" for="kelas">Kelas</label>
        <input class="isian" type="text" id="kelas" name="kelas" maxlength="60" value="<?= e((string) $isian['kelas']) ?>"
               list="daftarKelas" placeholder="mis. XI IPA 1">
        <datalist id="daftarKelas">
          <?php foreach (dpt_kelas_daftar() as $k): ?>
            <option value="<?= e($k) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <!-- Medan JABATAN (guru & tendik) -->
      <div data-medan-kelompok="guru" class="<?= (string) $isian['jenis'] === 'guru' ? '' : 'hidden' ?>">
        <label class="label" for="jabatan">Jabatan</label>
        <input class="isian" type="text" id="jabatan" name="jabatan" maxlength="80" value="<?= e((string) $isian['jabatan']) ?>"
               list="daftarJabatan" placeholder="mis. Guru / Kepala Tata Usaha">
        <datalist id="daftarJabatan">
          <?php foreach (jabatan_pilihan() as $j): ?>
            <option value="<?= e($j) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <p class="catatan-kecil">Boleh dipilih dari daftar atau diketik sendiri.</p>
      </div>
      <div>
        <label class="label" for="token">Token Pemilih <span class="font-normal text-slate-500">(opsional)</span></label>
        <input class="isian isian-rapat uppercase" type="text" id="token" name="token" maxlength="12"
               value="<?= e((string) $isian['token']) ?>" placeholder="kosongkan = acak otomatis" data-token>
        <p class="catatan-kecil">
          <?= $sedangUbah ? 'Biarkan apa adanya bila token tidak ingin diubah.' : 'Kosongkan agar sistem membuat token acak.' ?>
        </p>
      </div>

      <button type="submit" class="tombol-utama w-full py-3">
        <?= ikon($sedangUbah ? 'cek' : 'tambah') ?>
        <?= $sedangUbah ? 'Simpan Perubahan' : 'Tambahkan ke DPT ' . e($infoHalaman['nama']) ?>
      </button>
    </form>

    <div class="mt-4 border-t border-slate-200 pt-4">
      <form method="post" action="admin_dpt.php" data-konfirmasi
            data-pesan="Acak ulang token SEMUA pemilih yang belum memilih? Token lama tidak akan berlaku lagi — pastikan tidak ada token yang sudah dibagikan ke siswa.">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="token_massal">
        <input type="hidden" name="jenis_pemilih" value="<?= e($jenisHalaman) ?>">
        <button type="submit" class="tombol-putih w-full">
          <?= ikon('reset', 'h-4 w-4') ?> Acak Token <?= e($infoHalaman['nama']) ?> yang Belum Memilih
        </button>
      </form>
      <p class="catatan-kecil">Berguna bila daftar token sempat tersebar ke pihak yang tidak berhak.</p>
    </div>
  </section>

  <!-- ==================== IMPOR DATA PEMILIH (Excel/CSV) ==================== -->
  <section class="kartu kartu-pad" id="cardImpor">
    <div class="flex items-start justify-between gap-3">
      <div>
        <h2 class="kartu-judul">Impor Data Pemilih <?= e($infoHalaman['nama']) ?></h2>
        <p class="kartu-ket">Isi DPT <?= e(strtolower($infoHalaman['nama'])) ?> sekaligus dari berkas Excel (.xlsx/.xls) atau CSV.</p>
      </div>
      <span class="stat-ikon bg-emerald-100 text-emerald-700 !h-10 !w-10"><?= ikon('tambah', 'h-5 w-5') ?></span>
    </div>

    <!-- Daftar judul kolom yang dikenali SERVER ikut dikirim ke peramban supaya
         pemeriksaan awal pada berkas memakai aturan yang SAMA (NISN untuk siswa,
         NIK untuk guru). Jangan menulis ulang daftar itu di skrip peramban. -->
    <form method="post" action="admin_dpt.php" enctype="multipart/form-data" class="mt-4 space-y-3" data-impor
          data-kolom-wajib="<?= e(implode(',', dpt_kolom_kenal($jenisHalaman)['nisn'])) ?>"
          data-kolom-lain="<?= e(implode(',', dpt_kolom_jenis_lain($jenisHalaman))) ?>"
          data-jenis-lain="<?= e($jenisHalaman === 'guru' ? 'Siswa' : 'Guru & Tendik') ?>"
          data-label-wajib="<?= e($infoHalaman['induk']) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="impor">
      <input type="hidden" name="jenis_pemilih" value="<?= e($jenisHalaman) ?>">
      <input type="hidden" name="payload" value="" data-impor-payload>

      <div>
        <label class="label" for="berkasImpor">Pilih Berkas</label>
        <input class="isian !py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white"
               type="file" id="berkasImpor" name="berkas" accept=".xlsx,.xls,.csv,text/csv" data-impor-berkas>
        <p class="catatan-kecil">Kolom yang dibaca: <b><?= e($infoHalaman['induk']) ?></b> dan <b>Nama</b> (wajib),
          ditambah <b><?= e($infoHalaman['kelompok']) ?></b> dan <b>Token</b> (opsional — kosong = dibuat otomatis).</p>
      </div>

      <label class="flex items-start gap-2.5 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200">
        <input type="checkbox" name="perbarui" value="1"
               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-200">
        <span class="text-xs text-slate-600">
          <b class="text-slate-800">Perbarui data yang sudah ada</b><br>
          <?= e($infoHalaman['induk']) ?> yang sudah terdaftar akan ditimpa nama/<?= e(strtolower($infoHalaman['kelompok'])) ?>nya.
          Bila tidak dicentang, nomor induk lama dilewati.
          <b>Status sudah/belum memilih tidak pernah diubah</b> oleh impor.
        </span>
      </label>

      <!-- Pratinjau berkas (diisi oleh skrip; tanpa JavaScript berkas tetap dikirim apa adanya) -->
      <div class="hidden rounded-2xl bg-brand-50 p-3.5 ring-1 ring-brand-200" data-impor-pratinjau>
        <p class="text-xs font-bold text-brand-800" data-impor-ringkas>&nbsp;</p>
        <div class="mt-2 max-h-40 overflow-x-auto">
          <table class="w-full min-w-[24rem] border-separate border-spacing-0 text-left text-[11px]">
            <thead><tr data-impor-kepala></tr></thead>
            <tbody data-impor-isi></tbody>
          </table>
        </div>
      </div>
      <p class="hidden text-xs font-semibold text-rose-700" data-impor-galat></p>

      <button type="submit" class="tombol-ok w-full" data-impor-tombol disabled>
        <?= ikon('tambah', 'h-4 w-4') ?> Impor Sekarang
      </button>
    </form>

    <div class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500">
      Belum punya berkasnya? Unduh contoh format beserta judul kolomnya:
      <div class="mt-2 flex flex-wrap gap-2">
        <a href="admin_dpt.php?jenis=<?= e($jenisHalaman) ?>&amp;contoh=csv" class="tombol-putih tombol-kecil"><?= ikon('salin', 'h-3.5 w-3.5') ?> Unduh Contoh CSV <?= e($infoHalaman['nama']) ?></a>
        <a href="cetak_kartu_pemilih.php?jenis=<?= e($jenisHalaman) ?>" class="tombol-putih tombol-kecil"><?= ikon('kotak', 'h-3.5 w-3.5') ?> Cetak Kartu <?= e($infoHalaman['nama']) ?></a>
      </div>
      <p class="catatan-kecil">Impor dapat diulang kapan saja; pemilih yang sudah memilih tidak akan terpengaruh.</p>
    </div>
  </section>
  </div><!-- /.kolom kiri -->

  <!-- ============================ TABEL DPT ============================ -->
  <section class="kartu kartu-pad min-w-0" id="tabelDpt">
    <div class="flex flex-wrap items-center gap-3">
      <h2 class="kartu-judul">Daftar Pemilih Tetap &mdash; <?= e($infoHalaman['nama']) ?></h2>
      <span class="pill-abu" data-cari-info><?= e(angka(count($pemilih))) ?> baris</span>

      <div class="ml-auto flex w-full flex-wrap items-center gap-2 sm:w-auto">
        <div class="relative min-w-[12rem] flex-1">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><?= ikon('cari', 'h-4 w-4') ?></span>
          <input class="isian !pl-9" type="search" data-cari aria-label="Cari pemilih"
                 placeholder="Cari nama, <?= e($infoHalaman['induk']) ?>, atau <?= e(strtolower($infoHalaman['kelompok'])) ?>...">
        </div>
        <select class="isian sm:!w-40" data-saring aria-label="Saring status pemilih">
          <option value="semua">Semua status</option>
          <option value="belum">Belum memilih</option>
          <option value="sudah">Sudah memilih</option>
        </select>
        <!-- Tombol cetak membawa penyaring yang sedang aktif (diisi skrip) -->
        <a href="cetak_kartu_pemilih.php" class="tombol-utama tombol-kecil" data-cetak-kartu
           data-dasar="cetak_kartu_pemilih.php?jenis=<?= e($jenisHalaman) ?>">
          <?= ikon('kotak', 'h-4 w-4') ?> Cetak Kartu
        </a>
      </div>
    </div>

    <div class="bungkus-tabel mt-4">
      <table class="tabel" id="tblDpt">
        <thead>
          <tr>
            <th class="w-12">No</th>
            <th class="w-40"><?= e($infoHalaman['induk']) ?></th>
            <th>Nama Pemilih</th>
            <th class="w-44"><?= e($infoHalaman['kelompok']) ?></th>
            <th class="w-32">Token</th>
            <th class="w-40">Status</th>
            <th class="w-56">Aksi</th>
          </tr>
        </thead>
        <tbody data-tabel-isi>
          <?php if (!$pemilih): ?>
            <tr><td colspan="7" class="py-8 text-center text-slate-500">
              DPT <?= e($infoHalaman['nama']) ?> masih kosong. Tambahkan lewat formulir di samping atau impor berkas Excel/CSV.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($pemilih as $i => $p):
              $sudah = (int) $p['sudah'] === 1; ?>
            <tr data-baris-pemilih
                data-cari-teks="<?= e(strtolower($p['nama'] . ' ' . $p['nisn'] . ' ' . (string) $p['kelas'] . ' ' . (string) ($p['jabatan'] ?? '') . ' ' . $p['token'])) ?>"
                data-status="<?= $sudah ? 'sudah' : 'belum' ?>">
              <td class="text-slate-400" data-nomor><?= $i + 1 ?></td>
              <td class="font-mono text-xs font-semibold text-slate-600"><?= e((string) $p['nisn']) ?></td>
              <td>
                <span class="font-bold text-slate-800"><?= e((string) $p['nama']) ?></span>
                <?php if ($sudah): ?>
                  <span class="block text-[11px] text-slate-500">memilih <?= e(tanggal_id((string) $p['waktu'], false, true)) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-slate-600">
                <?= e((string) ($jenisHalaman === 'guru' ? (($p['jabatan'] ?? '') ?: '-') : ($p['kelas'] ?: '-'))) ?>
              </td>
              <td>
                <span class="inline-flex items-center gap-1.5">
                  <code class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-bold tracking-wider text-slate-700"><?= e((string) $p['token']) ?></code>
                  <button type="button" class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-brand-700"
                          data-salin="<?= e((string) $p['token']) ?>" title="Salin token"><?= ikon('salin', 'h-4 w-4') ?></button>
                </span>
              </td>
              <td>
                <?php if ($sudah): ?>
                  <span class="pill-ok"><?= ikon('cek', 'h-3.5 w-3.5') ?> Sudah memilih</span>
                <?php else: ?>
                  <span class="pill-kuning"><?= ikon('jam', 'h-3.5 w-3.5') ?> Belum memilih</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="flex flex-wrap gap-1.5">
                  <a href="admin_dpt.php?edit=<?= (int) $p['id'] ?>#formPemilih" class="tombol-putih tombol-kecil" title="Ubah data">
                    <?= ikon('edit', 'h-3.5 w-3.5') ?>
                  </a>
                  <?php if (!$sudah): ?>
                    <form method="post" action="admin_dpt.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="token_baru">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="tombol-putih tombol-kecil" title="Buat token baru"><?= ikon('reset', 'h-3.5 w-3.5') ?></button>
                    </form>
                    <form method="post" action="admin_dpt.php" data-konfirmasi
                          data-pesan="Hapus <?= e((string) $p['nama']) ?> dari DPT?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="tombol-bahaya tombol-kecil" title="Hapus pemilih"><?= ikon('hapus', 'h-3.5 w-3.5') ?></button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="admin_dpt.php" data-konfirmasi
                          data-pesan="Kembalikan hak pilih <?= e((string) $p['nama']) ?>? Satu surat suara terakhir akan dibatalkan sehingga jumlah suara berkurang satu.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="batal_pilih">
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                      <button type="submit" class="tombol-putih tombol-kecil" title="Kembalikan hak pilih"><?= ikon('reset', 'h-3.5 w-3.5') ?> Batal pilih</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" class="border-t border-slate-200 px-4 py-3 font-bold">JUMLAH</td>
            <td colspan="5" class="border-t border-slate-200 px-4 py-3 font-bold" data-cari-jumlah>
              <?= e(angka(count($pemilih))) ?> pemilih <?= e(strtolower($infoHalaman['nama'])) ?>
              &middot; <?= e(angka($stat['masuk'])) ?> sudah memilih
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="notis-info mt-4" data-cari-kosong hidden>
      <?= ikon('info') ?>
      <p>Tidak ada pemilih yang cocok dengan pencarian itu.</p>
    </div>

    <div class="notis-kuning mt-4">
      <?= ikon('info') ?>
      <p><b>Alur token:</b> satu token hanya berlaku untuk satu pemilih dan hangus setelah dipakai memilih.
        Tombol <b>Salin Semua Token</b> menyalin daftar
        <code><?= e($infoHalaman['induk']) ?>, Nama, <?= e($infoHalaman['kelompok']) ?>, Token</code>
        pada tab yang sedang dibuka. Tombol <b>Cetak Kartu</b> mencetak
        <?= e(strtolower($infoHalaman['nama'])) ?> dengan isi
        <?= e(implode(', ', array_merge([$infoHalaman['induk']], $jenisHalaman === 'guru' ? ['Jabatan'] : [], [ 'Token' ]))) ?>
        &mdash; 12 kartu per lembar A4.</p>
    </div>
  </section>
</div>

<script type="application/json" id="dataSalinToken"><?= json_encode(array_map(
    static function (array $p) use ($jenisHalaman): string {
        $kelompok = $jenisHalaman === 'guru' ? (string) ($p['jabatan'] ?? '') : (string) $p['kelas'];

        return $p['nisn'] . "\t" . $p['nama'] . "\t" . $kelompok . "\t" . $p['token'];
    },
    $pemilih
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>

<?php page_bottom(['shell' => 'admin', 'skrip' => ['assets/vendor/xlsx.full.min.js']]); ?>
