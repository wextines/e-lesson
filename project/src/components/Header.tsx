import { useState } from 'react';
import { Menu, X, ArrowUpRight } from 'lucide-react';
import type { Lang, Translation } from '@/i18n';
import type { TenantData } from '@/types';
import { useScrolled } from '@/hooks';

interface HeaderProps {
  t: Translation;
  lang: Lang;
  tenant?: TenantData | null;
  onLangChange: (lang: Lang) => void;
  onContactClick: () => void;
}

const NAV_IDS = ['about', 'courses', 'results', 'contacts'] as const;
const LANGS: Lang[] = ['ru', 'uz', 'en'];

export function Header({ t, lang, tenant, onLangChange, onContactClick }: HeaderProps) {
  const scrolled = useScrolled(24);
  const [menuOpen, setMenuOpen] = useState(false);

  const centerName = tenant?.name || 'Сфера';
  const initialLetter = centerName.charAt(0);

  const scrollTo = (id: string) => {
    setMenuOpen(false);
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-350 ease-smooth ${
        scrolled
          ? 'bg-canvas-100/80 backdrop-blur-xl border-b border-ink-100/30'
          : 'bg-transparent border-b border-transparent'
      }`}
    >
      <div className="container-edge flex items-center justify-between h-14 md:h-16">
        {/* Logo */}
        <button
          onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
          className="group flex items-center gap-2"
          aria-label={`${centerName} — home`}
        >
          <span
            className={`flex h-8 w-8 items-center justify-center rounded-full border transition-colors duration-300 ${
              scrolled
                ? 'border-ink-200 text-ink-600'
                : 'border-ink-100/60 text-ink-500'
            } group-hover:border-ink-400 group-hover:text-ink-700`}
          >
            <span className="font-display text-sm font-700 leading-none">{initialLetter}</span>
          </span>
          <span
            className={`font-display text-base font-600 tracking-tight transition-colors duration-300 ${
              scrolled ? 'text-ink-700' : 'text-ink-600'
            }`}
          >
            {centerName}
          </span>
        </button>

        {/* Desktop nav */}
        <nav className="hidden lg:flex items-center gap-8">
          {NAV_IDS.map((id) => (
            <button
              key={id}
              onClick={() => scrollTo(id)}
              className="group relative text-sm font-400 text-ink-400 transition-colors duration-200 hover:text-ink-700"
            >
              {t.nav[id]}
              <span className="absolute -bottom-1.5 left-0 h-px w-0 bg-ink-400 transition-all duration-300 ease-smooth group-hover:w-full" />
            </button>
          ))}
        </nav>

        {/* Right side */}
        <div className="flex items-center gap-3">
          {/* Language switcher */}
          <div className="hidden sm:flex items-center gap-0.5">
            {LANGS.map((l) => (
              <button
                key={l}
                onClick={() => onLangChange(l)}
                className={`px-1.5 py-1 text-[11px] font-500 uppercase tracking-wider transition-colors duration-200 ${
                  lang === l
                    ? 'text-ink-600'
                    : 'text-ink-100 hover:text-ink-300'
                }`}
                aria-pressed={lang === l}
              >
                {t.lang[l]}
              </button>
            ))}
          </div>

          <div className="hidden sm:block h-4 w-px bg-ink-100/60" />

          {/* CTA */}
          <button
            onClick={onContactClick}
            className="hidden md:inline-flex items-center gap-1.5 rounded-full bg-ink-700 px-4 py-2 text-sm font-500 text-canvas-50 transition-all duration-300 ease-smooth hover:bg-ink-600 hover:gap-2.5"
          >
            {t.nav.cta}
            <ArrowUpRight className="h-3.5 w-3.5" strokeWidth={2} />
          </button>

          {/* Mobile menu toggle */}
          <button
            onClick={() => setMenuOpen((v) => !v)}
            className="lg:hidden flex h-8 w-8 items-center justify-center text-ink-500"
            aria-label="Menu"
            aria-expanded={menuOpen}
          >
            {menuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>

      {/* Mobile menu */}
      <div
        className={`lg:hidden overflow-hidden transition-all duration-350 ease-smooth ${
          menuOpen ? 'max-h-96 border-t border-ink-100/30' : 'max-h-0'
        }`}
      >
        <div className="container-edge py-5 space-y-0.5 bg-canvas-100/95 backdrop-blur-xl">
          {NAV_IDS.map((id) => (
            <button
              key={id}
              onClick={() => scrollTo(id)}
              className="block w-full text-left py-2.5 text-base font-500 text-ink-500 border-b border-ink-100/20"
            >
              {t.nav[id]}
            </button>
          ))}
          <div className="flex items-center justify-between pt-4">
            <div className="flex items-center gap-1">
              {LANGS.map((l) => (
                <button
                  key={l}
                  onClick={() => onLangChange(l)}
                  className={`px-2 py-1 text-sm font-500 uppercase tracking-wider transition-colors ${
                    lang === l ? 'text-ink-600' : 'text-ink-100'
                  }`}
                >
                  {t.lang[l]}
                </button>
              ))}
            </div>
            <button
              onClick={() => {
                setMenuOpen(false);
                onContactClick();
              }}
              className="inline-flex items-center gap-1.5 rounded-full bg-ink-700 px-4 py-2 text-sm font-500 text-canvas-50"
            >
              {t.nav.cta}
              <ArrowUpRight className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
