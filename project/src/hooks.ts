import { useEffect, useRef, useState } from 'react';

/**
 * Adds the `is-visible` class to elements with the `reveal` class
 * when they scroll into view. Supports dynamically loaded content via MutationObserver.
 */
export function useScrollReveal<T extends HTMLElement = HTMLDivElement>() {
  const ref = useRef<T>(null);

  useEffect(() => {
    const root = ref.current ?? document;
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.05, rootMargin: '0px 0px -40px 0px' },
    );

    const observeAll = () => {
      const els = root.querySelectorAll<HTMLElement>('.reveal:not(.is-visible)');
      els.forEach((el) => observer.observe(el));
    };

    observeAll();

    const mutationObserver = new MutationObserver(() => {
      observeAll();
    });

    if (ref.current) {
      mutationObserver.observe(ref.current, { childList: true, subtree: true });
    }

    return () => {
      observer.disconnect();
      mutationObserver.disconnect();
    };
  }, []);

  return ref;
}

/** Tracks whether the page has been scrolled past a threshold. */
export function useScrolled(threshold = 20) {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > threshold);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, [threshold]);

  return scrolled;
}
