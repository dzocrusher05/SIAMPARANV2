/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/**/*.{html,php,js}",
    "./admin/**/*.{html,php,js}",
    "./api/**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        tulang: '#F8F5F0',
        abu: {
          50: '#F7F7F7',
          100: '#ECECEC',
          200: '#D9D9D9',
          300: '#BFBFBF',
          400: '#A6A6A6',
          500: '#8C8C8C',
          600: '#737373',
          700: '#595959',
          800: '#404040',
          900: '#262626',
        },
      },
      boxShadow: {
        soft: '0 6px 24px rgba(0,0,0,.08)',
      },
      borderRadius: {
        '2xl': '1.25rem',
      },
    },
  },
  plugins: [],
}

