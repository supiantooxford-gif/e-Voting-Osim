<?php
/**
 * admin.php — DASHBOARD PANEL PANITIA.
 * ---------------------------------------------------------------------------
 * Ringkasan cepat: statistik, keutuhan data, perolehan sementara, grafik DPT
 * per kelas, dan pintasan ke menu pengelolaan. Histori aktivitas TIDAK lagi di
 * sini — tempatnya di akun Super Admin (super_histori.php), permintaan pemilik
 * v2.5.0.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

require_admin();

$h      = hasil_lengkap();
$integ  = integritas_data();
$paslon = paslon_list(true);
$dptKelas = dpt_per_kelas();
$lembaga = lembaga_aktif();

page_top('Dashboard Panitia', [
    'shell' => 'admin',
    'sub'   => setting('nama_pemilihan') . ' &middot; ' . setting('periode'),
    'aksi'  => '<a href="hasil.php" class="tombol-putih tombol-kecil">' . ikon('chart', 'h-4 w-4') . ' Papan Hasil</a>'
        . '<a href="admin_dpt.php" class="tombol-utama tombol-kecil">' . ikon('dpt', 'h-4 w-4') . ' Kelola DPT</a>',
]);
?>

<!-- Identitas lembaga yang sedang dikelola -->
<section class="mb-5 flex flex-wrap items-center gap-4 rounded-2xl bg-white p-4 shadow-soft ring-1 ring-slate-200/80">
  <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-100 text-sm font-black text-brand-800">
    <?= e($lembaga !== false ? lembaga_inisial($lembaga) : 'L') ?>
  </span>
  <div class="min-w-0 flex-1">
    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Madrasah</p>
    <p class="truncate text-base font-black text-slate-900"><?= e(setting('nama_lembaga')) ?></p>
    <p class="text-xs text-slate-500">
      <?= e(jenis_lembaga_label()) ?> &middot; <?= e(istilah_kegiatan()) ?>
      <?= $lembaga !== false && !empty($lembaga['npsn']) ? ' &middot; NPSN ' . e((string) $lembaga['npsn']) : '' ?>
    </p>
  </div>
  <a href="admin_pengaturan.php" class="tombol-putih tombol-kecil"><?= ikon('setelan', 'h-4 w-4') ?> Pengaturan</a>
</section>

<!-- ============================== STATISTIK ============================== -->
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Statistik pemilihan">
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-100 text-brand-700"><?= ikon('dpt') ?></span>
    <div><p class="stat-label">Pemilih Terdaftar</p><p class="stat-angka"><?= e(angka($h['dpt'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-emerald-100 text-emerald-700"><?= ikon('kotak') ?></span>
    <div><p class="stat-label">Sudah Memilih</p><p class="stat-angka text-emerald-700"><?= e(angka($h['masuk'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-amber-100 text-amber-700"><?= ikon('jam') ?></span>
    <div><p class="stat-label">Belum Memilih</p><p class="stat-angka text-amber-700"><?= e(angka($h['belum'])) ?></p></div>
  </div>
  <div class="stat-kartu">
    <span class="stat-ikon bg-brand-900 text-white"><?= ikon('trophy') ?></span>
    <div class="min-w-0 flex-1">
      <p class="stat-label">Partisipasi</p>
      <p class="stat-angka"><?= e(persen($h['turnout'])) ?></p>
      <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-brand-600" style="width: <?= e((string) min(100, $h['turnout'])) ?>%"></div>
      </div>
    </div>
  </div>
</section>

<div class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
  <div class="space-y-5">

    <!-- ======================= PEROLEHAN SEMENTARA ======================= -->
    <section class="kartu kartu-pad">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="kartu-judul">Perolehan Suara Sementara</h2>
        <span class="pill-abu"><?= ikon('jam', 'h-3.5 w-3.5') ?> Diperbarui <?= e(date('H:i:s')) ?> <?= e(LABEL_ZONA) ?></span>
      </div>

      <div class="mt-4 space-y-3">
        <?php if (!$h['paslon']): ?>
          <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
            Belum ada pasangan calon. Tambahkan lewat menu <a class="font-bold text-brand-700 underline" href="admin_paslon.php">Pasangan Calon</a>.
          </p>
        <?php endif; ?>
        <?php foreach ($h['paslon'] as $p): ?>
          <div class="baris-hasil">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl text-sm font-black text-white" style="background: <?= e($p['warna']) ?>">
              <?= e((string) $p['nomor']) ?>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-bold text-slate-800"><?= e($p['ketua']) ?> &amp; <?= e($p['wakil']) ?></p>
              <div class="bar-luar mt-1.5">
                <div class="bar-dalam" style="width: <?= e((string) $p['persen']) ?>%; background: <?= e($p['warna']) ?>"></div>
              </div>
            </div>
            <div class="w-20 shrink-0 text-right">
              <p class="text-base font-black text-slate-900"><?= e(angka($p['suara'])) ?></p>
              <p class="text-xs font-semibold text-slate-500"><?= e(persen($p['persen'])) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ====================== GRAFIK DPT PER KELAS ====================== -->
    <section class="kartu kartu-pad">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="kartu-judul">Grafik DPT per Kelas</h2>
          <p class="kartu-ket">Sebaran pemilih terdaftar menurut kelas &mdash; dibedakan antara yang sudah dan belum memilih.</p>
        </div>
        <span class="pill-abu"><?= ikon('dpt', 'h-3.5 w-3.5') ?> <?= e(angka($dptKelas['dpt'])) ?> pemilih</span>
      </div>

      <?php if (!$dptKelas['baris']): ?>
        <p class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
          Belum ada pemilih terdaftar. Tambahkan lewat menu
          <a class="font-bold text-brand-700 underline" href="admin_dpt.php">DPT Pemilih</a>.
        </p>
      <?php else:
          /* Data grafik dibaca assets/app.js (#dataDptKelas). Tinggi wadah
             mengikuti jumlah bar supaya label kelas tidak berdesakan. */
          $grafikJs = [
              'baris' => array_map(static fn (array $b): array => [
                  'label' => $b['label'], 'dpt' => $b['dpt'],
                  'sudah' => $b['sudah'], 'belum' => $b['belum'],
              ], $dptKelas['baris']),
              'dpt'   => $dptKelas['dpt'],
              'sudah' => $dptKelas['sudah'],
              'belum' => $dptKelas['belum'],
          ];
          $tinggi = max(190, 44 * count($dptKelas['baris']) + 24); ?>
        <script type="application/json" id="dataDptKelas"><?= json_encode($grafikJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
        <div class="mt-4 w-full" style="height: <?= (int) $tinggi ?>px">
          <canvas id="chDptKelas" role="img"
                  aria-label="Grafik DPT per kelas: <?= e((string) count($dptKelas['baris'])) ?> kelompok pemilih"></canvas>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-semibold text-slate-600">
          <span class="inline-flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span> Sudah memilih (<?= e(angka($dptKelas['sudah'])) ?>)
          </span>
          <span class="inline-flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Belum memilih (<?= e(angka($dptKelas['belum'])) ?>)
          </span>
          <span class="text-slate-500">Panjang bar = jumlah DPT kelompok itu</span>
        </div>

        <details class="mt-4 rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200">
          <summary class="cursor-pointer text-sm font-bold text-slate-700">Lihat angka per kelas</summary>
          <div class="bungkus-tabel mt-3 min-w-0">
            <?php /* Tabel ringkas 4 kolom: lebar minimum bawaan .tabel (42rem)
                     tidak diperlukan dan membuat kartu ini melebarkan halaman
                     di layar HP — jadi dilepas di sini saja. */ ?>
            <table class="tabel !min-w-0">
              <thead>
                <tr><th>Kelas</th><th class="w-24">DPT</th><th class="w-28">Sudah</th><th class="w-28">Belum</th></tr>
              </thead>
              <tbody>
                <?php foreach ($dptKelas['baris'] as $b): ?>
                  <tr>
                    <td class="font-semibold text-slate-700"><?= e($b['label']) ?></td>
                    <td class="font-bold"><?= e(angka($b['dpt'])) ?></td>
                    <td class="text-emerald-700"><?= e(angka($b['sudah'])) ?></td>
                    <td class="text-amber-700"><?= e(angka($b['belum'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td class="border-t border-slate-200 px-4 py-3 font-black">JUMLAH</td>
                  <td class="border-t border-slate-200 px-4 py-3 font-black"><?= e(angka($dptKelas['dpt'])) ?></td>
                  <td class="border-t border-slate-200 px-4 py-3 font-black text-emerald-700"><?= e(angka($dptKelas['sudah'])) ?></td>
                  <td class="border-t border-slate-200 px-4 py-3 font-black text-amber-700"><?= e(angka($dptKelas['belum'])) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </details>

        <?php if ($dptKelas['guru'] > 0): ?>
          <p class="catatan-kecil">Bar <b>Guru &amp; Tendik</b> menghitung DPT guru dan tenaga kependidikan yang tidak memiliki kelas.</p>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <!-- ============================ KOLOM KANAN ============================ -->
  <div class="space-y-5">
    <!-- Keutuhan data -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Keutuhan Data</h2>
      <div class="<?= $integ['cocok'] ? 'notis-ok' : 'notis-galat' ?> mt-3">
        <?= ikon($integ['cocok'] ? 'cek' : 'info') ?>
        <p>
          <?php if ($integ['cocok']): ?>
            Jumlah surat suara (<?= e(angka($integ['suara'])) ?>) sama dengan jumlah pemilih bertanda
            sudah memilih (<?= e(angka($integ['pemilih'])) ?>). Tidak ada suara hilang atau berlebih.
          <?php else: ?>
            Selisih terdeteksi: surat suara <?= e(angka($integ['suara'])) ?>,
            pemilih bertanda sudah memilih <?= e(angka($integ['pemilih'])) ?>.
            Periksa halaman DPT lalu laporkan ke pengembang.
          <?php endif; ?>
        </p>
      </div>

      <dl class="mt-4 space-y-2 text-sm">
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Pasangan calon</dt><dd class="font-bold"><?= e(angka(count($paslon))) ?></dd>
        </div>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Paslon aktif</dt><dd class="font-bold"><?= e(angka(paslon_aktif_jumlah())) ?></dd>
        </div>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-2.5 ring-1 ring-slate-200">
          <dt class="text-slate-600">Hasil untuk publik</dt>
          <dd class="font-bold"><?= hasil_publik() ? '<span class="pill-ok">Dibuka</span>' : '<span class="pill-kuning">Ditutup</span>' ?></dd>
        </div>
      </dl>
    </section>

    <!-- Pintasan -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Pintasan</h2>
      <div class="mt-3 grid gap-2.5">
        <?php foreach ([
            ['admin_paslon.php', 'paslon', 'Kelola pasangan calon', 'Tambah, ubah, urutkan, unggah foto'],
            ['admin_dpt.php', 'dpt', 'Kelola DPT pemilih', 'Tambah siswa, token, status memilih'],
            ['hasil.php', 'chart', 'Papan hasil real-count', 'Grafik & partisipasi berjalan'],
            ['admin_pengaturan.php', 'setelan', 'Pengaturan & keamanan', 'Identitas, ganti sandi panitia'],
            ['admin_reset.php', 'reset', 'Reset data', 'Kosongkan perolehan suara'],
        ] as $p): ?>
          <a href="<?= e($p[0]) ?>" class="flex items-start gap-3 rounded-2xl bg-slate-50 p-3.5 ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:bg-white hover:shadow-soft">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700"><?= ikon($p[1]) ?></span>
            <span class="min-w-0">
              <span class="block text-sm font-bold text-slate-800"><?= e($p[2]) ?></span>
              <span class="block text-xs text-slate-500"><?= e($p[3]) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Cara pembagian token -->
    <section class="kartu kartu-pad">
      <h2 class="kartu-judul">Alur Pemungutan Suara</h2>
      <ol class="mt-3 space-y-2 text-sm text-slate-600">
        <?php foreach ([
            'Bagikan NISN &amp; token kepada siswa (lihat menu DPT).',
            'Siswa masuk, membaca visi-misi, lalu menekan PILIH PASLON.',
            'Suara tersimpan; siswa otomatis keluar (siap pemilih berikutnya).',
            'Pantau papan hasil real-count sampai pemungutan selesai.',
        ] as $i => $t): ?>
          <li class="flex gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg bg-brand-600 text-xs font-black text-white"><?= $i + 1 ?></span>
            <span><?= $t ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>
</div>

<?php page_bottom(['shell' => 'admin']); ?>
