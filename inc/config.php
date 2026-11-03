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
   zona waktu barat. Sekolah/madrasah pemilik berada di wilayah Indonesia
   Tengah, jadi LABEL_ZONA dipakai pada setiap tampilan jam.                  */
const ZONA_WAKTU = 'Asia/Makassar';
const LABEL_ZONA = 'WITA';
date_default_timezone_set(ZONA_WAKTU);

/* --- Identitas aplikasi --------------------------------------------------- */
/* Nama aplikasi ditulis dengan istilah umum "OSIS". Pada lembaga berjenis
   MADRASAH seluruh tulisan OSIS otomatis menjadi OSIM (lihat istilah_osis()
   di inc/helpers.php) — jangan menulis OSIM di sini. */
const APP_NAME    = 'E-Voting OSIS';
const APP_SUB     = 'Pemilihan Ketua & Wakil Ketua OSIS';
const APP_TAGLINE = 'Cepat, rahasia, dan transparan.';
const APP_VERSION = '1.2.2';
const APP_AUTHOR  = 'Supianto';

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
/* Jenis lembaga: 'madrasah' (OSIM) atau 'umum' (OSIS).
   Bawaan 'madrasah' karena instalasi ini dipakai madrasah (MA/MTs).          */
const JENIS_LEMBAGA_BAWAAN = 'madrasah';
const JENIS_LEMBAGA_NAMA = [
    'umum'     => 'Sekolah Umum (SD/SMP/SMA/SMK)',
    'madrasah' => 'Madrasah (MI/MTs/MA/MAK)',
];

/* Istilah kegiatan kesiswaan: OSIS pada sekolah umum, OSIM pada madrasah */
const ISTILAH_UMUM  = 'OSIS';
const ISTILAH_MADRA = 'OSIM';

/* Jenis pemilih pada DPT */
const JENIS_PEMILIH = [
    'siswa' => ['nama' => 'Siswa',         'induk' => 'NISN', 'kelompok' => 'Kelas'],
    'guru'  => ['nama' => 'Guru & Tendik', 'induk' => 'NIK',  'kelompok' => 'Jabatan'],
];

/* Pilihan jabatan untuk pemilih guru (boleh diketik sendiri bila tidak ada). */
const JABATAN_PILIHAN = [
    'Kepala Sekolah/Madrasah', 'Wakil Kepala', 'Guru', 'Guru Bimbingan Konseling',
    'Kepala Tata Usaha', 'Staf Tata Usaha', 'Pustakawan', 'Laboran',
    'Petugas Keamanan', 'Petugas Kebersihan', 'Lainnya',
];

const PENGATURAN_BAWAAN = [
    'jenis_lembaga'    => JENIS_LEMBAGA_BAWAAN,
    'nama_pemilihan'   => 'Pemilihan Ketua & Wakil Ketua OSIS',
    'nama_lembaga'     => 'SMA Negeri 1 Nusantara',
    'periode'          => 'Tahun Pelajaran 2025/2026',
    'tanggal_pemilihan' => 'Selasa, 20 Januari 2026',
    'tampilkan_hasil'  => '1', /* 1 = hasil boleh dilihat publik, 0 = hanya panitia */
];
