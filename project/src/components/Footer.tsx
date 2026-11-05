import { Phone, Mail, MapPin, Clock, ArrowUpRight } from 'lucide-react';
import type { Translation } from '@/i18n';
import type { TenantData } from '@/types';

interface FooterProps {
  t: Translation;
  tenant?: TenantData | null;
}

const NAV_IDS = ['about', 'courses', 'results', 'contacts'] as const;

export function Footer({ t, tenant }: FooterProps) {
  const { footer, nav } = t;
  const year = new Date().getFullYear();

  const centerName = tenant?.name || 'Сфера';
  const initialLetter = centerName.charAt(0);
  const phone = tenant?.phone || footer.phone;
  const email = tenant?.email || footer.email;

  const scrollTo = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <footer className="bg-ink-900 text-canvas-50/50">
      <div className="container-edge py-8 md:py-12">
        <div className="grid grid-cols-12 gap-6 md:gap-10">
          {/* Brand */}
          <div className="col-span-12 md:col-span-5">
            <div className="flex items-center gap-2 mb-4">
              <span className="flex h-8 w-8 items-center justify-center rounded-full border border-canvas-50/15 text-canvas-50/80">
                <span className="font-display text-sm font-700 leading-none">{initialLetter}</span>
              </span>
              <span className="font-display text-base font-600 text-canvas-50/90">{centerName}</span>
            </div>
            <p className="text-sm leading-relaxed max-w-sm">{footer.tagline}</p>
          </div>

          {/* Nav */}
          <div className="col-span-6 md:col-span-3">
            <h4 className="text-[11px] font-500 uppercase tracking-[0.15em] text-canvas-50/30 mb-4">
              {footer.nav}
            </h4>
            <ul className="space-y-2.5">
              {NAV_IDS.map((id) => (
                <li key={id}>
                  <button
                    onClick={() => scrollTo(id)}
                    className="group inline-flex items-center gap-1 text-sm text-canvas-50/50 transition-colors hover:text-canvas-50/90"
                  >
                    {nav[id]}
                    <ArrowUpRight className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                  </button>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact */}
          <div className="col-span-12 md:col-span-4">
            <h4 className="text-[11px] font-500 uppercase tracking-[0.15em] text-canvas-50/30 mb-4">
              {t.nav.contacts}
            </h4>
            <ul className="space-y-3 text-sm">
              <li>
                <a
                  href={`tel:${phone.replace(/\s/g, '')}`}
                  className="flex items-center gap-3 text-canvas-50/60 transition-colors hover:text-canvas-50/90"
                >
                  <Phone className="h-3.5 w-3.5 text-canvas-50/30" strokeWidth={1.5} />
                  {phone}
                </a>
              </li>
              <li>
                <a
                  href={`mailto:${email}`}
                  className="flex items-center gap-3 text-canvas-50/60 transition-colors hover:text-canvas-50/90"
                >
                  <Mail className="h-3.5 w-3.5 text-canvas-50/30" strokeWidth={1.5} />
                  {email}
                </a>
              </li>
              <li className="flex items-center gap-3">
                <MapPin className="h-3.5 w-3.5 text-canvas-50/30 shrink-0" strokeWidth={1.5} />
                {footer.address}
              </li>
              <li className="flex items-center gap-3">
                <Clock className="h-3.5 w-3.5 text-canvas-50/30 shrink-0" strokeWidth={1.5} />
                {footer.hours}
              </li>
            </ul>
          </div>
        </div>

        {/* Bottom bar */}
        <div className="mt-8 pt-4 border-t border-canvas-50/8 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p className="text-xs text-canvas-50/30">
            © {year} {centerName}. {footer.rights}.
          </p>
          <p className="text-xs text-canvas-50/30">{footer.madeWith}</p>
        </div>
      </div>
    </footer>
  );
}
