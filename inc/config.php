<?php
/**
 * inc/config.php — identitas aplikasi, lokasi berkas, dan aturan umum.
 * ---------------------------------------------------------------------------
 * Berkas ini TIDAK boleh diakses langsung dari peramban; hanya di-`require`
 * oleh berkas lain (inc/init.php).
 */
declare(strict_types=1);

/* Cegah berkas ini dijalankan sendiri lewat alamat web. */
if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

/* --- Zona waktu aplikasi -------------------------------------------------- */
/* Permintaan pemilik: seluruh jam memakai WITA (zona Indonesia Tengah), bukan
   zona waktu barat. Madrasah pemilik berada di wilayah Indonesia
   Tengah, jadi LABEL_ZONA dipakai pada setiap tampilan jam.                  */
const ZONA_WAKTU = 'Asia/Makassar';
const LABEL_ZONA = 'WITA';
date_default_timezone_set(ZONA_WAKTU);

/* --- Identitas aplikasi --------------------------------------------------- */
/* Permintaan pemilik (v2.3.0): aplikasi ini KHUSUS madrasah dan memakai
   istilah OSIM. Jangan menulis "OSIS" atau "sekolah" pada tampilan lagi. */
const APP_NAME    = 'E-Voting OSIM';
const APP_SUB     = 'Pemilihan Ketua & Wakil Ketua OSIM';
const APP_TAGLINE = 'Cepat, rahasia, dan transparan.';
const APP_VERSION = '2.5.0';
const APP_AUTHOR  = 'Supianto';

/* Nama berkas aset MENGANDUNG VERSI (mis. app-2.1.0.css).
   Alasan: aset statis di server/CDN dapat tersaji dari simpanan lama
   meski query string (?v=) berubah — dulu halaman sempat tampil tanpa
   gaya karena itu. Dengan nama berkas yang ikut berubah, alamatnya
   benar-benar baru sehingga tidak mungkin tersaji dari simpanan lama. */
define('ASET_CSS', 'assets/app-' . APP_VERSION . '.css');
define('ASET_JS',  'assets/app-' . APP_VERSION . '.js');

/* --- Multi lembaga -------------------------------------------------------
   Sejak v2.0.0 aplikasi dipakai BANYAK madrasah sekaligus:
     - Super Admin  : pengontrol utama (membuat lembaga & akun panitia,
                      memantau hasil seluruh lembaga).
     - Panitia      : hanya lembaga miliknya sendiri.
     - Pemilih      : memilih di lembaga yang dipilihnya di halaman depan.
   Seluruh data (paslon, DPT, suara, pengaturan) dipisah oleh `lembaga_id`.
   JANGAN menulis query tanpa saringan lembaga (lihat lembaga_id_aktif()).   */
const PERAN_SUPER   = 'super';
const PERAN_PANITIA = 'panitia';
const PERAN_PEMILIH = 'pemilih';

/* Lama sandi akun panitia buatan Super Admin minimal 8 karakter. */
const SANDI_MIN = 8;

/* Batas jumlah lembaga (pengaman, bukan kebutuhan) & panjang nama. */
const LEMBAGA_MAKS   = 200;
const LEMBAGA_NAMA_MAKS = 150;

/* --- Lokasi berkas (relatif terhadap folder aplikasi) --------------------- */
define('APP_ROOT', dirname(__DIR__));
const DB_REL     = 'data/voting.sqlite';
const SESI_REL   = 'data/sessions';

/* --- Media proxy platform (unggahan foto kandidat) ------------------------ */
/* Token dibaca server-side dari berkas di folder aplikasi; NEVER dikirim ke
   peramban. Yang disimpan ke basis data hanya URL hasil respons.             */
const MEDIA_ENDPOINT   = 'http://127.0.0.1:4310/api/app-media/upload';
const MEDIA_TOKEN_FILE = '.vibecoder-media-token';
const FOTO_EKSTENSI    = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
const FOTO_MAKS_BYTE   = 15728640; /* 15 MB — batas platform untuk gambar */

/* Logo lembaga (Komisi Pemilihan OSIM) — v2.3.1. Batas 2 MB cukup untuk logo;
   unggahan tetap lewat media proxy platform (yang disimpan hanya URL CDN-nya). */
const LOGO_MAKS_BYTE   = 2097152;

/* --- Aturan aplikasi ------------------------------------------------------ */
const TOKEN_PANJANG   = 8;   /* karakter token pemilih (tanpa tanda hubung) */
const LOGIN_MAKS_COBA = 6;   /* percobaan masuk gagal per sesi… */
const LOGIN_JENDELA   = 600; /* …dalam 10 menit */
const HASIL_POLLING   = 5;   /* jeda penyegaran hasil real-count (detik) */
const TEKS_MAKS       = 4000;

/* Impor data pemilih dari Excel/CSV */
const IMPOR_MAKS_BARIS = 2000;   /* batas baris per sekali impor */

/* Kartu pemilih: 12 kartu per lembar A4 (3 kolom x 4 baris) */
const KARTU_KOLOM = 3;
const KARTU_BARIS = 4;

/* Huruf/angka token: tanpa huruf & angka yang mudah tertukar (I, L, O, 0, 1). */
const TOKEN_ABJAD = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/* Kelas contoh untuk data awal */
const KELAS_CONTOH = ['X IPA 1', 'X IPS 1', 'XI IPA 1', 'XI IPS 1', 'XII IPA 1', 'XII IPS 1'];

/* Kunci pengaturan tingkat aplikasi (tabel `pengaturan`) beserta nilai bawaan. */
/* Jenis lembaga: aplikasi ini KHUSUS MADRASAH (v2.3.0).
   Kolom `lembaga.jenis` tetap ada di basis data agar skema lama tetap terbaca,
   tetapi nilainya SELALU 'madrasah' — tidak ada lagi pilihan jenis. */
const JENIS_LEMBAGA_BAWAAN = 'madrasah';
const JENIS_LEMBAGA_NAMA = [
    'madrasah' => 'Madrasah (MI/MTs/MA/MAK)',
];
const JENIS_LEMBAGA_PENDEK = [
    'madrasah' => 'Madrasah',
];

/* Istilah kegiatan kesiswaan aplikasi ini: OSIM (madrasah). */
const ISTILAH = 'OSIM';
/* Istilah lama yang masih mungkin tersimpan pada data/naskah ketikan pengguna.
   Seluruh keluaran halaman menukarnya menjadi ISTILAH (lihat terjemahkan_istilah()). */
const ISTILAH_LAMA = 'OSIS';

/* Jenis pemilih pada DPT */
const JENIS_PEMILIH = [
    'siswa' => ['nama' => 'Siswa',         'induk' => 'NISN', 'kelompok' => 'Kelas'],
    'guru'  => ['nama' => 'Guru & Tendik', 'induk' => 'NIK',  'kelompok' => 'Jabatan'],
];

/* Pilihan jabatan untuk pemilih guru (boleh diketik sendiri bila tidak ada). */
const JABATAN_PILIHAN = [
    'Kepala Madrasah', 'Wakil Kepala', 'Guru', 'Guru Bimbingan Konseling',
    'Kepala Tata Usaha', 'Staf Tata Usaha', 'Pustakawan', 'Laboran',
    'Petugas Keamanan', 'Petugas Kebersihan', 'Lainnya',
];

/* Pengaturan yang berlaku PER LEMBAGA (tabel pengaturan.lembaga_id = id lembaga).
   Nama & jenis lembaga TIDAK ada di sini: keduanya dibaca dari tabel `lembaga`
   (satu sumber aturan) dan hanya dapat diubah Super Admin.                     */
const PENGATURAN_LEMBAGA_BAWAAN = [
    'nama_pemilihan'    => 'Pemilihan Ketua & Wakil Ketua OSIM',
    'periode'           => 'Tahun Pelajaran 2025/2026',
    'tanggal_pemilihan' => 'Selasa, 20 Januari 2026',
    'tampilkan_hasil'   => '1', /* 1 = hasil boleh dilihat publik, 0 = hanya panitia */
];

/* Pengaturan tingkat APLIKASI (tabel pengaturan.lembaga_id = 0). */
const PENGATURAN_GLOBAL_BAWAAN = [
    'seed_contoh' => '0',
    /* Teks halaman depan — dapat diubah Super Admin lewat menu "Halaman Depan"
       (super_depan.php). Permintaan pemilik v2.3.0. */
    'depan_judul' => 'Pilih Madrasah Anda',
    'depan_ket'   => 'Setiap madrasah menyelenggarakan pemilihan ketua & wakil ketua OSIM secara terpisah. Temukan madrasah Anda di bawah, lalu masuk dengan nomor induk dan token yang dibagikan panitia.',
    /* Logo Komisi Pemilihan OSIM pada halaman depan (v2.4.0): URL CDN hasil
       unggahan Super Admin. Kosong = panel logo memakai monogram bawaan. */
    'depan_logo'  => '',
];

/* Gabungan bawaan (dipakai setting() saat kunci tidak ditemukan). */
const PENGATURAN_BAWAAN = PENGATURAN_LEMBAGA_BAWAAN + PENGATURAN_GLOBAL_BAWAAN;
