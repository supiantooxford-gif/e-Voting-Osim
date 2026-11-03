/** Konfigurasi Tailwind CSS untuk E-Voting OSIS.
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
        // Warna utama: Deep Blue / Indigo (kesan formal pemilu)
        brand: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#312e81',
          900: '#1e1b4b',
          950: '#131036',
        },
      },
      boxShadow: {
        soft: '0 12px 32px -14px rgb(15 23 42 / .28)',
        naik: '0 20px 42px -18px rgb(49 46 129 / .45)',
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
