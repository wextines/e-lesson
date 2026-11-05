import { useState, useEffect, type FormEvent } from 'react';
import { ArrowRight, Check, Loader2, AlertCircle } from 'lucide-react';
import type { Translation } from '@/i18n';
import type { ApiCourse } from '@/types';

interface CTAFormProps {
  t: Translation;
  courses: ApiCourse[];
  selectedCourse: string;
  onCourseChange: (courseId: string) => void;
}

interface FormErrors {
  name?: string;
  phone?: string;
  course?: string;
}

export function CTAForm({ t, courses, selectedCourse, onCourseChange }: CTAFormProps) {
  const { cta } = t;
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [course, setCourse] = useState(selectedCourse);
  const [errors, setErrors] = useState<FormErrors>({});
  const [status, setStatus] = useState<'idle' | 'loading' | 'success'>('idle');

  useEffect(() => {
    setCourse(selectedCourse);
  }, [selectedCourse]);

  const validate = (): boolean => {
    const e: FormErrors = {};
    if (!name.trim()) e.name = cta.errName;
    const phoneDigits = phone.replace(/\D/g, '');
    if (phoneDigits.length < 9) e.phone = cta.errPhone;
    if (!course) e.course = cta.errCourse;
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev: FormEvent) => {
    ev.preventDefault();
    if (!validate()) return;
    setStatus('loading');
    await new Promise((r) => setTimeout(r, 1400));
    setStatus('success');
  };

  const reset = () => {
    setName('');
    setPhone('');
    setCourse('');
    onCourseChange('');
    setErrors({});
    setStatus('idle');
  };

  const inputBase =
    'w-full rounded-soft border bg-ink-900/30 px-4 py-2.5 text-canvas-50 placeholder:text-canvas-50/25 outline-none transition-all duration-300';

  return (
    <section
      id="contacts"
      className="relative py-10 md:py-14 bg-ink-800 text-canvas-50 overflow-hidden"
    >
      <div className="container-edge relative">
        <div className="grid grid-cols-12 gap-6 md:gap-10">
          {/* Left: text */}
          <div className="col-span-12 lg:col-span-5 reveal">
            <span className="eyebrow-light mb-4 block">{cta.eyebrow}</span>
            <h2 className="font-display font-600 text-display-lg text-balance mb-4">
              {cta.title}
            </h2>
            <p className="text-sm md:text-base text-canvas-50/60 max-w-md leading-relaxed">{cta.text}</p>

            {/* Trust indicators */}
            <div className="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm text-canvas-50/40">
              <span className="flex items-center gap-2">
                <Check className="h-3.5 w-3.5 text-cobalt-400" strokeWidth={2.5} />
                100% free
              </span>
              <span className="flex items-center gap-2">
                <Check className="h-3.5 w-3.5 text-cobalt-400" strokeWidth={2.5} />
                No obligation
              </span>
              <span className="flex items-center gap-2">
                <Check className="h-3.5 w-3.5 text-cobalt-400" strokeWidth={2.5} />
                Reply within 24h
              </span>
            </div>
          </div>

          {/* Right: form */}
          <div className="col-span-12 lg:col-span-6 lg:col-start-7 reveal reveal-delay-1">
            {status === 'success' ? (
              <div className="flex flex-col items-center justify-center text-center py-8 animate-scale-in rounded-soft border border-canvas-50/8 bg-ink-700/20">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-cobalt-500 mb-4">
                  <Check className="h-6 w-6 text-white" strokeWidth={2.5} />
                </div>
                <h3 className="font-display text-lg font-600 text-canvas-50 mb-2">
                  {cta.success}
                </h3>
                <p className="text-canvas-50/50 max-w-sm mb-5 text-sm">{cta.successText}</p>
                <button
                  onClick={reset}
                  className="text-sm font-500 text-cobalt-300 hover:text-cobalt-200 transition-colors"
                >
                  {cta.closeSuccess}
                </button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} noValidate className="space-y-3.5">
                {/* Name */}
                <div>
                  <label htmlFor="cta-name" className="block text-xs font-400 text-canvas-50/50 mb-1.5">
                    {cta.name}
                  </label>
                  <input
                    id="cta-name"
                    type="text"
                    value={name}
                    onChange={(e) => {
                      setName(e.target.value);
                      if (errors.name) setErrors((p) => ({ ...p, name: undefined }));
                    }}
                    placeholder={cta.namePlaceholder}
                    className={`${inputBase} ${
                      errors.name
                        ? 'border-error/50 focus:border-error'
                        : 'border-canvas-50/10 focus:border-canvas-50/30'
                    }`}
                  />
                  {errors.name && (
                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-error">
                      <AlertCircle className="h-3.5 w-3.5" />
                      {errors.name}
                    </p>
                  )}
                </div>

                {/* Phone */}
                <div>
                  <label htmlFor="cta-phone" className="block text-xs font-400 text-canvas-50/50 mb-1.5">
                    {cta.phone}
                  </label>
                  <div className="flex items-stretch">
                    <span className="inline-flex items-center rounded-l-soft border border-r-0 border-canvas-50/10 bg-ink-900/30 px-3.5 text-canvas-50/40 text-sm">
                      +998
                    </span>
                    <input
                      id="cta-phone"
                      type="tel"
                      value={phone}
                      onChange={(e) => {
                        setPhone(e.target.value);
                        if (errors.phone) setErrors((p) => ({ ...p, phone: undefined }));
                      }}
                      placeholder={cta.phonePlaceholder}
                      className={`flex-1 rounded-r-soft border bg-ink-900/30 px-4 py-2.5 text-canvas-50 placeholder:text-canvas-50/25 outline-none transition-all duration-300 ${
                        errors.phone
                          ? 'border-error/50 focus:border-error'
                          : 'border-canvas-50/10 focus:border-canvas-50/30'
                      }`}
                    />
                  </div>
                  {errors.phone && (
                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-error">
                      <AlertCircle className="h-3.5 w-3.5" />
                      {errors.phone}
                    </p>
                  )}
                </div>

                {/* Course select */}
                <div>
                  <label htmlFor="cta-course" className="block text-xs font-400 text-canvas-50/50 mb-1.5">
                    {cta.course}
                  </label>
                  <select
                    id="cta-course"
                    value={course}
                    onChange={(e) => {
                      setCourse(e.target.value);
                      onCourseChange(e.target.value);
                      if (errors.course) setErrors((p) => ({ ...p, course: undefined }));
                    }}
                    className={`w-full rounded-soft border bg-ink-900/30 px-4 py-2.5 text-canvas-50 outline-none transition-all duration-300 ${
                      course ? '' : 'text-canvas-50/25'
                    } ${
                      errors.course
                        ? 'border-error/50 focus:border-error'
                        : 'border-canvas-50/10 focus:border-canvas-50/30'
                    }`}
                  >
                    <option value="" disabled className="bg-ink-700 text-canvas-50/40">
                      {cta.selectCourse}
                    </option>
                    {courses.map((c) => (
                      <option key={c.course_id} value={String(c.course_id)} className="bg-ink-700 text-canvas-50">
                        {c.title} {c.classLevel ? `(${c.classLevel} класс)` : ''}
                      </option>
                    ))}
                  </select>
                  {errors.course && (
                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-error">
                      <AlertCircle className="h-3.5 w-3.5" />
                      {errors.course}
                    </p>
                  )}
                </div>

                {/* Submit */}
                <button
                  type="submit"
                  disabled={status === 'loading'}
                  className="group flex w-full items-center justify-center gap-2 rounded-full bg-cobalt-500 px-6 py-3 text-sm font-600 text-white transition-all duration-300 ease-smooth hover:bg-cobalt-600 hover:gap-3 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                  {status === 'loading' ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" />
                      {cta.submitting}
                    </>
                  ) : (
                    <>
                      {cta.button}
                      <ArrowRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5" />
                    </>
                  )}
                </button>
              </form>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}
