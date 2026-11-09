// Mild motion only: gentle fade/rise on load. Skipped for users who prefer reduced motion.
if (window.gsap && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  gsap.from('[data-animate]', { opacity: 0, y: 12, duration: 0.5, stagger: 0.08, ease: 'power1.out', clearProps: 'all' });
}
