import { ArrowUpRight, Star } from 'lucide-react';
import type { Translation } from '@/i18n';
import type { ApiCourse } from '@/types';
import { IMAGES } from '@/images';

interface CoursesProps {
  t: Translation;
  courses: ApiCourse[];
  isLoading: boolean;
  isError: boolean;
  selectedCourse: string;
  onCourseSelect: (courseId: string) => void;
}

export function Courses({
  t,
  courses,
  isLoading,
  isError,
  selectedCourse,
  onCourseSelect,
}: CoursesProps) {
  const { courses: cLocale } = t;

  const featured = courses[0];
  const rows = courses.slice(0, 3);

  const formatPrice = (price: number): string => {
    if (price <= 0) {
      if (t.nav.courses === 'Kurslar') return 'Bepul';
      if (t.nav.courses === 'Courses') return 'Free';
      return 'Бесплатно';
    }
    const formatted = price.toLocaleString('ru-RU');
    if (t.nav.courses === 'Kurslar') return `${formatted} so'm`;
    if (t.nav.courses === 'Courses') return `${formatted} UZS`;
    return `${formatted} сум`;
  };

  const formatGrade = (classLevel: string): string => {
    if (!classLevel) return '';
    if (t.nav.courses === 'Kurslar') return `${classLevel} sinf`;
    if (t.nav.courses === 'Courses') return `Grades ${classLevel}`;
    return `${classLevel} класс`;
  };

  return (
    <section id="courses" className="py-10 md:py-14">
      <div className="container-edge">
        {/* Header */}
        <div className="grid grid-cols-12 gap-6 mb-6 md:mb-10">
          <div className="col-span-12 lg:col-span-4 reveal">
            <span className="eyebrow">{cLocale.eyebrow}</span>
          </div>
          <div className="col-span-12 lg:col-span-8 reveal reveal-delay-1">
            <h2 className="font-display font-600 text-ink-700 text-display-lg text-balance mb-3">
              {cLocale.title}
            </h2>
            <p className="text-sm md:text-base text-ink-400 max-w-2xl leading-relaxed">
              {cLocale.text}
            </p>
          </div>
        </div>

        {/* Loading State */}
        {isLoading && (
          <div className="reveal mb-6 md:mb-10 animate-pulse">
            <div className="grid grid-cols-12 gap-4 md:gap-6 items-stretch">
              <div className="col-span-12 md:col-span-7 bg-ink-100/30 rounded-soft min-h-[220px]" />
              <div className="col-span-12 md:col-span-5 bg-canvas-50 border border-ink-100/40 rounded-soft p-5 md:p-7 flex flex-col justify-between">
                <div>
                  <div className="h-4 w-24 bg-ink-100/50 rounded mb-3" />
                  <div className="h-8 w-48 bg-ink-100/50 rounded mb-3" />
                  <div className="h-4 w-full bg-ink-100/30 rounded mb-2" />
                  <div className="h-4 w-3/4 bg-ink-100/30 rounded" />
                </div>
                <div className="h-10 w-32 bg-ink-100/40 rounded-full mt-6" />
              </div>
            </div>
          </div>
        )}

        {/* Error State */}
        {!isLoading && isError && (
          <div className="reveal border border-ink-100/40 bg-canvas-50 rounded-soft p-8 text-center">
            <p className="text-sm md:text-base text-ink-400">
              {t.nav.courses === 'Kurslar'
                ? 'Kurslar ro‘yxatini yuklab bo‘lmadi. Iltimos, sahifani yangilang.'
                : t.nav.courses === 'Courses'
                ? 'Failed to load courses. Please refresh the page.'
                : 'Не удалось загрузить список курсов. Пожалуйста, обновите страницу.'}
            </p>
          </div>
        )}

        {/* Empty State */}
        {!isLoading && !isError && courses.length === 0 && (
          <div className="reveal border border-ink-100/40 bg-canvas-50 rounded-soft p-8 text-center">
            <p className="text-sm md:text-base text-ink-400">
              {t.nav.courses === 'Kurslar'
                ? 'Hozirda mavjud kurslar yo‘q.'
                : t.nav.courses === 'Courses'
                ? 'No courses available at the moment.'
                : 'В данный момент доступных курсов нет.'}
            </p>
          </div>
        )}

        {/* Data Loaded Successfully */}
        {!isLoading && !isError && courses.length > 0 && (
          <>
            {/* Featured course — compact editorial block */}
            {featured && (
              <div className="reveal mb-6 md:mb-10">
                <div className="grid grid-cols-12 gap-4 md:gap-6 items-stretch">
                  {/* Image */}
                  <div className="col-span-12 md:col-span-7">
                    <div className="relative overflow-hidden rounded-soft group h-full min-h-[180px] md:min-h-[260px]">
                      <img
                        src={IMAGES.courseFeatured}
                        alt={featured.title}
                        className="h-full w-full object-cover transition-transform duration-[1.2s] ease-smooth group-hover:scale-[1.04]"
                        loading="lazy"
                      />
                      <div className="absolute inset-0 bg-gradient-to-t from-ink-900/30 via-transparent to-transparent" />
                      <div className="absolute top-4 left-4">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-canvas-50/90 backdrop-blur-sm px-3 py-1 text-[11px] font-600 text-ink-600">
                          <Star className="h-3 w-3 fill-cobalt-500 text-cobalt-500" />
                          {cLocale.featured}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Featured content */}
                  <div className="col-span-12 md:col-span-5 flex flex-col justify-between border border-ink-100/40 rounded-soft p-5 md:p-7 bg-canvas-50">
                    <div>
                      <span className="eyebrow mb-2.5 block">{cLocale.featured}</span>
                      <h3 className="font-display font-700 text-ink-700 text-2xl md:text-3xl mb-1.5">
                        {featured.title}
                      </h3>
                      <p className="text-sm font-400 text-ink-300 mb-3">
                        {formatGrade(featured.classLevel)}
                      </p>
                      <p className="text-sm md:text-base text-ink-400 leading-relaxed mb-5">
                        {featured.description || featured.subject}
                      </p>
                    </div>
                    <div className="flex items-end justify-between gap-4">
                      <div>
                        <span className="font-display text-xl font-700 text-ink-700">
                          {formatPrice(featured.price)}
                        </span>
                        {featured.price > 0 && (
                          <span className="text-sm text-ink-300 font-400">
                            {cLocale.perMonth}
                          </span>
                        )}
                      </div>
                      <button
                        onClick={() => onCourseSelect(String(featured.course_id))}
                        className="group inline-flex items-center gap-1.5 rounded-full bg-cobalt-500 px-4 py-2.5 text-sm font-500 text-white transition-all duration-300 ease-smooth hover:bg-cobalt-600 hover:gap-2.5"
                      >
                        {cLocale.enroll}
                        <ArrowUpRight className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {/* Course rows — editorial list */}
            <div className="border-t border-ink-100/40">
              {rows.map((course, i) => {
                const isSelected = selectedCourse === String(course.course_id);
                const isFirst = i === 0;

                return (
                  <div
                    key={course.course_id}
                    className={`reveal group border-b border-ink-100/40 transition-colors duration-300 hover:bg-canvas-50 ${
                      isFirst ? 'border-t' : ''
                    }`}
                  >
                    <div className="grid grid-cols-12 gap-4 md:gap-6 items-center py-3 md:py-3.5 px-1 md:px-3">
                      {/* Number */}
                      <div className="col-span-2 md:col-span-1">
                        <span className="font-display text-xs font-600 text-ink-100">
                          {String(i + 1).padStart(2, '0')}
                        </span>
                      </div>

                      {/* Course name + grade */}
                      <div className="col-span-10 md:col-span-4">
                        <div className="flex items-center gap-2.5">
                          <h3 className="font-display text-base md:text-lg font-600 text-ink-600 transition-colors duration-300 group-hover:text-ink-700">
                            {course.title}
                          </h3>
                          {course.subject && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-cobalt-50 px-2 py-0.5 text-[10px] font-500 uppercase tracking-wide text-cobalt-600">
                              {course.subject}
                            </span>
                          )}
                        </div>
                        <p className="text-xs md:text-sm text-ink-300 mt-0.5">
                          {formatGrade(course.classLevel)}
                        </p>
                      </div>

                      {/* Description — hidden on small screens */}
                      <div className="hidden md:block col-span-4">
                        <p className="text-sm text-ink-400 leading-relaxed">
                          {course.description || course.subject}
                        </p>
                      </div>

                      {/* Price + button */}
                      <div className="col-span-12 md:col-span-3 flex items-center justify-between md:justify-end gap-4 mt-1.5 md:mt-0">
                        <div className="md:text-right">
                          <div className="font-display text-sm font-600 text-ink-600">
                            {formatPrice(course.price)}
                          </div>
                          {course.price > 0 && (
                            <div className="text-xs text-ink-200">
                              {cLocale.perMonth.trim()}
                            </div>
                          )}
                        </div>
                        <button
                          onClick={() => onCourseSelect(String(course.course_id))}
                          className={`inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-500 transition-all duration-300 ease-smooth ${
                            isSelected
                              ? 'border-cobalt-500 bg-cobalt-500 text-white'
                              : 'border-ink-100 text-ink-500 hover:border-ink-300 hover:text-ink-700'
                          }`}
                        >
                          {cLocale.enroll}
                          <ArrowUpRight className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </>
        )}
      </div>
    </section>
  );
}
