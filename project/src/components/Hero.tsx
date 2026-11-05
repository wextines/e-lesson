import { ArrowRight } from 'lucide-react';
import type { Translation } from '@/i18n';
import { IMAGES } from '@/images';

interface HeroProps {
  t: Translation;
  onContactClick: () => void;
  onCoursesClick: () => void;
}

export function Hero({ t, onContactClick, onCoursesClick }: HeroProps) {
  const { hero } = t;

  return (
    <section
      id="about"
      className="relative min-h-[calc(100vh-4rem)] lg:min-h-screen flex flex-col justify-between pt-20 md:pt-24 lg:pt-28 pb-8 md:pb-12 overflow-hidden"
    >
      <div className="container-edge flex-1 flex flex-col justify-between">
        {/* Top & Main Hero Content */}
        <div>
          {/* Top row: eyebrow + meta */}
          <div className="flex items-start justify-between mb-5 md:mb-6 reveal">
            <span className="eyebrow">{hero.eyebrow}</span>
            <span className="hidden md:block text-[11px] font-500 text-ink-200 tracking-wider">
              EST. 2021
            </span>
          </div>

          {/* Headline + image — balanced modern editorial */}
          <div className="grid grid-cols-12 gap-6 md:gap-10 lg:gap-12 items-center">
            {/* Left: headline + text + buttons */}
            <div className="col-span-12 lg:col-span-6 reveal reveal-delay-1">
              <h1 className="font-display font-700 text-ink-700 text-3xl sm:text-4xl md:text-5xl lg:text-[52px] xl:text-[56px] tracking-tight leading-[1.08] text-balance mb-4 md:mb-5">
                {hero.headline1}
                <br />
                {hero.headline2}{' '}
                <span className="text-cobalt-500">
                  {hero.headlineHighlight || ''}
                </span>
              </h1>
              <p className="text-sm md:text-base text-ink-400 leading-relaxed max-w-md mb-6 md:mb-8">
                {hero.text}
              </p>
              <div className="flex flex-wrap items-center gap-3.5">
                <button
                  onClick={onContactClick}
                  className="group inline-flex items-center gap-2 rounded-full bg-ink-700 px-5 py-3 text-sm font-500 text-canvas-50 transition-all duration-300 ease-smooth hover:bg-ink-600 hover:gap-3"
                >
                  {hero.btnPrimary}
                  <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5" />
                </button>
                <button
                  onClick={onCoursesClick}
                  className="group inline-flex items-center gap-2 rounded-full border border-ink-100 bg-transparent px-5 py-3 text-sm font-500 text-ink-600 transition-all duration-300 ease-smooth hover:border-ink-300 hover:text-ink-800"
                >
                  {hero.btnSecondary}
                  <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5" />
                </button>
              </div>
            </div>

            {/* Right: modern building editorial image */}
            <div className="col-span-12 lg:col-span-6 reveal reveal-delay-2">
              <div className="relative h-[250px] sm:h-[320px] lg:h-[380px] xl:h-[420px] overflow-hidden rounded-2xl shadow-sm group">
                <img
                  src={IMAGES.heroWide}
                  alt="Edu Expo educational center building"
                  className="h-full w-full object-cover object-center transition-transform duration-[1.2s] ease-smooth group-hover:scale-[1.03]"
                  loading="eager"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Stats — horizontal strip pinned naturally to bottom of first viewport */}
        <div className="border-t border-ink-100/40 pt-6 md:pt-8 mt-8 md:mt-10">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-y-4 md:gap-y-0">
            {hero.stats.map((stat, i) => (
              <div
                key={i}
                className={`reveal reveal-delay-${i + 1} ${
                  i > 0 ? 'md:border-l md:border-ink-100/40 md:pl-6' : ''
                }`}
              >
                <div className="font-display text-2xl md:text-3xl font-700 text-ink-700 tracking-tight">
                  {stat.value}
                </div>
                <div className="mt-0.5 text-xs md:text-sm text-ink-300 font-400">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
