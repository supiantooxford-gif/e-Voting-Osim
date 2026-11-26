<?php
/**
 * super_masuk.php — INTI HALAMAN MASUK SUPER ADMIN (pengontrol utama aplikasi).
 * ---------------------------------------------------------------------------
 * Terpisah dari halaman masuk lembaga supaya kredensial Super Admin tidak
 * pernah tertukar dengan akun panitia madrasah mana pun.
 *
 * ALAMAT RESMINYA: /<aplikasi>/admin  — dilayani index.php (rute alamat rapi;
 * server menyerahkan jalur yang bukan berkas nyata ke index.php). Berkas ini
 * TIDAK dituju langsung: bila dibuka sendiri, pengunjung dialihkan ke alamat
 * resmi itu supaya hanya ada satu alamat halaman masuk Super Admin.
 */
declare(strict_types=1);

/* Tidak dijalankan lewat index.php (alias /admin) -> alihkan ke alamat resmi.
   Tautan tujuan relatif sehingga benar baik pada alamat subpath platform
   maupun saat aplikasi dipasang pada domain sendiri. */
if (!defined('EV_SUPER_MASUK')) {
    header('Location: admin', true, 302);
    exit;
}

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';

if (is_super()) {
    redirect('super.php');
}

$jmlLembaga = (int) nilai('SELECT COUNT(*) FROM lembaga');
$jmlAktif   = (int) nilai('SELECT COUNT(*) FROM lembaga WHERE aktif = 1');

page_top('Masuk Super Admin', ['shell' => 'polos', 'aksi' =>
    '<a href="index.php" class="tombol-putih tombol-kecil">' . ikon('kembali', 'h-4 w-4') . ' Halaman Depan</a>']);
?>

<div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_minmax(0,24rem)] lg:items-center">

  <section class="animate-masuk overflow-hidden rounded-3xl bg-gradient-to-br from-brand-800 via-brand-800 to-brand-950 p-7 text-white shadow-naik ring-1 ring-emas-300/30 sm:p-9">
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-emas-400/20 text-emas-200 ring-1 ring-emas-300/40"><?= ikon('lembaga', 'h-7 w-7') ?></span>
    <h1 class="mt-4 text-2xl font-black leading-tight sm:text-3xl">Panel Super Admin</h1>
    <p class="mt-2 max-w-md text-sm leading-relaxed text-brand-100">
      Pengontrol utama aplikasi <?= e(APP_NAME) ?>. Dari sini Anda menambah madrasah,
      membuat akun panitia tiap lembaga, dan memantau hasil pemilihan seluruh lembaga.
    </p>

    <dl class="mt-6 grid grid-cols-2 gap-3">
      <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-200">Lembaga</dt>
        <dd class="mt-1 text-2xl font-black"><?= e(angka($jmlLembaga)) ?></dd>
      </div>
      <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-brand-200">Aktif</dt>
        <dd class="mt-1 text-2xl font-black text-emas-300"><?= e(angka($jmlAktif)) ?></dd>
      </div>
    </dl>

    <p class="mt-5 text-xs leading-relaxed text-brand-200">
      Panitia madrasah TIDAK masuk lewat halaman ini. Mereka masuk melalui kartu lembaganya
      di halaman depan.
    </p>
  </section>

  <section class="animate-masuk">
    <div class="kartu kartu-pad">
      <h2 class="kartu-judul">Masuk Super Admin</h2>
      <p class="kartu-ket">Gunakan akun Super Admin aplikasi.</p>

      <form method="post" action="login.php?peran=super" class="mt-4 space-y-4">
        <?= csrf_field() ?>
        <div>
          <label class="label" for="username">Nama Pengguna</label>
          <input class="isian" type="text" id="username" name="username" required autocomplete="username"
                 placeholder="Nama pengguna Super Admin" spellcheck="false" autofocus>
        </div>
        <div>
          <label class="label" for="sandi">Kata Sandi</label>
          <input class="isian" type="password" id="sandi" name="sandi" required autocomplete="current-password"
                 placeholder="Kata sandi Super Admin">
        </div>
        <button type="submit" class="tombol-utama w-full py-3 text-base">
          <?= ikon('kunci') ?> Masuk
        </button>
      </form>

      <p class="catatan-kecil">
        Ingin memberikan suara atau masuk sebagai panitia?
        <a href="index.php" class="font-bold text-brand-700 underline">Pilih madrasah di halaman depan</a>.
      </p>
    </div>
  </section>
</div>

<?php page_bottom(['shell' => 'polos']); ?>
