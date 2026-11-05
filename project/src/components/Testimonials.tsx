import { useState, useEffect, useCallback } from 'react';
import { ArrowLeft, ArrowRight, Star } from 'lucide-react';
import type { Translation } from '@/i18n';

interface TestimonialsProps {
  t: Translation;
}

export function Testimonials({ t }: TestimonialsProps) {
  const { results } = t;
  const [active, setActive] = useState(0);
  const count = results.testimonials.length;

  const next = useCallback(() => setActive((v) => (v + 1) % count), [count]);
  const prev = useCallback(() => setActive((v) => (v - 1 + count) % count), [count]);

  useEffect(() => {
    const timer = setInterval(next, 8000);
    return () => clearInterval(timer);
  }, [next]);

  return (
    <section id="results" className="py-10 md:py-12 bg-ink-700 text-canvas-50 overflow-hidden">
      <div className="container-edge">
        {/* Header + carousel in a tighter layout */}
        <div className="grid grid-cols-12 gap-6 md:gap-10">
          {/* Left: eyebrow + title + controls */}
          <div className="col-span-12 md:col-span-4 reveal">
            <span className="eyebrow-light mb-4 block">{results.eyebrow}</span>
            <h2 className="font-display font-600 text-display-md text-balance mb-5">
              {results.title}
            </h2>
            {/* Controls */}
            <div className="flex items-center gap-3">
              <button
                onClick={prev}
                className="flex h-9 w-9 items-center justify-center rounded-full border border-canvas-50/15 text-canvas-50/60 transition-all duration-300 hover:border-canvas-50/40 hover:text-canvas-50"
                aria-label={results.prev}
              >
                <ArrowLeft className="h-4 w-4" />
              </button>
              <button
                onClick={next}
                className="flex h-9 w-9 items-center justify-center rounded-full border border-canvas-50/15 text-canvas-50/60 transition-all duration-300 hover:border-canvas-50/40 hover:text-canvas-50"
                aria-label={results.next}
              >
                <ArrowRight className="h-4 w-4" />
              </button>
              <div className="flex items-center gap-2 ml-1">
                {results.testimonials.map((_, i) => (
                  <button
                    key={i}
                    onClick={() => setActive(i)}
                    className={`h-1.5 rounded-full transition-all duration-300 ${
                      i === active
                        ? 'w-7 bg-cobalt-400'
                        : 'w-1.5 bg-canvas-50/25 hover:bg-canvas-50/40'
                    }`}
                    aria-label={`Slide ${i + 1}`}
                  />
                ))}
              </div>
            </div>
          </div>

          {/* Right: quote */}
          <div className="col-span-12 md:col-span-8 reveal reveal-delay-1">
            <div className="relative min-h-[120px] md:min-h-[140px]">
              {results.testimonials.map((tm, i) => (
                <blockquote
                  key={i}
                  className={`absolute inset-0 transition-all duration-500 ease-smooth ${
                    i === active
                      ? 'opacity-100 translate-y-0'
                      : 'opacity-0 translate-y-3 pointer-events-none'
                  }`}
                >
                  <p className="font-display text-base md:text-lg lg:text-xl font-400 leading-[1.4] text-canvas-50/95 text-balance mb-4">
                    {tm.quote}
                  </p>
                  <div className="flex items-center gap-3">
                    <div className="flex items-center gap-0.5">
                      {Array.from({ length: tm.rating }).map((_, s) => (
                        <Star
                          key={s}
                          className="h-3.5 w-3.5 fill-cobalt-400 text-cobalt-400"
                        />
                      ))}
                    </div>
                    <div className="h-3.5 w-px bg-canvas-50/15" />
                    <div>
                      <span className="font-600 text-canvas-50">{tm.author}</span>
                      <span className="text-sm text-canvas-50/50 ml-2">{tm.meta}</span>
                    </div>
                  </div>
                </blockquote>
              ))}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
