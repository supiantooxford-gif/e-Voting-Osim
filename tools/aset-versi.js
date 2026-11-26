/**
 * tools/aset-versi.js — menyiapkan berkas aset BERVERSI.
 * ---------------------------------------------------------------------------
 * Aset statis di server/CDN dapat tersaji dari simpanan lama meskipun query
 * string (?v=) berubah. Karena itu nama berkasnya ikut versi aplikasi:
 *   assets/app.css  ->  assets/app-<APP_VERSION>.css
 *   assets/app.js   ->  assets/app-<APP_VERSION>.js
 * inc/config.php menuliskan nama itu lewat ASET_CSS / ASET_JS.
 *
 * Jalankan setelah membangun Tailwind:  npm run css
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const cfg = fs.readFileSync(path.join(root, 'inc/config.php'), 'utf8');
const cocok = cfg.match(/const\s+APP_VERSION\s*=\s*'([^']+)'/);
if (!cocok) {
  console.error('APP_VERSION tidak ditemukan di inc/config.php');
  process.exit(1);
}
const versi = cocok[1];

const pasangan = [
  ['assets/app.css', `assets/app-${versi}.css`],
  ['assets/app.js', `assets/app-${versi}.js`],
];

for (const [dari, ke] of pasangan) {
  const sumber = path.join(root, dari);
  if (!fs.existsSync(sumber)) {
    console.error('Berkas sumber tidak ada:', dari);
    process.exit(1);
  }
  fs.copyFileSync(sumber, path.join(root, ke));
  /* Hapus salinan versi lain supaya folder tidak menumpuk. */
  const folder = path.dirname(path.join(root, ke));
  const pola = new RegExp('^' + path.basename(dari, path.extname(dari)) + '-[0-9.]+' + path.extname(dari) + '$');
  for (const nama of fs.readdirSync(folder)) {
    if (pola.test(nama) && nama !== path.basename(ke)) {
      fs.unlinkSync(path.join(folder, nama));
    }
  }
  console.log(`aset berversi: ${ke}`);
}
