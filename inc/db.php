<?php
/**
 * inc/db.php — pembuka basis data SQLite + skema + migrasi + data contoh.
 * ---------------------------------------------------------------------------
 * Sejak v2.0.0 aplikasi dipakai BANYAK madrasah sekaligus.
 * Setiap tabel data (paslon, pemilih, suara, pengaturan) dipisah kolom
 * `lembaga_id`; tabel `lembaga` menjadi daftar induknya.
 *
 * Catatan penting (pelajaran dari aplikasi lain di workspace ini):
 *   1. Penanda skema bisa LEBIH BARU daripada isi basis data, karena itu
 *      `skema_terkini()` memeriksa langsung keberadaan tabel/kolom wajib.
 *   2. Semua tulisan banyak-tabel dibungkus satu `BEGIN IMMEDIATE`.
 *   3. Mode jurnal WAL + busy_timeout supaya beberapa permintaan bersamaan
 *      (banyak siswa memilih serentak) tidak saling mengunci.
 *   4. Migrasi v2 -> v3 TIDAK boleh menghilangkan data asli: seluruh baris lama
 *      dipindahkan ke lembaga pertama, akun panitia lama menjadi Super Admin.
 */
declare(strict_types=1);

/* Berkas ini hanya boleh dijalankan lewat inc/init.php. */
if (!defined('EVOTING_APP')) {
    http_response_code(404);
    exit('Tidak ditemukan.');
}

const SCHEMA_VERSION = '4';

/* Tabel & kolom yang WAJIB ada. Bila penanda skema sudah menyamai versi
   terbaru tetapi ada isi yang belum ada (migrasi terputus), schema dijalankan
   ulang — semuanya idempoten sehingga aman. */
const SCHEMA_TABEL_WAJIB = ['lembaga', 'pengaturan', 'admin', 'paslon', 'pemilih', 'suara', 'aktivitas'];
const SCHEMA_KOLOM_WAJIB = [
    'lembaga'    => ['nama', 'jenis', 'npsn', 'alamat', 'logo', 'aktif', 'dibuat'],
    'pengaturan' => ['lembaga_id', 'kunci', 'nilai'],
    'admin'      => ['lembaga_id', 'peran', 'username', 'sandi', 'aktif'],
    'paslon'     => ['lembaga_id', 'nomor', 'ketua', 'wakil', 'visi', 'misi', 'program', 'foto', 'aktif'],
    'pemilih'    => ['lembaga_id', 'nisn', 'nama', 'kelas', 'token', 'sudah', 'waktu', 'jenis', 'jabatan'],
    'suara'      => ['lembaga_id', 'paslon_id', 'dibuat'],
    'aktivitas'  => ['lembaga_id', 'waktu', 'jenis', 'keterangan', 'ip', 'peramban'],
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

/* --- Pemeriksaan isi basis data ----------------------------------------- */

/** Apakah sebuah tabel sudah ada? */
function tabel_ada(string $tabel): bool
{
    $ada = pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $ada->execute([$tabel]);

    return $ada->fetchColumn() !== false;
}

/** Daftar kolom sebuah tabel. */
function kolom_daftar(string $tabel): array
{
    $info = pdo()->prepare("PRAGMA table_info($tabel)");
    $info->execute();

    return array_column($info->fetchAll(), 'name');
}

/** Apakah sebuah indeks sudah ada? */
function indeks_ada(string $indeks): bool
{
    $ada = pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?");
    $ada->execute([$indeks]);

    return $ada->fetchColumn() !== false;
}

/** Memeriksa apakah seluruh tabel & kolom wajib sudah ada. */
function skema_terkini(): bool
{
    foreach (SCHEMA_TABEL_WAJIB as $tabel) {
        if (!tabel_ada($tabel)) {
            return false;
        }
    }
    foreach (SCHEMA_KOLOM_WAJIB as $tabel => $kolom) {
        $daftar = kolom_daftar($tabel);
        foreach ((array) $kolom as $nama) {
            if (!in_array($nama, $daftar, true)) {
                return false;
            }
        }
    }

    return true;
}

/* --- Skema --------------------------------------------------------------- */

/**
 * Membuat skema bila perlu + memindahkan data lama ke lembaga pertama.
 * Seluruh isinya satu transaksi supaya tidak ada fsync beruntun pada setiap
 * permintaan (skema yang sudah lengkap langsung keluar lebih dulu).
 */
function ensure_schema(): void
{
    $penanda = APP_ROOT . '/data/.schema_ok';
    if (is_file($penanda) && trim((string) @file_get_contents($penanda)) === SCHEMA_VERSION && skema_terkini()) {
        return;
    }

    $pdo = pdo();

    /* Basis data benar-benar baru? (dipakai untuk mengisi data contoh sekali) */
    $dbBaru = !tabel_ada('paslon') && !tabel_ada('pemilih') && !tabel_ada('admin');

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        /* ---- 1. pengaturan: kunci tunggal -> (lembaga_id, kunci) ---------- */
        migrasi_pengaturan($pdo);

        /* ---- 2. lembaga (induk seluruh data) ----------------------------- */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS lembaga (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                nama   TEXT NOT NULL,
                jenis  TEXT NOT NULL DEFAULT "madrasah",
                npsn   TEXT,
                alamat TEXT,
                logo   TEXT,
                aktif  INTEGER NOT NULL DEFAULT 1,
                dibuat TEXT NOT NULL,
                diubah TEXT
            )'
        );
        db_add_column($pdo, 'lembaga', 'logo', 'TEXT');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_lembaga_npsn ON lembaga (npsn) WHERE npsn IS NOT NULL AND npsn <> ""');

        /* ---- 3. admin: + lembaga_id, peran, aktif ------------------------ */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lembaga_id INTEGER,
                peran      TEXT NOT NULL DEFAULT "panitia",
                nama       TEXT NOT NULL,
                username   TEXT NOT NULL UNIQUE,
                sandi      TEXT NOT NULL,
                aktif      INTEGER NOT NULL DEFAULT 1,
                dibuat     TEXT NOT NULL
            )'
        );
        db_add_column($pdo, 'admin', 'lembaga_id', 'INTEGER');
        db_add_column($pdo, 'admin', 'peran', 'TEXT NOT NULL DEFAULT "panitia"');
        db_add_column($pdo, 'admin', 'aktif', 'INTEGER NOT NULL DEFAULT 1');

        /* ---- 4. paslon --------------------------------------------------- */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS paslon (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                lembaga_id   INTEGER NOT NULL,
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
        db_add_column($pdo, 'paslon', 'lembaga_id', 'INTEGER NOT NULL DEFAULT 0');

        /* ---- 5. pemilih -------------------------------------------------- */
        /* Kolom `nisn` = NOMOR INDUK pemilih: NISN untuk siswa, NIK untuk guru.
           Keunikan nomor induk berlaku PER LEMBAGA (dua madrasah boleh punya
           nomor induk sama) sedangkan token tetap unik GLOBAL supaya token yang
           salah ketik tidak pernah cocok dengan pemilih lembaga lain.          */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS pemilih (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lembaga_id INTEGER NOT NULL,
                nisn       TEXT NOT NULL,
                nama       TEXT NOT NULL,
                kelas      TEXT,
                token      TEXT NOT NULL UNIQUE,
                sudah      INTEGER NOT NULL DEFAULT 0,
                waktu      TEXT,
                dibuat     TEXT NOT NULL,
                jenis      TEXT NOT NULL DEFAULT "siswa",
                jabatan    TEXT
            )'
        );
        db_add_column($pdo, 'pemilih', 'lembaga_id', 'INTEGER NOT NULL DEFAULT 0');
        /* Kolom baru pada basis data lama (v1 -> v2): DPT guru. */
        db_add_column($pdo, 'pemilih', 'jenis', 'TEXT NOT NULL DEFAULT "siswa"');
        db_add_column($pdo, 'pemilih', 'jabatan', 'TEXT');
        $pdo->exec("UPDATE pemilih SET jenis = 'siswa' WHERE jenis IS NULL OR jenis = ''");

        /* ---- 6. suara (anonim: tanpa identitas pemilih) ------------------- */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS suara (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lembaga_id INTEGER NOT NULL,
                paslon_id  INTEGER NOT NULL REFERENCES paslon(id),
                dibuat     TEXT NOT NULL
            )'
        );
        db_add_column($pdo, 'suara', 'lembaga_id', 'INTEGER NOT NULL DEFAULT 0');

        /* ---- 7. aktivitas ----------------------------------------------- */
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS aktivitas (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lembaga_id INTEGER,
                waktu      TEXT NOT NULL,
                jenis      TEXT NOT NULL,
                keterangan TEXT,
                ip         TEXT,
                peramban   TEXT
            )'
        );
        db_add_column($pdo, 'aktivitas', 'lembaga_id', 'INTEGER');

        /* ---- 8. lembaga pertama (pemilik data lama) ---------------------- */
        $lembagaId = lembaga_pastikan_ada($pdo);

        /* Data lama belum punya lembaga -> pindahkan ke lembaga pertama. */
        jalankan('UPDATE paslon    SET lembaga_id = ? WHERE lembaga_id = 0 OR lembaga_id IS NULL', [$lembagaId]);
        jalankan('UPDATE pemilih   SET lembaga_id = ? WHERE lembaga_id = 0 OR lembaga_id IS NULL', [$lembagaId]);
        jalankan('UPDATE suara     SET lembaga_id = ? WHERE lembaga_id = 0 OR lembaga_id IS NULL', [$lembagaId]);
        jalankan('UPDATE aktivitas SET lembaga_id = ? WHERE lembaga_id IS NULL', [$lembagaId]);

        /* ---- 9. keunikan nomor induk per lembaga ------------------------- */
        migrasi_pemilih_unik($pdo);

        /* ---- 10. indeks -------------------------------------------------- */
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_paslon_lembaga  ON paslon (lembaga_id, nomor)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pemilih_jenis   ON pemilih (lembaga_id, jenis)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pemilih_sudah   ON pemilih (lembaga_id, sudah)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_suara_paslon    ON suara (paslon_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_suara_lembaga   ON suara (lembaga_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_admin_lembaga   ON admin (lembaga_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_aktivitas_waktu ON aktivitas (waktu)');

        /* ---- 11. akun Super Admin + pengaturan bawaan tiap lembaga ------- */
        super_admin_pastikan_ada($pdo);
        jalankan("UPDATE admin SET lembaga_id = ? WHERE peran <> 'super' AND (lembaga_id IS NULL OR lembaga_id = 0)", [$lembagaId]);
        foreach (semua('SELECT id, jenis FROM lembaga') as $l) {
            lembaga_isi_pengaturan($pdo, (int) $l['id'], (string) $l['jenis']);
        }

        /* ---- 12. data contoh: HANYA untuk basis data baru ---------------- */
        if ($dbBaru) {
            db_isi_contoh($pdo, $lembagaId);
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $abaikan) {
        }
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
 * Mengubah tabel `pengaturan` lama (kunci tunggal) menjadi per lembaga.
 * Nilai lama dipertahankan: kunci milik lembaga dipindahkan ke lembaga 1,
 * kunci tingkat aplikasi (mis. `seed_contoh`) ke lembaga_id = 0.
 */
function migrasi_pengaturan(PDO $pdo): void
{
    if (tabel_ada('pengaturan') && in_array('lembaga_id', kolom_daftar('pengaturan'), true)) {
        return;
    }

    $lama = [];
    if (tabel_ada('pengaturan')) {
        $st = $pdo->query('SELECT kunci, nilai FROM pengaturan');
        $lama = $st === false ? [] : $st->fetchAll();
        $pdo->exec('DROP TABLE pengaturan');
    }

    $pdo->exec(
        'CREATE TABLE pengaturan (
            lembaga_id INTEGER NOT NULL DEFAULT 0,
            kunci      TEXT NOT NULL,
            nilai      TEXT,
            PRIMARY KEY (lembaga_id, kunci)
        )'
    );

    $global = array_keys(PENGATURAN_GLOBAL_BAWAAN);
    $tulis = $pdo->prepare('INSERT OR REPLACE INTO pengaturan (lembaga_id, kunci, nilai) VALUES (?, ?, ?)');
    foreach ($lama as $baris) {
        $kunci = (string) ($baris['kunci'] ?? '');
        if ($kunci === '') {
            continue;
        }
        $scope = in_array($kunci, $global, true) ? 0 : 1;
        $tulis->execute([$scope, $kunci, $baris['nilai']]);
    }
}

/**
 * Membentuk ulang tabel `pemilih` supaya keunikan nomor induk berlaku per
 * lembaga (dulu UNIQUE global). Baris & id dipertahankan seluruhnya.
 */
function migrasi_pemilih_unik(PDO $pdo): void
{
    if (indeks_ada('idx_pemilih_lembaga_nisn')) {
        return;
    }

    $pdo->exec('ALTER TABLE pemilih RENAME TO pemilih_lama');
    $pdo->exec(
        'CREATE TABLE pemilih (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            lembaga_id INTEGER NOT NULL,
            nisn       TEXT NOT NULL,
            nama       TEXT NOT NULL,
            kelas      TEXT,
            token      TEXT NOT NULL UNIQUE,
            sudah      INTEGER NOT NULL DEFAULT 0,
            waktu      TEXT,
            dibuat     TEXT NOT NULL,
            jenis      TEXT NOT NULL DEFAULT "siswa",
            jabatan    TEXT
        )'
    );
    $pdo->exec(
        'INSERT INTO pemilih (id, lembaga_id, nisn, nama, kelas, token, sudah, waktu, dibuat, jenis, jabatan)
         SELECT id, lembaga_id, nisn, nama, kelas, token, sudah, waktu, dibuat, jenis, jabatan FROM pemilih_lama'
    );
    $pdo->exec('DROP TABLE pemilih_lama');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_pemilih_lembaga_nisn ON pemilih (lembaga_id, nisn)');
}

/**
 * Memastikan ada satu lembaga (dipakai sebagai pemilik data lama).
 * Nama & jenis diambil dari pengaturan lama bila ada, supaya lembaga pemilik
 * TIDAK berganti nama setelah pembaruan.
 */
function lembaga_pastikan_ada(PDO $pdo): int
{
    $id = (int) nilai('SELECT id FROM lembaga ORDER BY id LIMIT 1');
    if ($id > 0) {
        return $id;
    }

    $nama  = trim((string) nilai("SELECT nilai FROM pengaturan WHERE lembaga_id = 1 AND kunci = 'nama_lembaga'", [], ''));
    $jenis = trim((string) nilai("SELECT nilai FROM pengaturan WHERE lembaga_id = 1 AND kunci = 'jenis_lembaga'", [], ''));
    if ($nama === '') {
        $nama = 'Lembaga Pertama';
    }
    if (!isset(JENIS_LEMBAGA_NAMA[$jenis])) {
        $jenis = JENIS_LEMBAGA_BAWAAN;
    }

    $pdo->prepare('INSERT INTO lembaga (nama, jenis, npsn, alamat, aktif, dibuat) VALUES (?, ?, NULL, NULL, 1, ?)')
        ->execute([$nama, $jenis, date('Y-m-d H:i:s')]);
    $id = (int) $pdo->lastInsertId();

    /* Nama & jenis kini SATU sumbernya di tabel lembaga — kunci lama dibuang
       supaya tidak ada dua nilai yang bisa berbeda diam-diam. */
    $pdo->exec("DELETE FROM pengaturan WHERE kunci IN ('nama_lembaga', 'jenis_lembaga')");

    return $id;
}

/** Mengisi pengaturan bawaan sebuah lembaga (tidak menimpa yang sudah ada). */
function lembaga_isi_pengaturan(PDO $pdo, int $lembagaId, string $jenis): void
{
    $istilah = ISTILAH;
    $bawaan  = [
        'nama_pemilihan'    => 'Pemilihan Ketua & Wakil Ketua ' . $istilah,
        'periode'           => tahun_pelajaran(),
        'tanggal_pemilihan' => '',
        'tampilkan_hasil'   => '1',
    ];
    $tulis = $pdo->prepare('INSERT OR IGNORE INTO pengaturan (lembaga_id, kunci, nilai) VALUES (?, ?, ?)');
    foreach ($bawaan as $kunci => $nilai) {
        $tulis->execute([$lembagaId, $kunci, $nilai]);
    }
}

/**
 * Memastikan selalu ada akun Super Admin.
 * Pada basis data LAMA yang sudah punya akun panitia, akun pertama diangkat
 * menjadi Super Admin (nama pengguna & sandinya tidak berubah sehingga pemilik
 * tetap dapat masuk seperti biasa). Nama pengguna itu kemudian dipakai untuk
 * mengelola seluruh lembaga.
 */
function super_admin_pastikan_ada(PDO $pdo): void
{
    if ((int) nilai("SELECT COUNT(*) FROM admin WHERE peran = 'super'") > 0) {
        return;
    }
    $lama = satu('SELECT id FROM admin ORDER BY id LIMIT 1');
    if ($lama !== false) {
        $pdo->prepare("UPDATE admin SET peran = 'super', lembaga_id = NULL, aktif = 1 WHERE id = ?")
            ->execute([(int) $lama['id']]);

        return;
    }
    $pdo->prepare('INSERT INTO admin (lembaga_id, peran, nama, username, sandi, aktif, dibuat)
                   VALUES (NULL, "super", ?, ?, ?, 1, ?)')
        ->execute(['Super Admin', 'admin', password_hash('admin123', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
}

/**
 * Mengisi data contoh (3 paslon + 20 pemilih) untuk SATU lembaga.
 * Hanya dipanggil untuk basis data baru atau lewat menu "Muat Data Contoh",
 * dan dijaga penanda `seed_contoh` supaya tidak pernah menimpa data pemilik.
 */
function db_isi_contoh(PDO $pdo, int $lembagaId): void
{
    /* Penjaga sebenarnya ada pada dua pemeriksaan COUNT di bawah: data contoh
       hanya dibuat untuk lembaga yang paslon/pemilihnya memang masih kosong.
       Jadi memanggil fungsi ini untuk lembaga kedua TIDAK pernah menimpa data
       lembaga pertama (dan sebaliknya). */
    if ((int) nilai('SELECT COUNT(*) FROM paslon WHERE lembaga_id = ?', [$lembagaId]) === 0) {
        $contoh = [
            [
                'nomor' => 1, 'ketua' => 'Ahmad Fauzi', 'kelas_ketua' => 'XI IPA 1',
                'wakil' => 'Nurul Hidayah', 'kelas_wakil' => 'XI IPS 1',
                'visi' => 'Mewujudkan OSIM yang aktif, kreatif, dan dekat dengan seluruh siswa.',
                'misi' => "Mengadakan kegiatan yang melibatkan seluruh kelas tanpa terkecuali\n"
                    . "Menghidupkan kembali mading, podcast, dan klub minat-bakat madrasah\n"
                    . "Membuka kanal aspirasi siswa yang ditindaklanjuti setiap bulan\n"
                    . "Menjaga kebersihan dan kenyamanan lingkungan madrasah",
                'program' => "Festival Seni & Kreativitas Siswa setiap semester\n"
                    . "Kelas Aspirasi: satu kotak saran digital di setiap jurusan\n"
                    . "Gerakan Madrasah Bersih (Jumat bersih bersama)\n"
                    . "Turnamen olahraga antar kelas",
            ],
            [
                'nomor' => 2, 'ketua' => 'Rizky Pratama', 'kelas_ketua' => 'XI IPA 2',
                'wakil' => 'Salsabila Putri', 'kelas_wakil' => 'XI IPA 1',
                'visi' => 'Madrasah yang berprestasi, inklusif, dan melek teknologi.',
                'misi' => "Meningkatkan pembinaan lomba akademik dan non-akademik\n"
                    . "Menyediakan pelatihan literasi digital dan keamanan bermedia sosial\n"
                    . "Mendukung kegiatan ekstrakurikuler dengan jadwal yang tertib\n"
                    . "Menguatkan budaya saling menghargai antar siswa",
                'program' => "Klinik Belajar gratis sebelum ujian\n"
                    . "Pekan Literasi Digital bersama guru TIK\n"
                    . "Beasiswa kecil untuk siswa berprestasi kurang mampu\n"
                    . "Madrasah Ramah Siswa: kampanye anti perundungan",
            ],
            [
                'nomor' => 3, 'ketua' => 'Bagas Setiawan', 'kelas_ketua' => 'XII IPS 1',
                'wakil' => 'Ayu Lestari', 'kelas_wakil' => 'XII IPA 1',
                'visi' => 'OSIM sebagai jembatan komunikasi siswa, madrasah, dan masyarakat.',
                'misi' => "Menjadi penghubung aspirasi siswa kepada pihak madrasah\n"
                    . "Menggalakkan kegiatan sosial dan keagamaan di madrasah\n"
                    . "Menumbuhkan jiwa kewirausahaan siswa\n"
                    . "Meningkatkan keterlibatan alumni dalam kegiatan madrasah",
                'program' => "Bakti Sosial Ramadan dan berbagi sembako\n"
                    . "Bazar Kewirausahaan Siswa (koperasi siswa)\n"
                    . "Program OSIM Mengajar untuk madrasah ibtidaiyah sekitar\n"
                    . "Podcast \"Suara Siswa\" setiap dua pekan",
            ],
        ];
        $tambah = $pdo->prepare(
            'INSERT INTO paslon (lembaga_id, nomor, ketua, wakil, kelas_ketua, kelas_wakil, visi, misi, program, aktif, dibuat)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        foreach ($contoh as $p) {
            $tambah->execute([
                $lembagaId, $p['nomor'], $p['ketua'], $p['wakil'], $p['kelas_ketua'], $p['kelas_wakil'],
                $p['visi'], $p['misi'], $p['program'], date('Y-m-d H:i:s'),
            ]);
        }
    }

    if ((int) nilai('SELECT COUNT(*) FROM pemilih WHERE lembaga_id = ?', [$lembagaId]) === 0) {
        $nama = [
            ['Aisyah Ramadhani', 0], ['Budi Santoso', 0], ['Citra Dewi Lestari', 0],
            ['Dedi Kurniawan', 1], ['Eka Nur Fadilah', 1], ['Fajar Hidayat', 1],
            ['Gita Permata Sari', 2], ['Hendra Wijaya', 2], ['Indah Puspita', 2],
            ['Joko Prasetyo', 3], ['Kartika Sari', 3], ['Lukman Hakim', 3],
            ['Maya Anggraini', 4], ['Naufal Rizky', 4], ['Oktavia Nurhaliza', 4],
            ['Putra Adi Nugraha', 5], ['Qori Amelia', 5], ['Rangga Saputra', 5],
            ['Sinta Maharani', 0], ['Taufik Hidayatullah', 1],
        ];
        $tambah = $pdo->prepare(
            'INSERT INTO pemilih (lembaga_id, nisn, nama, kelas, token, sudah, dibuat, jenis)
             VALUES (?, ?, ?, ?, ?, 0, ?, "siswa")'
        );
        $no = 1;
        foreach ($nama as [$nm, $idxKelas]) {
            $tambah->execute([
                $lembagaId,
                /* NISN contoh: 10 digit berurutan agar mudah dikenali */
                '00712345' . str_pad((string) $no, 2, '0', STR_PAD_LEFT),
                $nm,
                KELAS_CONTOH[$idxKelas],
                token_baru(),
                date('Y-m-d H:i:s'),
            ]);
            $no++;
        }
    }

    $pdo->prepare("INSERT OR REPLACE INTO pengaturan (lembaga_id, kunci, nilai) VALUES (0, 'seed_contoh', '1')")
        ->execute();
}
