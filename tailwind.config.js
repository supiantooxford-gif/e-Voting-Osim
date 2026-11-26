/** Konfigurasi Tailwind CSS untuk E-Voting OSIM.
 *  Tailwind DIKOMPILASI ke assets/app.css (bukan CDN) supaya aplikasi tetap
 *  ringan dipakai dari HP siswa di bilik suara (tanpa permintaan ke luar).
 *  Cara membangun ulang:
 *    npm install --no-audit --no-fund tailwindcss@3.4.16
 *    npx tailwindcss -i assets/tailwind.css -o assets/app.css --minify
 */
module.exports = {
  content: ['./**/*.php', './assets/app.js', './assets/tailwind.css'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      colors: {
        // Warna utama: HIJAU (permintaan pemilik v2.3.0 — segar, tenang, islami)
        brand: {
          50: '#ecfdf5',
          100: '#d1fae5',
          200: '#a7f3d0',
          300: '#6ee7b7',
          400: '#34d399',
          500: '#10b981',
          600: '#059669',
          700: '#047857',
          800: '#065f46',
          900: '#064e3b',
          950: '#022c22',
        },
        // Aksen KUNING KEEMASAN (kombinasi kedua, dipakai hemat: aksen, lencana, angka)
        emas: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
          950: '#451a03',
        },
      },
      boxShadow: {
        soft: '0 12px 32px -14px rgb(15 23 42 / .28)',
        naik: '0 20px 42px -18px rgb(6 95 70 / .42)',
      },
      keyframes: {
        masuk: {
          '0%': { opacity: '0', transform: 'translateY(10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        pop: {
          '0%': { opacity: '0', transform: 'scale(.94)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        geserKanan: {
          '0%': { opacity: '0', transform: 'translateX(24px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        denyutRing: {
          '0%': { transform: 'scale(.9)', opacity: '.7' },
          '100%': { transform: 'scale(1.6)', opacity: '0' },
        },
        naikTurun: {
          '0%,100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-6px)' },
        },
      },
      animation: {
        masuk: 'masuk .45s cubic-bezier(.22,.9,.3,1) both',
        pop: 'pop .22s cubic-bezier(.2,.9,.3,1.25) both',
        geser: 'geserKanan .3s ease-out both',
        denyut: 'denyutRing 1.6s ease-out infinite',
        melayang: 'naikTurun 3.4s ease-in-out infinite',
      },
    },
  },
  plugins: [],
};
