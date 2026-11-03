<?php
/**
 * cetak_kartu_pemilih.php — CETAK KARTU PEMILIH (A4, 12 kartu per lembar).
 * ---------------------------------------------------------------------------
 * Setiap kartu memuat:
 *   - pemilih SISWA : Nama, NISN, Kelas, Token
 *   - pemilih GURU  : Nama, NIK, Jabatan, Token   (permintaan pemilik)
 * Susunan kartu: 3 kolom x 4 baris = 12 kartu per lembar A4 potrait
 * (KARTU_KOLOM/KARTU_BARIS di inc/config.php) dan pemenggalan halaman
 * dilakukan per blok 12 kartu, bukan bergantung perkiraan tinggi peramban.
 *
 * Penyaring (?cari=&status=&kelas=) sama seperti halaman DPT, sehingga lembar
 * yang dicetak selalu sesuai dengan tabel yang sedang dilihat panitia.
 * Hanya panitia yang dapat membukanya (require_admin()).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

/* Seluruh keluaran ditampung untuk penyesuaian istilah OSIS/OSIM. */
ob_start();

$f       = dpt_filter_get();
$pemilih = dpt_pemilih_saring($f);
$info    = jenis_pemilih_info($f['jenis']);
$auto    = ($_GET['auto'] ?? '') === '1';
$perHal  = KARTU_KOLOM * KARTU_BARIS;

if (!$pemilih) {
    flash('kuning', 'Tidak ada pemilih yang cocok dengan penyaring kartu. Ubah penyaring lalu coba lagi.');
    redirect('admin_dpt.php?jenis=' . $f['jenis']);
}

/* Kartu dibagi per lembar (12 kartu) supaya pemenggalan halaman pasti. */
$lembar = array_chunk($pemilih, $perHal);
$namaLembaga = setting('nama_lembaga');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kartu Pemilih &middot; <?= e($namaLembaga) ?></title>
<style>
  /* Ukuran halaman A4 potrait — WAJIB ada di <style> halaman ini, bukan di
     berkas tema; tanpa itu PDF yang dihasilkan menjadi ukuran Letter. */
  @page { size: A4 portrait; margin: 8mm; }

  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 10px;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    color: #0f172a; background: #e2e8f0;
  }

  /* ---------------------------- Bilah pengaturan ---------------------------- */
  .bar {
    position: sticky; top: 0; z-index: 20; display: flex; flex-wrap: wrap; gap: 10px;
    align-items: center; margin: 0 auto 14px; max-width: 210mm; padding: 12px 14px;
    background: #fff; border-radius: 14px; box-shadow: 0 8px 22px -12px rgb(15 23 42 / .35);
  }
  .bar h1 { margin: 0; font-size: 15px; }
  .bar p { margin: 2px 0 0; font-size: 11px; color: #64748b; }
  .bar form { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-left: auto; }
  .bar input, .bar select {
    padding: 6px 9px; border: 1px solid #cbd5e1; border-radius: 9px; font-size: 12px; background: #fff;
  }
  .bar input[type="search"] { min-width: 11rem; }
  .btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 13px; border: 0; border-radius: 10px;
    background: #4f46e5; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none;
  }
  .btn:hover { background: #4338ca; }
  .btn-putih { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
  .btn-putih:hover { background: #f1f5f9; }
  .ket { max-width: 210mm; margin: 0 auto 14px; font-size: 11px; color: #475569; }

  /* Di layar kecil lembar A4 (194mm) TIDAK diciutkan supaya tampilan tetap persis
     seperti hasil cetak; wadahnya yang dapat digeser mendatar. Tanpa wadah ini
     halaman meluber di HP (tertangkap uji Playwright). */
  .cetak { overflow-x: auto; padding-bottom: 8px; }

  /* -------------------------------- Lembar -------------------------------- */
  .lembar {
    width: 194mm; min-height: 281mm; margin: 0 auto 14px; padding: 0;
    background: #fff; border-radius: 4px; box-shadow: 0 10px 30px -18px rgb(15 23 42 / .45);
    display: grid; grid-template-columns: repeat(<?= (int) KARTU_KOLOM ?>, 1fr);
    grid-auto-rows: 66mm; gap: 4mm; align-content: start;
  }

  /* --------------------------------- Kartu -------------------------------- */
  .kartu {
    display: flex; flex-direction: column; overflow: hidden;
    border: 1.4px solid #4f46e5; border-radius: 3mm; background: #fff;
    break-inside: avoid; page-break-inside: avoid;
  }
  .kartu-kepala {
    display: flex; align-items: center; gap: 6px; padding: 5px 8px;
    background: linear-gradient(120deg, #312e81, #4f46e5); color: #fff;
  }
  .kartu-kepala .lambang {
    display: grid; place-items: center; width: 22px; height: 22px; flex: 0 0 auto;
    border-radius: 6px; background: rgb(255 255 255 / .2); font-size: 11px; font-weight: 800;
  }
  .kartu-kepala .teks { min-width: 0; }
  .kartu-kepala .sekolah {
    font-size: 8.4px; font-weight: 800; text-transform: uppercase; letter-spacing: .02em;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .kartu-kepala .jenis { font-size: 7px; letter-spacing: .16em; text-transform: uppercase; opacity: .85; }

  .kartu-isi { flex: 1; padding: 7px 9px 4px; display: flex; flex-direction: column; gap: 4px; }
  .baris { display: flex; gap: 6px; align-items: baseline; }
  .baris .label {
    flex: 0 0 34px; font-size: 7.6px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #64748b;
  }
  .baris .nilai { font-size: 11px; font-weight: 700; color: #0f172a; min-width: 0; word-break: break-word; }
  .baris .nilai.nama { font-size: 12.6px; line-height: 1.15; }
  .baris .nilai.mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: .02em; }

  .token {
    margin-top: auto; padding: 5px 7px; border: 1.4px dashed #4f46e5; border-radius: 8px;
    background: #eef2ff; text-align: center;
  }
  .token .label {
    font-size: 6.8px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; color: #4338ca;
  }
  .token .kode {
    margin-top: 1px; font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
    font-size: 15px; font-weight: 800; letter-spacing: .1em; color: #1e1b4b;
  }

  .kartu-kaki {
    padding: 3px 8px 4px; font-size: 6.8px; line-height: 1.25; color: #64748b;
    border-top: 1px solid #e2e8f0; background: #f8fafc;
  }
  .kartu-kaki b { color: #334155; }

  /* Status sudah/belum memilih cukup sebagai penanda kecil di kaki kartu */
  .pil {
    display: inline-block; padding: 0 4px; border-radius: 999px; font-size: 6.4px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .06em;
  }
  .pil-belum { background: #fef3c7; color: #92400e; }
  .pil-sudah { background: #d1fae5; color: #065f46; }

  /* --------------------------- Mode cetak --------------------------------- */
  @media print {
    body { padding: 0; background: #fff; }
    .bar, .ket { display: none !important; }
    /* Saat dicetak wadah gulir itu WAJIB dilepas: `overflow:auto` menjadikan
       .cetak wadah gulir tersendiri, dan isinya tidak lagi dipenggal rapi per
       halaman (gejala: muncul halaman kosong/berlebih pada PDF). */
    .cetak { overflow: visible !important; padding-bottom: 0 !important; }
    .lembar {
      width: auto; min-height: 0; margin: 0; box-shadow: none; border-radius: 0;
    }
    /* Pemenggalan halaman dipasang ANTAR lembar (bukan "break-after" pada tiap
       lembar): cara itu tidak pernah menyisakan halaman kosong di akhir. */
    .lembar + .lembar { break-before: page; page-break-before: always; }
    /* Warna kepala kartu & kotak token WAJIB ikut tercetak */
    .kartu, .kartu-kepala, .token, .pil { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>

<!-- ======================= BILAH PENGATURAN & CETAK ======================= -->
<div class="bar">
  <div>
    <h1>Kartu Pemilih <?= e($info['nama']) ?> &mdash; <?= e($namaLembaga) ?></h1>
    <p>
      <?= e(angka(count($pemilih))) ?> kartu &middot; <?= e((string) $perHal) ?> kartu per lembar A4
      &middot; <?= e(angka(count($lembar))) ?> lembar
      <?= $f['cari'] !== '' ? ' &middot; cari: ' . e($f['cari']) : '' ?>
      &middot; <?= e(strtolower($info['kelompok'])) ?>: <?= $f['kelas'] !== '' ? e($f['kelas']) : 'semua' ?>
      <?= $f['status'] !== 'semua' ? ' &middot; status: ' . e($f['status'] === 'sudah' ? 'sudah memilih' : 'belum memilih') : '' ?>
    </p>
  </div>

  <form method="get" action="cetak_kartu_pemilih.php">
    <select name="jenis" aria-label="Jenis pemilih">
      <?php foreach (JENIS_PEMILIH as $kunci => $jp): ?>
        <option value="<?= e($kunci) ?>" <?= $f['jenis'] === $kunci ? 'selected' : '' ?>><?= e($jp['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="search" name="cari" value="<?= e($f['cari']) ?>" placeholder="Cari nama/<?= e($info['induk']) ?>..." aria-label="Cari pemilih">
    <select name="kelas" aria-label="Saring <?= e(strtolower($info['kelompok'])) ?>">
      <option value="">Semua <?= e(strtolower($info['kelompok'])) ?></option>
      <?php foreach ($f['jenis'] === 'guru' ? dpt_jabatan_daftar() : dpt_kelas_daftar() as $k): ?>
        <option value="<?= e($k) ?>" <?= $f['kelas'] === $k ? 'selected' : '' ?>><?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" aria-label="Saring status memilih">
      <option value="semua" <?= $f['status'] === 'semua' ? 'selected' : '' ?>>Semua status</option>
      <option value="belum" <?= $f['status'] === 'belum' ? 'selected' : '' ?>>Belum memilih</option>
      <option value="sudah" <?= $f['status'] === 'sudah' ? 'selected' : '' ?>>Sudah memilih</option>
    </select>
    <button type="submit" class="btn btn-putih">Terapkan</button>
    <?php if ($f['cari'] !== '' || $f['kelas'] !== '' || $f['status'] !== 'semua'): ?>
      <a href="cetak_kartu_pemilih.php" class="btn btn-putih">Reset</a>
    <?php endif; ?>
  </form>

  <button type="button" class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
  <a href="admin_dpt.php?jenis=<?= e($f['jenis']) ?>" class="btn btn-putih">Kembali ke DPT</a>
</div>

<p class="ket">
  Cetak pada kertas <b>A4</b> dengan skala <b>100%</b> (jangan "Fit to page") dan aktifkan
  <b>cetak warna latar</b> agar kepala kartu serta kotak token ikut tercetak.
  Kartu dipotong mengikuti garis tepi: setiap lembar berisi <?= e((string) $perHal) ?> kartu.
</p>

<!-- ============================== ISI KARTU ============================== -->
<div class="cetak">
<?php foreach ($lembar as $hal => $isiLembar): ?>
  <section class="lembar" data-lembar="<?= $hal + 1 ?>">
    <?php foreach ($isiLembar as $p):
        $sudah = (int) $p['sudah'] === 1;
        $inisial = strtoupper(substr((string) $p['nama'], 0, 1)); ?>
      <article class="kartu">
        <header class="kartu-kepala">
          <span class="lambang"><?= e($inisial) ?></span>
          <span class="teks">
            <span class="sekolah"><?= e($namaLembaga) ?></span>
            <span class="jenis">Kartu Pemilih</span>
          </span>
        </header>

        <div class="kartu-isi">
          <span class="baris"><span class="label">Nama</span><span class="nilai nama"><?= e((string) $p['nama']) ?></span></span>
          <span class="baris">
            <span class="label"><?= e($info['induk']) ?></span>
            <span class="nilai mono"><?= e((string) $p['nisn']) ?></span>
          </span>
          <span class="baris">
            <span class="label"><?= e($info['kelompok']) ?></span>
            <span class="nilai"><?= e((string) ($f['jenis'] === 'guru'
                ? (($p['jabatan'] ?? '') ?: '-')
                : ($p['kelas'] ?: '-'))) ?></span>
          </span>
          <span class="token">
            <span class="label">Token Pemilih</span>
            <span class="kode"><?= e((string) $p['token']) ?></span>
          </span>
        </div>

        <footer class="kartu-kaki">
          <b>Simpan kartu ini.</b> Masuk dengan <?= e($info['induk']) ?> &amp; token di atas, lalu pilih satu pasangan calon.
          Token hanya berlaku <b>sekali</b>.
          <span class="pil <?= $sudah ? 'pil-sudah' : 'pil-belum' ?>"><?= $sudah ? 'sudah memilih' : 'belum memilih' ?></span>
        </footer>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div><!-- /.cetak -->

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { window.setTimeout(function () { window.print(); }, 350); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
