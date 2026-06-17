/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './includes/**/*.php',
    './api/**/*.php',
    './assets/js/**/*.js',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: 'rgb(var(--brand, 99 102 241) / <alpha-value>)',
          dark:    'rgb(var(--brand-dark, 79 70 229) / <alpha-value>)',
        }
      },
      fontFamily: { mono: ['JetBrains Mono', 'monospace'] }
    }
  },
  plugins: [],
}
