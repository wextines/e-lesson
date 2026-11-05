import { useState, useEffect, useCallback } from 'react';
import { Header } from '@/components/Header';
import { Hero } from '@/components/Hero';
import { WhySfera } from '@/components/WhySfera';
import { Courses } from '@/components/Courses';
import { Testimonials } from '@/components/Testimonials';
import { CTAForm } from '@/components/CTAForm';
import { Footer } from '@/components/Footer';
import { useScrollReveal } from '@/hooks';
import { translations, type Lang } from '@/i18n';
import type { LandingApiResponse, TenantData, ApiCourse } from '@/types';

function App() {
  const [lang, setLang] = useState<Lang>('ru');
  const [selectedCourse, setSelectedCourse] = useState('');
  const [tenant, setTenant] = useState<TenantData | null>(null);
  const [courses, setCourses] = useState<ApiCourse[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isError, setIsError] = useState<boolean>(false);

  const t = translations[lang];
  const ref = useScrollReveal<HTMLDivElement>();

  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    setIsError(false);

    // Fetch tenant landing data using the backend as the single source of truth
    fetch('/api/tenant-landing.php', {
      headers: {
        Accept: 'application/json',
      },
    })
      .then((res) => {
        if (!res.ok) {
          throw new Error(`HTTP error ${res.status}`);
        }
        return res.json();
      })
      .then((data: LandingApiResponse) => {
        if (isMounted) {
          if (data && data.success) {
            setTenant(data.tenant);
            setCourses(data.courses || []);

            // Align default language if provided by tenant
            if (
              data.tenant?.default_language &&
              ['ru', 'uz', 'en'].includes(data.tenant.default_language)
            ) {
              setLang(data.tenant.default_language as Lang);
            }
          } else {
            setIsError(true);
          }
          setIsLoading(false);
        }
      })
      .catch((err) => {
        console.error('[Landing API] Failed to fetch tenant landing data:', err);
        if (isMounted) {
          setIsError(true);
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const handleLangChange = useCallback((l: Lang) => {
    setLang(l);
    document.documentElement.lang = l;
  }, []);

  const scrollToContacts = useCallback(() => {
    document.getElementById('contacts')?.scrollIntoView({ behavior: 'smooth' });
  }, []);

  const scrollToCourses = useCallback(() => {
    document.getElementById('courses')?.scrollIntoView({ behavior: 'smooth' });
  }, []);

  const handleCourseSelect = useCallback((courseId: string) => {
    setSelectedCourse(courseId);
    // Defer scroll until after state update renders the form
    requestAnimationFrame(() => {
      document.getElementById('contacts')?.scrollIntoView({ behavior: 'smooth' });
    });
  }, []);

  return (
    <div ref={ref} className="min-h-screen bg-canvas-100 grain">
      <Header
        t={t}
        lang={lang}
        tenant={tenant}
        onLangChange={handleLangChange}
        onContactClick={scrollToContacts}
      />
      <main>
        <Hero
          t={t}
          onContactClick={scrollToContacts}
          onCoursesClick={scrollToCourses}
        />
        <WhySfera t={t} />
        <Courses
          t={t}
          courses={courses}
          isLoading={isLoading}
          isError={isError}
          selectedCourse={selectedCourse}
          onCourseSelect={handleCourseSelect}
        />
        <Testimonials t={t} />
        <CTAForm
          t={t}
          courses={courses}
          selectedCourse={selectedCourse}
          onCourseChange={setSelectedCourse}
        />
      </main>
      <Footer t={t} tenant={tenant} />
    </div>
  );
}

export default App;
