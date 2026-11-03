<?php
/**
 * inc/db.php — pembuka basis data SQLite + skema + data contoh.
 * ---------------------------------------------------------------------------
 * Seluruh aplikasi memakai satu berkas SQLite di data/voting.sqlite.
 * Catatan penting (pelajaran dari aplikasi lain di workspace ini):
 *   1. Penanda skema bisa LEBIH BARU daripada isi basis data, karena itu
 *      `skema_terkini()` memeriksa langsung keberadaan tabel/kolom wajib.
 *   2. Semua tulisan banyak-tabel dibungkus satu `BEGIN IMMEDIATE`.
 *   3. Mode jurnal WAL + busy_timeout supaya beberapa permintaan bersamaan
 *      (banyak siswa memilih serentak) tidak saling mengunci.
 */
declare(strict_types=1);

/* Berkas ini hanya boleh dijalankan lewat inc/init.php. */
if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

const SCHEMA_VERSION = '2';

/* Tabel & kolom yang WAJIB ada. Bila penanda skema sudah menyamai versi
   terbaru tetapi ada isi yang belum ada (migrasi terputus), schema dijalankan
   ulang — semuanya idempoten sehingga aman. */
const SCHEMA_TABEL_WAJIB = ['pengaturan', 'admin', 'paslon', 'pemilih', 'suara', 'aktivitas'];
const SCHEMA_KOLOM_WAJIB = [
    'pengaturan' => ['kunci', 'nilai'],
    'admin'      => ['username', 'sandi'],
    'paslon'     => ['nomor', 'ketua', 'wakil', 'visi', 'misi', 'program', 'foto', 'aktif'],
    'pemilih'    => ['nisn', 'nama', 'kelas', 'token', 'sudah', 'waktu', 'jenis', 'jabatan'],
    'suara'      => ['paslon_id', 'dibuat'],
    'aktivitas'  => ['waktu', 'jenis', 'keterangan', 'ip', 'peramban'],
];

/** Lokasi lengkap berkas basis data. */
function db_file(): string
{
    return APP_ROOT . '/' . DB_REL;
}

/** Membuka (dan menyimpan) koneksi PDO ke basis data. */
function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $folder = dirname(db_file());
    if (!is_dir($folder)) {
        @mkdir($folder, 0777, true);
    }

    $pdo = new PDO('sqlite:' . db_file(), null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    /* Banyak siswa dapat menekan tombol hampir bersamaan — tunggu, jangan gagal. */
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

/* --- Penolong query singkat ---------------------------------------------- */

/** Menjalankan query dengan prepared statement. */
function q(string $sql, array $args = []): PDOStatement
{
    $st = pdo()->prepare($sql);
    $st->execute($args);

    return $st;
}

/** Satu baris (atau false bila tidak ada). */
function satu(string $sql, array $args = []): array|false
{
    $hasil = q($sql, $args)->fetch();

    return $hasil === false ? false : $hasil;
}

/** Banyak baris. */
function semua(string $sql, array $args = []): array
{
    return q($sql, $args)->fetchAll();
}

/** Satu nilai (kolom pertama baris pertama). */
function nilai(string $sql, array $args = [], mixed $bawaan = 0): mixed
{
    $v = q($sql, $args)->fetchColumn();

    return $v === false ? $bawaan : $v;
}

/** Menjalankan perintah tulis; mengembalikan jumlah baris terpengaruh. */
function jalankan(string $sql, array $args = []): int
{
    return q($sql, $args)->rowCount();
}

/* --- Skema --------------------------------------------------------------- */

/** Memeriksa apakah seluruh tabel & kolom wajib sudah ada. */
function skema_terkini(): bool
{
    $pdo = pdo();
    foreach (SCHEMA_TABEL_WAJIB as $tabel) {
        $ada = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
        $ada->execute([$tabel]);
        if ($ada->fetchColumn() === false) {
            return false;
        }
    }
    foreach (SCHEMA_KOLOM_WAJIB as $tabel => $kolom) {
        foreach ((array) $kolom as $nama) {
            $ada = $pdo->prepare("PRAGMA table_info($tabel)");
            $ada->execute();
            $daftar = array_column($ada->fetchAll(), 'name');
            if (!in_array($nama, $daftar, true)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Membuat skema bila perlu + mengisi data contoh SEKALI.
 * Seluruh isinya satu transaksi supaya tidak ada fsync beruntun pada setiap
 * permintaan (schema yang sudah lengkap langsung keluar lebih dulu).
 */
function ensure_schema(): void
{
    $penanda = APP_ROOT . '/data/.schema_ok';
    if (is_file($penanda) && trim((string) @file_get_contents($penanda)) === SCHEMA_VERSION && skema_terkini()) {
        return;
    }

    $pdo = pdo();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pengaturan (
                kunci TEXT PRIMARY KEY,
                nilai TEXT
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                nama     TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                sandi    TEXT NOT NULL,
                dibuat   TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS paslon (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                nomor        INTEGER NOT NULL,
                ketua        TEXT NOT NULL,
                wakil        TEXT NOT NULL,
                kelas_ketua  TEXT,
                kelas_wakil  TEXT,
                visi         TEXT,
                misi         TEXT,
                program      TEXT,
                foto         TEXT,
                aktif        INTEGER NOT NULL DEFAULT 1,
                dibuat       TEXT NOT NULL,
                diubah       TEXT
            )'
        );

        /* Kolom `nisn` = NOMOR INDUK pemilih: NISN untuk siswa, NIK untuk guru.
           Satu kolom identitas dipakai bersama supaya aturan satu orang satu
           suara tetap dijaga oleh indeks UNIQUE-nya (lihat jenis_pemilih_sah). */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pemilih (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                nisn    TEXT NOT NULL UNIQUE,
                nama    TEXT NOT NULL,
                kelas   TEXT,
                token   TEXT NOT NULL UNIQUE,
                sudah   INTEGER NOT NULL DEFAULT 0,
                waktu   TEXT,
                dibuat  TEXT NOT NULL,
                jenis   TEXT NOT NULL DEFAULT "siswa",
                jabatan TEXT
            )'
        );

        /* Tabel suara SENGAJA tidak menyimpan identitas pemilih: surat suara
           anonim sehingga panitia tidak dapat mengetahui pilihan seseorang. */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS suara (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                paslon_id INTEGER NOT NULL REFERENCES paslon(id),
                dibuat    TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS aktivitas (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                waktu      TEXT NOT NULL,
                jenis      TEXT NOT NULL,
                keterangan TEXT,
                ip         TEXT,
                peramban   TEXT
            )'
        );

        /* Kolom baru pada basis data yang sudah berjalan (v1 -> v2):
           DPT guru (jenis) beserta jabatannya. Hanya MENAMBAH kolom sehingga
           data pemilih yang sudah ada tetap utuh (jenis = 'siswa'). */
        db_add_column($pdo, 'pemilih', 'jenis', 'TEXT NOT NULL DEFAULT "siswa"');
        db_add_column($pdo, 'pemilih', 'jabatan', 'TEXT');
        /* Baris lama yang belum punya jenis diisi 'siswa' (tanpa menebak data). */
        $pdo->exec("UPDATE pemilih SET jenis = 'siswa' WHERE jenis IS NULL OR jenis = ''");

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_paslon_nomor  ON paslon (nomor)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pemilih_jenis ON pemilih (jenis)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pemilih_sudah ON pemilih (sudah)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_suara_paslon  ON suara (paslon_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_aktivitas_waktu ON aktivitas (waktu)');

        /* Nilai pengaturan bawaan: hanya ditulis bila belum ada. */
        foreach (PENGATURAN_BAWAAN as $kunci => $nilai) {
            q('INSERT OR IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)', [$kunci, $nilai]);
        }

        /* Akun panitia pertama (hanya bila belum ada akun sama sekali). */
        if ((int) nilai('SELECT COUNT(*) FROM admin') === 0) {
            q(
                'INSERT INTO admin (nama, username, sandi, dibuat) VALUES (?, ?, ?, ?)',
                ['Panitia OSIS', 'admin', password_hash('admin123', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]
            );
        }

        /* Data contoh: SEKALI saja, dijaga penanda `seed_contoh`. */
        db_isi_contoh($pdo);

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }

    /* Penanda ditulis SESUDAH transaksi sukses. */
    if (!is_dir(dirname($penanda))) {
        @mkdir(dirname($penanda), 0777, true);
    }
    @file_put_contents($penanda, SCHEMA_VERSION);
}

/**
 * Menambahkan kolom bila belum ada (ALTER TABLE ADD COLUMN).
 * SQLite menolak "ADD COLUMN IF NOT EXISTS", jadi keberadaannya diperiksa lebih
 * dulu — dipakai untuk basis data yang sudah berjalan sebelum versi skema ini.
 */
function db_add_column(PDO $pdo, string $tabel, string $kolom, string $tipe): void
{
    $info = $pdo->prepare("PRAGMA table_info($tabel)");
    $info->execute();
    $ada = array_column($info->fetchAll(), 'name');
    if (!in_array($kolom, $ada, true)) {
        $pdo->exec("ALTER TABLE $tabel ADD COLUMN $kolom $tipe");
    }
}

/**
 * Mengisi data contoh (3 paslon + 20 pemilih) hanya bila belum pernah diisi.
 * Data pemilik/panitia TIDAK pernah ditimpa: fungsinya dijaga penanda.
 */
function db_isi_contoh(PDO $pdo): void
{
    if (nilai("SELECT nilai FROM pengaturan WHERE kunci = 'seed_contoh'", [], '') === '1') {
        return;
    }

    if ((int) nilai('SELECT COUNT(*) FROM paslon') === 0) {
        $contoh = [
            [
                'nomor' => 1, 'ketua' => 'Ahmad Fauzi', 'kelas_ketua' => 'XI IPA 1',
                'wakil' => 'Nurul Hidayah', 'kelas_wakil' => 'XI IPS 1',
                'visi' => 'Mewujudkan OSIS yang aktif, kreatif, dan dekat dengan seluruh siswa.',
                'misi' => "Mengadakan kegiatan yang melibatkan seluruh kelas tanpa terkecuali\n"
                    . "Menghidupkan kembali mading, podcast, dan klub minat-bakat sekolah\n"
                    . "Membuka kanal aspirasi siswa yang ditindaklanjuti setiap bulan\n"
                    . "Menjaga kebersihan dan kenyamanan lingkungan sekolah",
                'program' => "Festival Seni & Kreativitas Siswa setiap semester\n"
                    . "Kelas Aspirasi: satu kotak saran digital di setiap jurusan\n"
                    . "Gerakan Sekolah Bersih (Jumat bersih bersama)\n"
                    . "Turnamen olahraga antar kelas",
            ],
            [
                'nomor' => 2, 'ketua' => 'Rizky Pratama', 'kelas_ketua' => 'XI IPA 2',
                'wakil' => 'Salsabila Putri', 'kelas_wakil' => 'XI IPA 1',
                'visi' => 'Sekolah yang berprestasi, inklusif, dan melek teknologi.',
                'misi' => "Meningkatkan pembinaan lomba akademik dan non-akademik\n"
                    . "Menyediakan pelatihan literasi digital dan keamanan bermedia sosial\n"
                    . "Mendukung kegiatan ekstrakurikuler dengan jadwal yang tertib\n"
                    . "Menguatkan budaya saling menghargai antar siswa",
                'program' => "Klinik Belajar gratis sebelum ujian\n"
                    . "Pekan Literasi Digital bersama guru TIK\n"
                    . "Beasiswa kecil untuk siswa berprestasi kurang mampu\n"
                    . "Sekolah Ramah Siswa: kampanye anti perundungan",
            ],
            [
                'nomor' => 3, 'ketua' => 'Bagas Setiawan', 'kelas_ketua' => 'XII IPS 1',
                'wakil' => 'Ayu Lestari', 'kelas_wakil' => 'XII IPA 1',
                'visi' => 'OSIS sebagai jembatan komunikasi siswa, sekolah, dan masyarakat.',
                'misi' => "Menjadi penghubung aspirasi siswa kepada pihak sekolah\n"
                    . "Menggalakkan kegiatan sosial dan keagamaan di sekolah\n"
                    . "Menumbuhkan jiwa kewirausahaan siswa\n"
                    . "Meningkatkan keterlibatan alumni dalam kegiatan sekolah",
                'program' => "Bakti Sosial Ramadan dan berbagi sembako\n"
                    . "Bazar Kewirausahaan Siswa (koperasi siswa)\n"
                    . "Program OSIS Mengajar untuk sekolah dasar sekitar\n"
                    . "Podcast \"Suara Siswa\" setiap dua pekan",
            ],
        ];
        foreach ($contoh as $p) {
            q(
                'INSERT INTO paslon (nomor, ketua, wakil, kelas_ketua, kelas_wakil, visi, misi, program, aktif, dibuat)
                 VALUES (:nomor, :ketua, :wakil, :kk, :kw, :visi, :misi, :program, 1, :dibuat)',
                [
                    ':nomor' => $p['nomor'], ':ketua' => $p['ketua'], ':wakil' => $p['wakil'],
                    ':kk' => $p['kelas_ketua'], ':kw' => $p['kelas_wakil'],
                    ':visi' => $p['visi'], ':misi' => $p['misi'], ':program' => $p['program'],
                    ':dibuat' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    if ((int) nilai('SELECT COUNT(*) FROM pemilih') === 0) {
        $nama = [
            ['Aisyah Ramadhani', 0], ['Budi Santoso', 0], ['Citra Dewi Lestari', 0],
            ['Dedi Kurniawan', 1], ['Eka Nur Fadilah', 1], ['Fajar Hidayat', 1],
            ['Gita Permata Sari', 2], ['Hendra Wijaya', 2], ['Indah Puspita', 2],
            ['Joko Prasetyo', 3], ['Kartika Sari', 3], ['Lukman Hakim', 3],
            ['Maya Anggraini', 4], ['Naufal Rizky', 4], ['Oktavia Nurhaliza', 4],
            ['Putra Adi Nugraha', 5], ['Qori Amelia', 5], ['Rangga Saputra', 5],
            ['Sinta Maharani', 0], ['Taufik Hidayatullah', 1],
        ];
        $no = 1;
        foreach ($nama as [$nm, $idxKelas]) {
            q(
                'INSERT INTO pemilih (nisn, nama, kelas, token, sudah, dibuat, jenis) VALUES (?, ?, ?, ?, 0, ?, ?)',
                [
                    /* NISN contoh: 10 digit berurutan agar mudah dikenali */
                    '00712345' . str_pad((string) $no, 2, '0', STR_PAD_LEFT),
                    $nm,
                    KELAS_CONTOH[$idxKelas],
                    token_baru(),
                    date('Y-m-d H:i:s'),
                    'siswa',
                ]
            );
            $no++;
        }
    }

    q("INSERT OR REPLACE INTO pengaturan (kunci, nilai) VALUES ('seed_contoh', '1')");
}
