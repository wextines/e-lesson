/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Warm off-white background system
        canvas: {
          50: '#FBFAF7',
          100: '#F6F4EF',
          200: '#EFECE5',
          300: '#E4E0D6',
        },
        // Deep navy ink system
        ink: {
          50: '#E8EDF4',
          100: '#C7D2E3',
          200: '#8FA3C4',
          300: '#5A7399',
          400: '#34517A',
          500: '#1B3559',
          600: '#122849',
          700: '#0C1B33',
          800: '#081327',
          900: '#050D1C',
        },
        // Cobalt blue accent
        cobalt: {
          50: '#EBF0FF',
          100: '#D6E1FF',
          200: '#A8BEFF',
          300: '#7A9BFF',
          400: '#4C78FF',
          500: '#2546F0',
          600: '#1B36C4',
          700: '#15289A',
          800: '#101E73',
          900: '#0B154F',
        },
        // Functional
        success: '#1B8A4B',
        warning: '#C97A0A',
        error: '#C23A3A',
      },
      fontFamily: {
        display: ['"Schibsted Grotesk"', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
        serif: ['"Instrument Serif"', 'Georgia', 'serif'],
      },
      fontWeight: {
        '300': '300',
        '400': '400',
        '500': '500',
        '600': '600',
        '700': '700',
        '800': '800',
        '900': '900',
      },
      fontSize: {
        // Fluid display sizes
        'display-xl': ['clamp(3rem, 7vw, 6.5rem)', { lineHeight: '0.95', letterSpacing: '-0.03em' }],
        'display-lg': ['clamp(2.5rem, 5.5vw, 4.5rem)', { lineHeight: '1.0', letterSpacing: '-0.025em' }],
        'display-md': ['clamp(2rem, 4vw, 3.25rem)', { lineHeight: '1.05', letterSpacing: '-0.02em' }],
        'display-sm': ['clamp(1.5rem, 2.5vw, 2.25rem)', { lineHeight: '1.1', letterSpacing: '-0.015em' }],
      },
      spacing: {
        '18': '4.5rem',
        '22': '5.5rem',
        '30': '7.5rem',
        '38': '9.5rem',
      },
      maxWidth: {
        '8xl': '88rem',
        '9xl': '100rem',
      },
      borderRadius: {
        'sharp': '2px',
        'soft': '6px',
        'card': '10px',
      },
      transitionDuration: {
        '250': '250ms',
        '350': '350ms',
      },
      transitionTimingFunction: {
        'smooth': 'cubic-bezier(0.22, 1, 0.36, 1)',
        'out-expo': 'cubic-bezier(0.16, 1, 0.3, 1)',
      },
      keyframes: {
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(24px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'scale-in': {
          '0%': { opacity: '0', transform: 'scale(0.96)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        'marquee': {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(-50%)' },
        },
      },
      animation: {
        'fade-up': 'fade-up 0.7s cubic-bezier(0.16,1,0.3,1) both',
        'fade-in': 'fade-in 0.6s ease both',
        'scale-in': 'scale-in 0.5s cubic-bezier(0.16,1,0.3,1) both',
        'marquee': 'marquee 40s linear infinite',
      },
    },
  },
  plugins: [],
};
