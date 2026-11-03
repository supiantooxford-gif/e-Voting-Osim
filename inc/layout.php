<?php
/**
 * inc/layout.php — kerangka halaman (kepala HTML, menu, kaki, notifikasi).
 * ---------------------------------------------------------------------------
 * Tiga kerangka:
 *   'polos'   : tanpa menu (halaman masuk sederhana)
 *   'publik'  : bilah atas ringkas (halaman masuk, hasil publik, halaman sukses)
 *   'admin'   : panel panitia dengan menu samping
 */
declare(strict_types=1);

/* Berkas ini hanya boleh dijalankan lewat inc/init.php. */
if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

/** Ikon SVG (garis) — dipakai menu, tombol, dan kartu statistik. */
function ikon(string $nama, string $kelas = 'h-5 w-5'): string
{
    $jalur = [
        'dashboard' => '<path d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>',
        'paslon'    => '<path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>',
        'dpt'       => '<path d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/>',
        'chart'     => '<path d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z"/><path d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z"/>',
        'setelan'   => '<path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'reset'     => '<path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>',
        'keluar'    => '<path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>',
        'cari'      => '<path d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>',
        'cek'       => '<path d="M4.5 12.75l6 6 9-13.5"/>',
        'salin'     => '<path d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/>',
        'tambah'    => '<path d="M12 4.5v15m7.5-7.5h-15"/>',
        'edit'      => '<path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>',
        'hapus'     => '<path d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>',
        'menu'      => '<path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>',
        'tutup'     => '<path d="M6 18L18 6M6 6l12 12"/>',
        'info'      => '<path d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>',
        'trophy'    => '<path d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0"/>',
        'kunci'     => '<path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>',
        'panah'     => '<path d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>',
        'kotak'     => '<path d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>',
        'jam'       => '<path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'piala'     => '<path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path d="M12 15v6m-3 0h6M6 3h12l-.5 6a5.5 5.5 0 01-11 0L6 3z"/>',
    ];
    $isi = $jalur[$nama] ?? $jalur['info'];

    return '<svg class="' . e($kelas) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

/** Item menu panel panitia. */
function menu_admin(): array
{
    return [
        ['berkas' => 'admin.php',            'label' => 'Dashboard',        'ikon' => 'dashboard'],
        ['berkas' => 'admin_paslon.php',     'label' => 'Pasangan Calon',   'ikon' => 'paslon'],
        ['berkas' => 'admin_dpt.php',        'label' => 'DPT Pemilih',      'ikon' => 'dpt'],
        ['berkas' => 'hasil.php',            'label' => 'Hasil Real-Count', 'ikon' => 'chart'],
        ['berkas' => 'admin_pengaturan.php', 'label' => 'Pengaturan',       'ikon' => 'setelan'],
        ['berkas' => 'admin_reset.php',      'label' => 'Reset Data',       'ikon' => 'reset'],
    ];
}

/** Mencetak notifikasi flash sebagai toast. */
function cetak_toast(): void
{
    $daftar = ambil_flash();
    echo '<div id="toastWadah" class="fixed right-3 top-3 z-[95] flex w-[min(92vw,23rem)] flex-col gap-2" aria-live="polite" aria-atomic="true">';
    foreach ($daftar as $f) {
        $jenis = in_array($f['jenis'], ['ok', 'galat', 'info', 'kuning'], true) ? $f['jenis'] : 'info';
        $kelas = ['ok' => 'notis-ok', 'galat' => 'notis-galat', 'info' => 'notis-info', 'kuning' => 'notis-kuning'][$jenis];
        $ikon  = ['ok' => 'cek', 'galat' => 'info', 'info' => 'info', 'kuning' => 'info'][$jenis];
        echo '<div class="' . $kelas . ' shadow-soft animate-geser" data-toast role="status">'
            . '<span class="mt-0.5 shrink-0">' . ikon($ikon, 'h-5 w-5') . '</span>'
            . '<p class="flex-1">' . e($f['pesan']) . '</p>'
            . '<button type="button" class="shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100" '
            . 'data-toast-tutup aria-label="Tutup pemberitahuan">' . ikon('tutup', 'h-4 w-4') . '</button>'
            . '</div>';
    }
    echo '</div>';
}

/**
 * Membuka halaman.
 *
 * @param string $judul Judul dokumen & judul halaman.
 * @param array  $opsi  shell: 'admin'|'publik'|'polos', sub, aksi (HTML kanan bilah atas)
 */
function page_top(string $judul, array $opsi = []): void
{
    /* Seluruh keluaran halaman ditampung dulu supaya istilah OSIS dapat
       disesuaikan menjadi OSIM pada lembaga madrasah (terjemahkan_selesai()). */
    if (empty($GLOBALS['__ev_buf'])) {
        $GLOBALS['__ev_buf'] = true;
        ob_start();
    }

    $shell = $opsi['shell'] ?? 'publik';
    $sub   = $opsi['sub'] ?? '';
    $aksi  = $opsi['aksi'] ?? '';
    $admin = admin_kini();
    $nama  = $admin ? (string) $admin['nama'] : '';
    ?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#312e81">
<title><?= e($judul) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234f46e5'/%3E%3Cpath d='M9 17l4 4 10-10' stroke='white' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/app.css?v=<?= e(APP_VERSION) ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
<?php cetak_toast(); ?>

<?php if ($shell === 'admin'): ?>
<div class="lg:flex">
  <!-- ==================== MENU SAMPING (panel panitia) ==================== -->
  <aside id="papanSamping" class="fixed inset-y-0 left-0 z-[70] w-72 -translate-x-full overflow-y-auto bg-gradient-to-b from-brand-800 via-brand-800 to-brand-950 p-5 shadow-naik transition duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
         aria-label="Menu panel panitia">
    <div class="flex items-center gap-3">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-white/15 text-white ring-1 ring-white/25">
        <?= ikon('kotak', 'h-6 w-6') ?>
      </span>
      <div class="min-w-0">
        <p class="truncate text-sm font-black uppercase tracking-wide text-white"><?= e(APP_NAME) ?></p>
        <p class="truncate text-xs text-brand-200">Panel Panitia</p>
      </div>
      <button type="button" class="ml-auto rounded-lg p-1.5 text-white/70 transition hover:bg-white/10 hover:text-white lg:hidden"
              data-nav-tutup aria-label="Tutup menu"><?= ikon('tutup') ?></button>
    </div>

    <nav class="mt-7 flex flex-col gap-1.5">
      <?php foreach (menu_admin() as $m):
          $aktif = basename((string) ($_SERVER['PHP_SELF'] ?? '')) === $m['berkas']; ?>
        <a href="<?= e($m['berkas']) ?>" class="<?= $aktif ? 'nav-tautan-aktif' : 'nav-tautan' ?>">
          <?= ikon($m['ikon']) ?>
          <span><?= e($m['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="mt-8 rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
      <p class="text-xs font-semibold uppercase tracking-wide text-brand-200">Masuk sebagai</p>
      <div class="mt-2 flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-500 text-sm font-black text-white">
          <?= e(strtoupper(substr($nama !== '' ? $nama : 'P', 0, 1))) ?>
        </span>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-white"><?= e($nama) ?></p>
          <p class="text-xs text-brand-200">Panitia / Pembina</p>
        </div>
      </div>
      <a href="logout.php" class="mt-3 flex items-center justify-center gap-2 rounded-xl bg-white/15 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/25">
        <?= ikon('keluar', 'h-4 w-4') ?> Keluar
      </a>
    </div>

    <?php if (hasil_publik()): ?>
      <a href="hasil.php" class="mt-4 flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-brand-100 transition hover:bg-white/10">
        <?= ikon('chart', 'h-4 w-4') ?> Lihat papan hasil publik
      </a>
    <?php endif; ?>
  </aside>
  <div id="lapisNav" class="fixed inset-0 z-[60] hidden bg-slate-900/60 lg:hidden" data-nav-tutup></div>

  <div class="min-w-0 flex-1">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
      <div class="flex items-center gap-3 px-4 py-3 lg:px-8">
        <button type="button" class="rounded-xl p-2 text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-100 lg:hidden"
                data-nav-buka aria-label="Buka menu"><?= ikon('menu') ?></button>
        <div class="min-w-0">
          <h1 class="truncate text-base font-black text-slate-900 sm:text-lg"><?= e($judul) ?></h1>
          <?php if ($sub !== ''): ?><p class="truncate text-xs text-slate-500"><?= e($sub) ?></p><?php endif; ?>
        </div>
        <div class="ml-auto flex items-center gap-2"><?= $aksi ?></div>
      </div>
    </header>
    <main class="mx-auto w-full max-w-[92rem] p-4 sm:p-6 lg:p-8">
<?php elseif ($shell === 'publik'): ?>
  <header class="sticky top-0 z-40 border-b border-brand-900/20 bg-gradient-to-r from-brand-900 via-brand-800 to-brand-900 text-white shadow-soft">
    <div class="mx-auto flex w-full max-w-[92rem] flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
      <a href="index.php" class="flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15 ring-1 ring-white/25"><?= ikon('kotak', 'h-5 w-5') ?></span>
        <span class="min-w-0">
          <span class="block truncate text-sm font-black uppercase tracking-wide"><?= e(APP_NAME) ?></span>
          <span class="block truncate text-[11px] text-brand-200"><?= e(APP_SUB) ?></span>
        </span>
      </a>
      <div class="ml-auto flex items-center gap-2"><?= $aksi ?></div>
    </div>
  </header>
  <main class="mx-auto w-full max-w-[92rem] px-4 py-6 sm:px-6 sm:py-8">
<?php else: ?>
  <main class="mx-auto w-full max-w-[92rem] p-4 sm:p-6 lg:p-8">
<?php endif; ?>
    <?php
}

/** Menutup halaman. */
function page_bottom(array $opsi = []): void
{
    $shell = $opsi['shell'] ?? 'publik';
    ?>
    </main>
    <?php if ($shell !== 'polos'): ?>
    <footer class="mx-auto w-full max-w-[92rem] px-4 pb-8 pt-10 text-center text-xs text-slate-500 sm:px-6">
      <p><?= e(APP_NAME) ?> Versi <?= e(APP_VERSION) ?> &middot; <?= e(setting('nama_lembaga')) ?></p>
      <p class="mt-1">Dikembangkan oleh <?= e(APP_AUTHOR) ?> &middot; Sistem pemilihan elektronik OSIS</p>
    </footer>
    <?php endif; ?>
    <?php if ($shell === 'admin'): ?>
      </div><!-- /.min-w-0.flex-1 -->
    </div><!-- /.lg:flex -->
    <?php endif; ?>
    <script src="assets/vendor/chart.umd.min.js"></script>
    <?php foreach ((array) ($opsi['skrip'] ?? []) as $s): ?>
      <script src="<?= e($s) ?>?v=<?= e(APP_VERSION) ?>"></script>
    <?php endforeach; ?>
    <script>window.EV_LABEL_ZONA = '<?= e(LABEL_ZONA) ?>';</script>
    <script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>document.addEventListener('DOMContentLoaded', function () { if (window.EV && EV.siapkan) { EV.siapkan(); } });</script>
</body>
</html>
    <?php
    terjemahkan_selesai();
}
