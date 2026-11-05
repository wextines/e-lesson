import type { Translation } from '@/i18n';

interface WhySferaProps {
  t: Translation;
}

export function WhySfera({ t }: WhySferaProps) {
  const { why } = t;

  return (
    <section id="why" className="py-12 md:py-16 bg-canvas-50 border-y border-ink-100/30">
      <div className="container-edge">
        {/* Header — clean left-aligned */}
        <div className="max-w-3xl mb-8 md:mb-12">
          <div className="mb-3.5 reveal">
            <span className="eyebrow">{why.eyebrow}</span>
          </div>
          <h2 className="font-display font-700 text-ink-700 text-3xl sm:text-4xl md:text-[44px] lg:text-[48px] tracking-tight leading-[1.12] text-balance mb-4 reveal reveal-delay-1">
            {why.title}
          </h2>
          <p className="text-sm sm:text-base md:text-lg text-ink-400 leading-relaxed max-w-2xl reveal reveal-delay-2">
            {why.text}
          </p>
        </div>

        {/* Three benefits — compact 3-column grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 border border-ink-100/30 rounded-soft overflow-hidden">
          {why.stories.map((story, i) => (
            <div
              key={story.num}
              className={`reveal reveal-delay-${i + 1} bg-canvas-50 p-5 md:p-6 flex flex-col ${
                i < 2 ? 'md:border-r border-ink-100/30' : ''
              } ${i === 0 ? 'border-b md:border-b-0 border-ink-100/30' : ''} ${
                i === 1 ? 'border-b md:border-b-0 border-ink-100/30' : ''
              }`}
            >
              <div className="flex items-baseline gap-3 mb-3">
                <span className="font-display text-xl font-700 text-ink-200 leading-none">
                  {story.num}
                </span>
                <span className="eyebrow">{story.label}</span>
              </div>
              <h3 className="font-display font-600 text-ink-700 text-lg md:text-xl text-balance mb-2.5 leading-snug">
                {story.title}
              </h3>
              <p className="text-sm text-ink-400 leading-relaxed">
                {story.text}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
