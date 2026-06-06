/** @type {import('tailwindcss').Config} */
export default {
  content: ['./admin-app/src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        aeo: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          950: '#1e1b4b',
        },
        surface: {
          DEFAULT: '#ffffff',
          muted: '#f8fafc',
          elevated: '#ffffff',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
        display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(0 0 0 / 0.04), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
        'card-hover': '0 10px 25px -5px rgb(0 0 0 / 0.08), 0 4px 10px -4px rgb(0 0 0 / 0.04)',
        glow: '0 0 20px -5px rgb(99 102 241 / 0.35)',
        sidebar: '4px 0 24px -4px rgb(0 0 0 / 0.08)',
      },
      borderRadius: {
        xl: '0.875rem',
        '2xl': '1rem',
        '3xl': '1.25rem',
      },
      animation: {
        'fade-in': 'fadeIn 0.3s ease-out',
        'slide-up': 'slideUp 0.35s ease-out',
        'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
        shimmer: 'shimmer 2s linear infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(12px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.7' },
        },
        shimmer: {
          '0%': { backgroundPosition: '-200% 0' },
          '100%': { backgroundPosition: '200% 0' },
        },
      },
      backgroundImage: {
        'gradient-brand': 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%)',
        'gradient-brand-subtle': 'linear-gradient(135deg, #eef2ff 0%, #f5f3ff 100%)',
        'gradient-dark': 'linear-gradient(180deg, #1e1b4b 0%, #312e81 100%)',
        'gradient-mesh': 'radial-gradient(at 40% 20%, rgb(99 102 241 / 0.12) 0px, transparent 50%), radial-gradient(at 80% 0%, rgb(139 92 246 / 0.1) 0px, transparent 50%), radial-gradient(at 0% 50%, rgb(99 102 241 / 0.08) 0px, transparent 50%)',
      },
    },
  },
  plugins: [],
  corePlugins: {
    preflight: false,
  },
};
