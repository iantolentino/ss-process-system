// Mild motion only: gentle fade/rise on load. Skipped for users who prefer reduced motion.
if (window.gsap && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  gsap.from('[data-animate]', { opacity: 0, y: 12, duration: 0.5, stagger: { amount: 0.4 }, ease: 'power1.out', clearProps: 'all' });
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
  root.querySelectorAll('.table-wrap, .org-wrap').forEach(w => { w.tabIndex = 0; });   // wide tables can be scrolled with the keyboard
  root.querySelectorAll('.alert').forEach(a => a.setAttribute('role', a.classList.contains('error') ? 'alert' : 'status'));
};
a11y();

// Mobile menu
const navbtn = document.getElementById('navbtn');
navbtn?.addEventListener('click', () => navbtn.setAttribute('aria-expanded', document.body.classList.toggle('nav-open')));
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { navbtn.click(); navbtn.focus(); } });

// Tabs: <div class="tabs" data-tabs> with buttons [data-tab=name] and panels [data-tabpanel=name]. Without JS every panel simply shows.
document.querySelectorAll('[data-tabs]').forEach(bar => {
  const btns = [...bar.querySelectorAll('[data-tab]')], panels = [...document.querySelectorAll('[data-tabpanel]')];
  const show = name => { btns.forEach(b => b.setAttribute('aria-selected', b.dataset.tab === name)); panels.forEach(p => { p.hidden = p.dataset.tabpanel !== name; }); };
  btns.forEach(b => b.addEventListener('click', () => { show(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab); }));
  show(btns.some(b => b.dataset.tab === location.hash.slice(1)) ? location.hash.slice(1) : btns[0].dataset.tab);
  bar.hidden = false;
});
