/* ==========================================================================
   E-Voting OSIS — assets/app.js
   --------------------------------------------------------------------------
   Skrip modular: setiap fitur punya fungsi `init...` sendiri dan hanya bekerja
   bila elemennya memang ada di halaman, sehingga satu berkas aman dipakai di
   semua halaman (pemilih, panitia, maupun papan hasil).

   Daftar modul:
     1. Toast           — notifikasi mengambang (flash pesan dari server)
     2. Dialog          — modal umum (visi & misi, konfirmasi, dsb.)
     3. Pilih paslon    — konfirmasi sebelum suara dikirim
     4. Konfirmasi aksi — dialog untuk form yang menghapus/mengubah data
     5. Tab masuk       — panel Pemilih / Panitia pada halaman login
     6. Live search     — pencarian cepat pada tabel DPT
     7. Salin           — salin token (satu atau seluruhnya)
     8. Token input     — format otomatis XXXX-XXXX
     9. Pratinjau foto  — tampilkan foto terpilih sebelum diunggah
    10. Hasil live      — grafik + penyegaran otomatis (real-count)
    11. Hitung mundur   — keluar otomatis setelah memilih
    12. Nav mobile      — menu samping panel panitia
   ========================================================================== */
window.EV = (function () {
  'use strict';

  /* ------------------------------- Penolong ------------------------------- */
  const $  = (sel, akar = document) => akar.querySelector(sel);
  const $$ = (sel, akar = document) => Array.from(akar.querySelectorAll(sel));

  /** Format angka gaya Indonesia: 1.234 */
  const angka = (n) => new Intl.NumberFormat('id-ID').format(Number(n) || 0);

  /** Format persen satu desimal: 45,5% */
  const persen = (n) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1, maximumFractionDigits: 1,
  }).format(Number(n) || 0) + '%';

  /** Menandai angka yang baru berubah supaya terlihat "berdenyut". */
  function sorot(el) {
    if (!el) { return; }
    el.classList.remove('animate-pop');
    void el.offsetWidth; /* memaksa peramban menghitung ulang animasi */
    el.classList.add('animate-pop');
    window.setTimeout(() => el.classList.remove('animate-pop'), 700);
  }

  /* ======================= 1. TOAST ====================================== */
  /** Menampilkan notifikasi sementara di pojok kanan atas. */
  function toast(pesan, jenis = 'ok', waktu = 4200) {
    let wadah = $('#toastWadah');
    if (!wadah) {
      wadah = document.createElement('div');
      wadah.id = 'toastWadah';
      wadah.className = 'fixed right-3 top-3 z-[95] flex w-[min(92vw,23rem)] flex-col gap-2';
      wadah.setAttribute('aria-live', 'polite');
      document.body.appendChild(wadah);
    }
    const warna = {
      ok: 'notis-ok', galat: 'notis-galat', info: 'notis-info', kuning: 'notis-kuning',
    }[jenis] || 'notis-info';

    const kartu = document.createElement('div');
    kartu.className = warna + ' shadow-soft animate-geser';
    kartu.setAttribute('role', 'status');
    kartu.innerHTML = '<p class="flex-1"></p>';
    kartu.querySelector('p').textContent = pesan; /* textContent = aman dari HTML */
    wadah.appendChild(kartu);
    window.setTimeout(() => kartu.remove(), waktu);

    return kartu;
  }

  function initToast() {
    $$('[data-toast]').forEach((t) => {
      const tutup = () => {
        t.style.transition = 'opacity .25s, transform .25s';
        t.style.opacity = '0';
        t.style.transform = 'translateX(20px)';
        window.setTimeout(() => t.remove(), 250);
      };
      const tombol = $('[data-toast-tutup]', t);
      if (tombol) { tombol.addEventListener('click', tutup); }
      /* Pesan bawaan server hilang sendiri supaya layar tetap bersih. */
      window.setTimeout(tutup, 7000);
    });
  }

  /* ======================= 2. DIALOG (MODAL) ============================ */
  function initDialog() {
    /* Tombol pembuka: <button data-modal-buka="id-dialog"> */
    $$('[data-modal-buka]').forEach((tombol) => {
      tombol.addEventListener('click', () => {
        const dlg = document.getElementById(tombol.getAttribute('data-modal-buka'));
        if (!dlg) { return; }
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
      });
    });

    /* Tombol penutup di dalam dialog */
    $$('dialog').forEach((dlg) => {
      $$('[data-modal-tutup]', dlg).forEach((tombol) => {
        tombol.addEventListener('click', () => dlg.close());
      });
      /* Klik latar gelap (di luar kotak dialog) menutup dialog */
      dlg.addEventListener('click', (ev) => {
        const kotak = dlg.firstElementChild;
        if (!kotak) { return; }
        const r = kotak.getBoundingClientRect();
        const diLuar = ev.clientX < r.left || ev.clientX > r.right
          || ev.clientY < r.top || ev.clientY > r.bottom;
        if (diLuar) { dlg.close(); }
      });
    });
  }

  /* ======================= 3. KONFIRMASI PILIHAN ========================= */
  /* Tombol "PILIH PASLON" tetap berupa tombol kirim formulir biasa, jadi bila
     JavaScript gagal dimuat suara tetap dapat dikirim (hanya tanpa konfirmasi).
     Saat JavaScript aktif, pengiriman ditahan lebih dulu oleh modal konfirmasi. */
  let formMenunggu = null;

  function tanyaPilih(form) {
    const dlg = $('#modalKonfirmasi');
    if (!dlg || typeof dlg.showModal !== 'function') { form.submit(); return; }
    formMenunggu = form;
    const nomor = form.getAttribute('data-nomor') || '';
    const nama  = form.getAttribute('data-nama') || '';
    const label = $('#konfPaslon');
    if (label) { label.textContent = 'Paslon ' + nomor + ' — ' + nama; }
    dlg.showModal();
  }

  function initPilih() {
    $$('[data-pilih-form]').forEach((form) => {
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        tanyaPilih(form);
      });
    });

    /* Tombol pilih di dalam modal visi & misi */
    $$('[data-pilih-dari-modal]').forEach((tombol) => {
      tombol.addEventListener('click', () => {
        const id = tombol.getAttribute('data-pilih-dari-modal');
        const form = $( '[data-pilih-form] input[name="paslon_id"][value="' + id + '"]');
        $$('dialog[open]').forEach((d) => d.close());
        if (form) { tanyaPilih(form.closest('form')); }
      });
    });

    /* Tombol di modal konfirmasi */
    const dlg = $('#modalKonfirmasi');
    if (!dlg) { return; }

    $$('[data-konf-batal]', dlg).forEach((t) => t.addEventListener('click', () => dlg.close()));
    $$('[data-konf-lanjut]', dlg).forEach((t) => t.addEventListener('click', () => {
      const tombol = t;
      tombol.disabled = true;
      tombol.innerHTML = 'Menyimpan suara...';
      if (formMenunggu) { formMenunggu.submit(); } /* submit() asli: tanpa memicu ulang modal */
    }));
  }

  /* ======================= 4. KONFIRMASI AKSI UMUM ======================= */
  /* Dipakai form yang menghapus/mengubah data: <form data-konfirmasi
     data-pesan="...">. Dialognya dibuat otomatis oleh skrip ini sehingga
     halaman tinggal menandai formulirnya. */
  let aksiMenunggu = null;

  function dialogAksi(pesan) {
    let dlg = $('#dialogAksi');
    if (!dlg) {
      dlg = document.createElement('dialog');
      dlg.id = 'dialogAksi';
      dlg.className = 'modal !w-[min(94vw,26rem)]';
      dlg.innerHTML = ''
        + '<div class="p-6 text-center">'
        + '  <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-rose-100 text-rose-600">'
        + '    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
        + '      <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>'
        + '    </svg>'
        + '  </span>'
        + '  <h3 class="mt-4 text-lg font-black text-slate-900">Konfirmasi</h3>'
        + '  <p class="mt-2 text-sm text-slate-600" data-pesan-aksi></p>'
        + '  <div class="mt-5 flex flex-col gap-3 sm:flex-row">'
        + '    <button type="button" class="tombol-putih flex-1" data-aksi-batal>Batal</button>'
        + '    <button type="button" class="tombol-bahaya flex-1" data-aksi-lanjut>Ya, Lanjutkan</button>'
        + '  </div>'
        + '</div>';
      document.body.appendChild(dlg);
      $('[data-aksi-batal]', dlg).addEventListener('click', () => dlg.close());
      $('[data-aksi-lanjut]', dlg).addEventListener('click', () => {
        dlg.close();
        if (aksiMenunggu) { aksiMenunggu.submit(); }
      });
    }
    $('[data-pesan-aksi]', dlg).textContent = pesan;
    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
  }

  function initKonfirmasi() {
    $$('form[data-konfirmasi]').forEach((form) => {
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        aksiMenunggu = form;
        dialogAksi(form.getAttribute('data-pesan') || 'Lanjutkan tindakan ini?');
      });
    });
  }

  /* ======================= 5. TAB HALAMAN MASUK ========================= */
  function initTab() {
    const tombol = $$('[data-tab]');
    if (!tombol.length) { return; }

    const panels = {
      pemilih: $('#panelPemilih'),
      panitia: $('#panelPanitia'),
    };

    function aktifkan(nama) {
      tombol.forEach((t) => {
        const ini = t.getAttribute('data-tab') === nama;
        t.classList.toggle('tab-aktif', ini);
        t.classList.toggle('tab-tidak', !ini);
        t.setAttribute('aria-selected', ini ? 'true' : 'false');
      });
      Object.keys(panels).forEach((k) => {
        if (panels[k]) { panels[k].hidden = (k !== nama); }
      });
    }

    const wadahTab = $('[role="tablist"][data-tab-awal]');
    const awal = (wadahTab && wadahTab.getAttribute('data-tab-awal')) || 'pemilih';
    aktifkan(awal);
    tombol.forEach((t) => t.addEventListener('click', () => aktifkan(t.getAttribute('data-tab'))));
  }

  /* ======================= 6. LIVE SEARCH =============================== */
  function initCari() {
    const isian = $('[data-cari]');
    const isi   = $('[data-tabel-isi]');
    if (!isian || !isi) { return; }

    const baris   = $$('[data-baris-pemilih]', isi);
    const saring  = $('[data-saring]');
    const info    = $('[data-cari-info]');
    const jumlah  = $('[data-cari-jumlah]');
    const kosong  = $('[data-cari-kosong]');

    function terapkan() {
      const kata   = isian.value.trim().toLowerCase();
      const status = saring ? saring.value : 'semua';
      let tampil = 0;

      baris.forEach((tr) => {
        const cocokKata   = !kata || (tr.getAttribute('data-cari-teks') || '').indexOf(kata) !== -1;
        const cocokStatus = status === 'semua' || tr.getAttribute('data-status') === status;
        const tampak      = cocokKata && cocokStatus;
        tr.hidden = !tampak;
        if (tampak) {
          tampil += 1;
          const no = $('[data-nomor]', tr);
          if (no) { no.textContent = String(tampil); }
        }
      });

      if (info)   { info.textContent = angka(tampil) + ' baris'; }
      if (jumlah) { jumlah.textContent = angka(tampil) + ' pemilih ditampilkan'; }
      if (kosong) { kosong.hidden = tampil !== 0; }
    }

    isian.addEventListener('input', terapkan);
    if (saring) { saring.addEventListener('change', terapkan); }
    terapkan();
  }

  /* ======================= 7. SALIN KE PAPAN KLIP ======================= */
  async function salinTeks(teks) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(teks);
        return true;
      }
    } catch (e) { /* jatuh ke cara lama di bawah */ }
    try {
      const ta = document.createElement('textarea');
      ta.value = teks;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand('copy');
      ta.remove();
      return ok;
    } catch (e) {
      return false;
    }
  }

  /** Judul kolom untuk "Salin Semua Token" (dibaca dari penanda di halaman). */
  function judulKolomToken() {
    const el = $('[data-kolom-token]');
    return el ? el.getAttribute('data-kolom-token') : 'NISN\tNama\tKelas\tToken';
  }

  function initSalin() {
    /* Salin satu nilai: <button data-salin="XXXX-XXXX"> */
    $$('[data-salin]').forEach((tombol) => {
      tombol.addEventListener('click', async () => {
        const teks = tombol.getAttribute('data-salin');
        const ok = await salinTeks(teks);
        toast(ok ? 'Token ' + teks + ' disalin.' : 'Gagal menyalin, salin manual: ' + teks, ok ? 'ok' : 'galat');
      });
    });

    /* Salin seluruh daftar token DPT */
    const semua = $('[data-salin-token]');
    const data  = $('#dataSalinToken');
    if (semua && data) {
      semua.addEventListener('click', async () => {
        let daftar = [];
        try { daftar = JSON.parse(data.textContent) || []; } catch (e) { daftar = []; }
        if (!daftar.length) { toast('Belum ada pemilih yang bisa disalin.', 'kuning'); return; }
        const teks = judulKolomToken() + '\n' + daftar.join('\n');
        const ok = await salinTeks(teks);
        toast(ok
          ? angka(daftar.length) + ' baris token disalin. Tempel di aplikasi pengolah kata atau pesan.'
          : 'Gagal menyalin seluruh token.', ok ? 'ok' : 'galat');
      });
    }
  }

  /* ======================= 8. ISIAN TOKEN ============================== */
  /* Menulis otomatis dalam bentuk XXXX-XXXX dan hanya menerima huruf/angka. */
  function initToken() {
    $$('[data-token]').forEach((isian) => {
      isian.addEventListener('input', () => {
        const bersih = isian.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
        isian.value = bersih.length > 4 ? bersih.slice(0, 4) + '-' + bersih.slice(4) : bersih;
      });
    });
  }

  /* ======================= 9. PRATINJAU FOTO =========================== */
  function initFoto() {
    const isian = $('[data-foto]');
    if (!isian) { return; }
    isian.addEventListener('change', () => {
      const berkas = isian.files && isian.files[0];
      if (!berkas) { return; }
      const ukuranMb = berkas.size / (1024 * 1024);
      if (ukuranMb > 15) {
        toast('Ukuran foto ' + ukuranMb.toFixed(1) + ' MB melebihi batas 15 MB.', 'galat');
        isian.value = '';
        return;
      }
      const gambar = $('[data-pratinjau-foto]');
      const kosong = $('[data-pratinjau-kosong]');
      if (!gambar) { return; }
      gambar.src = URL.createObjectURL(berkas);
      gambar.classList.remove('hidden');
      if (kosong) { kosong.classList.add('hidden'); }
    });
  }

  /* ======================= 10. HASIL LIVE (REAL-COUNT) ================= */
  function initHasil() {
    const wadah = $('[data-hasil]');
    const data  = $('#dataHasil');
    let hasil   = null;
    try { hasil = data ? JSON.parse(data.textContent) : null; } catch (e) { hasil = null; }

    const donat  = $('#chDonat');
    const batang = $('#chBatang');
    const adaChart = typeof window.Chart !== 'undefined';

    if (adaChart) {
      window.Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
      window.Chart.defaults.color = '#475569';
    }

    let chDonat = null;
    let chBatang = null;

    if (wadah && hasil && adaChart && (donat || batang)) {
      const label   = hasil.paslon.map((p) => 'Paslon ' + p.nomor);
      const warna   = hasil.paslon.map((p) => p.warna);
      const suara   = hasil.paslon.map((p) => p.suara);
      const persen_ = hasil.paslon.map((p) => p.persen);

      if (donat) {
        chDonat = new window.Chart(donat, {
          type: 'doughnut',
          data: {
            labels: label,
            datasets: [{ data: persen_, backgroundColor: warna, borderWidth: 0, hoverOffset: 8 }],
          },
          options: {
            responsive: true,
            /* Tinggi wadah sudah dikunci di halaman (h-[13rem]/h-[14rem]); tanpa
               maintainAspectRatio:false Chart.js memakai rasio bawaan 1:2
               sehingga diagram jadi sangat panjang ke bawah. */
            maintainAspectRatio: false,
            cutout: '62%',
            animation: { duration: 500 },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (c) => ' ' + c.label + ': ' + persen(c.parsed)
                    + ' (' + angka(suara[c.dataIndex]) + ' suara)',
                },
              },
            },
          },
        });
      }

      if (batang) {
        chBatang = new window.Chart(batang, {
          type: 'bar',
          data: {
            labels: label,
            datasets: [{
              label: 'Jumlah suara',
              data: suara,
              backgroundColor: warna,
              borderRadius: 10,
              maxBarThickness: 34,
            }],
          },
          options: {
            indexAxis: 'y',
            responsive: true,
            /* Tinggi dibatasi oleh wadah (lihat $tinggiBatang pada hasil.php). */
            maintainAspectRatio: false,
            animation: { duration: 500 },
            scales: {
              x: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { precision: 0 } },
              y: { grid: { display: false } },
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (c) => ' ' + angka(c.parsed.x) + ' suara ('
                    + persen(persen_[c.dataIndex]) + ')',
                },
              },
            },
          },
        });
      }
    }

    /* Menyisipkan angka baru ke halaman. */
    function gambarUlang(h) {
      if (!h) { return; }
      const peta = { dpt: h.dpt, masuk: h.masuk, belum: h.belum, sah: h.sah };
      Object.keys(peta).forEach((kunci) => {
        const el = $('[data-angka="' + kunci + '"]');
        if (!el) { return; }
        const teks = angka(peta[kunci]);
        if (el.textContent !== teks) { el.textContent = teks; sorot(el); }
      });

      const turn = $('[data-angka="turnout"]');
      if (turn) {
        const teks = persen(h.turnout);
        if (turn.textContent !== teks) { turn.textContent = teks; sorot(turn); }
      }
      const barTurn = $('[data-bar="turnout"]');
      if (barTurn) { barTurn.style.width = Math.min(100, Number(h.turnout) || 0) + '%'; }

      /* Rincian tiap paslon */
      (h.paslon || []).forEach((p) => {
        const suara_ = $('[data-suara="' + p.id + '"]');
        const persen_ = $('[data-persen="' + p.id + '"]');
        const bar = $('[data-bar-persen="' + p.id + '"]');
        if (suara_) { const t = angka(p.suara); if (suara_.textContent !== t) { suara_.textContent = t; sorot(suara_); } }
        if (persen_) { const t = persen(p.persen); if (persen_.textContent !== t) { persen_.textContent = t; } }
        if (bar) { bar.style.width = (Number(p.persen) || 0) + '%'; }

        const legenda = $('[data-legenda="' + p.id + '"] [data-legenda-persen]');
        if (legenda) { legenda.textContent = persen(p.persen); }
      });

      /* Pemimpin sementara */
      let teratas = null;
      if ((h.sah || 0) > 0) {
        (h.paslon || []).forEach((p) => {
          if (!teratas || p.suara > teratas.suara) { teratas = p; }
        });
      }
      const namaPemenang  = $('[data-pemenang-nama]');
      const suaraPemenang = $('[data-pemenang-suara]');
      if (teratas && namaPemenang) {
        if (namaPemenang.textContent.trim() !== teratas.nama.trim()) { namaPemenang.textContent = teratas.nama; }
      }
      if (teratas && suaraPemenang) { suaraPemenang.textContent = angka(teratas.suara); }

      /* Grafik */
      if (chDonat) {
        chDonat.data.datasets[0].data = (h.paslon || []).map((p) => p.persen);
        chDonat.data.labels = (h.paslon || []).map((p) => 'Paslon ' + p.nomor);
        chDonat.update('none');
      }
      if (chBatang) {
        chBatang.data.datasets[0].data = (h.paslon || []).map((p) => p.suara);
        chBatang.data.labels = (h.paslon || []).map((p) => 'Paslon ' + p.nomor);
        chBatang.update('none');
      }

      /* Penanda waktu */
      const jam = (h.diperbarui || '').split(' ')[1] || '';
      const labelZona = window.EV_LABEL_ZONA || 'WITA';
      $$('[data-hasil-jam], [data-hasil-jam2]').forEach((el) => { el.textContent = jam + ' ' + labelZona; });
    }

    /* ---------------------- Penyegaran berkala (polling) ---------------------- */
    const chip = $('[data-partisipasi]');
    const url  = (wadah && wadah.getAttribute('data-api')) || (chip ? 'api_hasil.php' : null);
    if (!url) { return; }
    const jeda = Math.max(2, parseInt((wadah && wadah.getAttribute('data-jeda')) || '5', 10)) * 1000;

    let gagal = 0;
    async function segarkan() {
      if (document.hidden) { return; } /* hemat kuota saat tab tidak dilihat */
      try {
        const resp = await fetch(url + '?waktu=' + Date.now(), { headers: { 'Accept': 'application/json' } });
        if (!resp.ok) {
          gagal += 1;
          const status = $('[data-hasil-status]');
          if (resp.status === 403 && status) { status.textContent = 'DITUTUP'; }
          return;
        }
        const h = await resp.json();
        if (!h || !h.ok) { return; }
        gagal = 0;

        const status = $('[data-hasil-status]');
        if (status && status.textContent !== 'LIVE') { status.textContent = 'LIVE'; }

        if (wadah) { gambarUlang(h); }
        if (chip) {
          const el = $('[data-partisipasi-masuk]', chip);
          if (el) { const t = angka(h.masuk); if (el.textContent !== t) { el.textContent = t; sorot(el); } }
        }
      } catch (e) {
        gagal += 1;
        if (gagal === 3) { toast('Koneksi ke server terputus — hasil akan disegarkan kembali setelah tersambung.', 'kuning', 6000); }
      }
    }

    window.setInterval(segarkan, jeda);
    /* Segera menyegarkan begitu tab dibuka kembali. */
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { segarkan(); } });
    window.setTimeout(segarkan, 1500);
  }

  /* ======================= 11. HITUNG MUNDUR KELUAR ==================== */
  function initKeluar() {
    const kotak = $('[data-hitung-mundur-keluar]');
    if (!kotak) { return; }
    let sisa = parseInt(kotak.getAttribute('data-detik') || '8', 10);
    const label = $('[data-sisa]', kotak);

    const tik = window.setInterval(() => {
      sisa -= 1;
      if (label) { label.textContent = String(Math.max(0, sisa)); }
      if (sisa <= 0) {
        window.clearInterval(tik);
        window.location.href = 'logout.php?selesai=1';
      }
    }, 1000);

    /* Bila pemilih menekan tombol keluar sendiri, hentikan hitungan. */
    $$('[data-keluar-sekarang]').forEach((a) => a.addEventListener('click', () => window.clearInterval(tik)));
  }

  /* ======================= 12. NAVIGASI HP PANEL ======================= */
  function initNav() {
    const papan = $('#papanSamping');
    if (!papan) { return; }
    const lapis = $('#lapisNav');

    const tutup = () => {
      papan.classList.add('-translate-x-full');
      if (lapis) { lapis.classList.add('hidden'); }
    };
    const buka = () => {
      papan.classList.remove('-translate-x-full');
      if (lapis) { lapis.classList.remove('hidden'); }
    };

    $$('[data-nav-buka]').forEach((t) => t.addEventListener('click', buka));
    $$('[data-nav-tutup]').forEach((t) => t.addEventListener('click', tutup));
    window.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') { tutup(); } });
  }

  /* ======================= 13. IMPOR DATA PEMILIH ======================= */
  /* Membaca berkas Excel/CSV di peramban memakai SheetJS, menampilkan pratinjau,
     lalu mengirim isinya sebagai JSON. Pemetaan judul kolom -> medan DPT
     dilakukan di SERVER (inc/dpt.php) supaya aturannya hanya ada satu. */
  function initImpor() {
    const form = $('[data-impor]');
    if (!form) { return; }
    const berkas  = $('[data-impor-berkas]', form);
    const payload = $('[data-impor-payload]', form);
    const kotak   = $('[data-impor-pratinjau]', form);
    const ringkas = $('[data-impor-ringkas]', form);
    const kepala  = $('[data-impor-kepala]', form);
    const isi     = $('[data-impor-isi]', form);
    const pesan   = $('[data-impor-galat]', form);
    const tombol  = $('[data-impor-tombol]', form);
    const MAKS = 2000;

    function galat(teks) {
      if (pesan) { pesan.textContent = teks; pesan.classList.remove('hidden'); }
      if (tombol) { tombol.disabled = true; }
      if (kotak) { kotak.classList.add('hidden'); }
      payload.value = '';
    }

    /** Menyusun tabel HTML kecil dari data (dibuat lewat DOM, bukan innerHTML). */
    function gambarPratinjau(header, rows) {
      if (!kepala || !isi || !kotak || !ringkas) { return; }
      kepala.innerHTML = '';
      isi.innerHTML = '';
      header.slice(0, 8).forEach((judul) => {
        const th = document.createElement('th');
        th.className = 'border-b border-brand-200 px-2 py-1 font-bold uppercase tracking-wide text-brand-700';
        th.textContent = judul === '' ? '(tanpa judul)' : judul;
        kepala.appendChild(th);
      });
      rows.slice(0, 6).forEach((baris) => {
        const tr = document.createElement('tr');
        header.slice(0, 8).forEach((_, i) => {
          const td = document.createElement('td');
          td.className = 'border-b border-slate-100 px-2 py-1 text-slate-600 whitespace-nowrap';
          td.textContent = (baris[i] === undefined || baris[i] === null) ? '' : String(baris[i]);
          tr.appendChild(td);
        });
        isi.appendChild(tr);
      });
      ringkas.textContent = angka(rows.length) + ' baris data terbaca'
        + (rows.length > 6 ? ' (ditampilkan 6 baris pertama)' : '');
      kotak.classList.remove('hidden');
    }

    /** Mengubah daftar baris (array of array) dari lembar pertama berkas. */
    function bacaBaris(dariBerkas) {
      const buku = window.XLSX.read(dariBerkas, { type: 'array' });
      const lembar = buku.Sheets[buku.SheetNames[0]];
      return window.XLSX.utils.sheet_to_json(lembar, { header: 1, raw: false, defval: '', blankrows: false });
    }

    berkas.addEventListener('change', () => {
      const f = berkas.files && berkas.files[0];
      if (!f) { payload.value = ''; if (tombol) { tombol.disabled = true; } if (kotak) { kotak.classList.add('hidden'); } return; }
      if (typeof window.XLSX === 'undefined') {
        galat('Pustaka pembaca berkas tidak termuat. Muat ulang halaman, atau isi data lewat formulir satu per satu.');
        return;
      }
      if (pesan) { pesan.classList.add('hidden'); }

      const pembaca = new FileReader();
      pembaca.onerror = () => galat('Berkas tidak dapat dibaca. Coba simpan ulang sebagai CSV/Excel.');
      pembaca.onload = () => {
        try {
          let baris = bacaBaris(pembaca.result);
          /* Sebagian berkas CSV memakai pemisah titik koma (Excel Indonesia).
             Bila hasilnya hanya satu kolom, berkas dibaca ulang dengan pemisah itu. */
          const teks = new TextDecoder('utf-8').decode(new Uint8Array(pembaca.result));
          if ((!baris.length || baris[0].length <= 1) && teks.indexOf(';') !== -1) {
            const buku2 = window.XLSX.read(teks.replace(/^\uFEFF/, ''), { type: 'string', FS: ';' });
            baris = window.XLSX.utils.sheet_to_json(buku2.Sheets[buku2.SheetNames[0]],
              { header: 1, raw: false, defval: '', blankrows: false });
          }
          if (baris.length < 2) {
            galat('Berkas itu belum memuat data. Baris pertama harus berisi judul kolom, baris berikutnya datanya.');
            return;
          }

          /* BOM (\uFEFF) sering ikut menempel pada judul kolom pertama CSV
             hasil Excel — dibersihkan supaya pratinjau & pemetaan kolom bersih. */
          const header = baris[0].map((v) => String(v === null || v === undefined ? '' : v)
            .replace(/^\uFEFF/, '').trim());
          let rows = baris.slice(1);
          let catatanPotong = '';
          if (rows.length > MAKS) {
            catatanPotong = ' Hanya ' + angka(MAKS) + ' baris pertama yang akan diimpor — pecah berkasnya bila perlu.';
            rows = rows.slice(0, MAKS);
          }

          /* Peringatan ringan bila kolom wajib tidak terlihat (server tetap penentu
             akhir). Judul kolom yang diterima DIAMBIL DARI SERVER lewat atribut
             data-kolom-wajib: siswa memakai NISN, guru & tendik memakai NIK —
             jangan mematok salah satunya di sini. */
          const rapikan = (t) => String(t).toLowerCase().replace(/[^a-z0-9]/g, '');
          const kunci = header.map(rapikan);
          const diterima = (form.getAttribute('data-kolom-wajib') || 'nisn')
            .split(',').map(rapikan).filter((t) => t !== '');
          const labelWajib = form.getAttribute('data-label-wajib') || 'NISN';
          const lain = (form.getAttribute('data-kolom-lain') || '')
            .split(',').map(rapikan).filter((t) => t !== '');
          const jenisLain = form.getAttribute('data-jenis-lain') || 'jenis lain';
          const adaInduk = kunci.some((h) => diterima.some((d) => h === d || h.indexOf(d) === 0));
          const kolomLain = header[kunci.findIndex((h) => lain.some((d) => h === d || h.indexOf(d) === 0))];
          const adaNama = kunci.some((h) => h.indexOf('nama') === 0);

          if (!adaInduk || !adaNama) {
            /* Berkas tertukar tab: kolomnya milik jenis pemilih yang lain. */
            if (kolomLain !== undefined && kolomLain !== '') {
              galat('Berkas itu tampaknya berisi data ' + jenisLain + ' (memakai kolom "'
                + kolomLain + '"). Anda sedang membuka DPT ' + labelWajib
                + '. Buka tab DPT ' + jenisLain + ' lalu impor berkas itu di sana.');
            } else {
              galat('Kolom "' + labelWajib + '" dan "Nama" tidak ditemukan pada baris judul berkas. '
                + 'Unduh contoh format di bawah untuk memastikan susunannya.' + catatanPotong);
            }
            return;
          }

          payload.value = JSON.stringify({ header: header, rows: rows });
          gambarPratinjau(header, rows);
          if (pesan && catatanPotong) { pesan.textContent = catatanPotong.trim(); pesan.classList.remove('hidden'); }
          if (tombol) { tombol.disabled = false; }
          toast(angka(rows.length) + ' baris siap diimpor. Tekan "Impor Sekarang" untuk menyimpan.', 'info', 5000);
        } catch (e) {
          galat('Berkas tidak dapat dibaca: pastikan formatnya Excel (.xlsx/.xls) atau CSV.');
        }
      };
      pembaca.readAsArrayBuffer(f);
    });

    /* Cegah kiriman tanpa muatan (mis. Enter ditekan sebelum berkas dibaca). */
    form.addEventListener('submit', (ev) => {
      if (!payload.value) {
        ev.preventDefault();
        toast('Pilih berkas dan tunggu sampai pratinjaunya muncul terlebih dahulu.', 'kuning');
      }
    });
  }

  /* ============= 14. KARTU PEMILIH (membawa penyaring aktif) ============= */
  function initKartuCetak() {
    const tautan = $('[data-cetak-kartu]');
    if (!tautan) { return; }
    const isian = $('[data-cari]');
    const saring = $('[data-saring]');
    const dasar = tautan.getAttribute('data-dasar') || tautan.getAttribute('href');

    function perbarui() {
      const p = new URLSearchParams();
      const c = isian ? isian.value.trim() : '';
      const s = saring ? saring.value : 'semua';
      if (c) { p.set('cari', c); }
      if (s && s !== 'semua') { p.set('status', s); }
      const q = p.toString();
      tautan.setAttribute('href', dasar + (q ? '?' + q : ''));
    }
    if (isian) { isian.addEventListener('input', perbarui); }
    if (saring) { saring.addEventListener('change', perbarui); }
    perbarui();
  }

  /* ================= 15. TOMBOL "TUTUP" PADA KARTU HASIL ================= */
  function initTutupKartu() {
    $$('[data-tutup-kartu]').forEach((tombol) => {
      tombol.addEventListener('click', () => {
        const sasaran = document.querySelector(tombol.getAttribute('data-tutup-kartu'));
        if (sasaran) { sasaran.remove(); }
      });
    });
  }

  /* ============ 15b. JENIS PEMILIH (Siswa / Guru) pada formulir DPT ============ */
  function initJenisPemilih() {
    const radio = $$('[data-jenis-pemilih]');
    if (!radio.length) { return; }
    const labelInduk = $('[data-label-induk]');
    const ketInduk   = $('[data-ket-induk]');
    const induk      = $('[data-induk]');

    const aturan = {
      siswa: {
        induk: 'NISN / NIS', contoh: 'mis. 0071234501',
        labelInduk: 'NISN / NIS', ket: 'Nomor Induk Siswa Nasional (10 digit) atau NIS sekolah.',
      },
      guru: {
        induk: 'NIK', contoh: 'mis. 197501012005011001',
        labelInduk: 'NIK (Nomor Induk Kependudukan)', ket: 'NIK dipakai sebagai nomor induk pemilih guru & tendik.',
      },
    };

    function terapkan(jenis) {
      const a = aturan[jenis] || aturan.siswa;
      if (labelInduk) { labelInduk.textContent = a.labelInduk; }
      if (ketInduk) { ketInduk.textContent = a.ket; }
      if (induk) { induk.setAttribute('placeholder', a.contoh); }
      $$('[data-medan-kelompok]').forEach((blok) => {
        blok.classList.toggle('hidden', blok.getAttribute('data-medan-kelompok') !== jenis);
      });
      /* Medan yang disembunyikan tidak dikirim; medan jenis yang tampil wajib diisi. */
      $$('[data-medan-kelompok]').forEach((blok) => {
        const isian = blok.querySelector('input');
        if (isian) { isian.required = (blok.getAttribute('data-medan-kelompok') === jenis); }
      });
    }

    radio.forEach((r) => r.addEventListener('change', () => { if (r.checked) { terapkan(r.value); } }));
    const terpilih = radio.find((r) => r.checked);
    terapkan(terpilih ? terpilih.value : 'siswa');
  }

  /* ============ 16. JENIS LEMBAGA (penanda belum disimpan) ============ */
  function initJenisLembaga() {
    const form = $('[data-jenis-lembaga]');
    if (!form) { return; }
    const pilih = $('select[name="jenis_lembaga"]', form);
    const catatan = $('[data-jenis-catatan]', form);
    const tombol = $('button[type="submit"]', form);
    if (!pilih || !catatan) { return; }
    const tersimpan = form.getAttribute('data-tersimpan') || pilih.value;

    function periksa() {
      const beda = pilih.value !== tersimpan;
      catatan.classList.toggle('hidden', !beda);
      if (tombol) { tombol.classList.toggle('ring-4', beda); tombol.classList.toggle('ring-amber-200', beda); }
    }
    pilih.addEventListener('change', periksa);
    periksa();
  }

  /* ============================ PENYIAPAN ============================= */
  function siapkan() {
    initToast();
    initDialog();
    initPilih();
    initKonfirmasi();
    initTab();
    initCari();
    initSalin();
    initToken();
    initFoto();
    initHasil();
    initKeluar();
    initNav();
    initImpor();
    initKartuCetak();
    initTutupKartu();
    initJenisLembaga();
    initJenisPemilih();
  }

  return { siapkan, toast, angka, persen };
})();
