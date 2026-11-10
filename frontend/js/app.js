// Mild motion only: gentle fade/rise on load. Skipped for users who prefer reduced motion.
if (window.gsap && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  gsap.from('[data-animate]', { opacity: 0, y: 12, duration: 0.5, stagger: 0.08, ease: 'power1.out', clearProps: 'all' });
}

// Accessibility fixes applied to the markup as rendered (keeps the PHP templates short).
let uid = 0;
window.a11y = (root = document) => {
  // Link every <label class="form-label"> to the field after it in the same group.
  root.querySelectorAll('.form-group > label:not([for])').forEach(l => {
    const f = l.parentElement.querySelector('input:not([type=hidden]),select,textarea');
    if (f && !l.contains(f)) { f.id ||= `f${++uid}`; l.htmlFor = f.id; }
  });
  // Fields with no label at all: use the placeholder, else the first column name.
  root.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(f => {
    if (!f.labels.length && !f.getAttribute('aria-label')) f.setAttribute('aria-label', f.placeholder || f.title || f.name.replace(/\W+/g, ' ').trim());
  });
  // Icon-only buttons (↑ ↓ ✕) are named by their tooltip.
  root.querySelectorAll('button[title]:not([aria-label])').forEach(b => b.setAttribute('aria-label', b.title));
  root.querySelectorAll('th:not([scope])').forEach(th => th.setAttribute('scope', 'col'));
  root.querySelectorAll('th:empty').forEach(th => { th.innerHTML = '<span class="sr">Actions</span>'; });   // blank header cells (download / action columns)
  root.querySelectorAll('.table-wrap').forEach(w => { w.tabIndex = 0; });   // wide tables can be scrolled with the keyboard
  root.querySelectorAll('.alert').forEach(a => a.setAttribute('role', a.classList.contains('error') ? 'alert' : 'status'));
};
a11y();
// Tell screen readers which menu item is the current page.
document.querySelectorAll('.topbar nav a').forEach(a => {
  if (new URL(a.href).pathname === location.pathname) a.setAttribute('aria-current', 'page');
});
