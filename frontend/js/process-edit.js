// Step rows for the process form: add / remove / reorder, then renumber so posted indexes stay 0..n-1.
const box = document.getElementById('steps');

function renumber() {
  box.querySelectorAll('[data-step]').forEach((row, i) => {
    row.querySelector('[data-num]').textContent = i + 1;
    row.querySelectorAll('[name^="steps["]').forEach(el => { el.name = el.name.replace(/^steps\[[^\]]*\]/, `steps[${i}]`); });
  });
}

box.addEventListener('click', e => {
  const act = e.target.dataset.act, row = e.target.closest('[data-step]');
  if (!act || !row) return;
  if (act === 'up' && row.previousElementSibling) row.previousElementSibling.before(row);
  if (act === 'down' && row.nextElementSibling) row.nextElementSibling.after(row);
  if (act === 'del' && box.children.length > 1 && confirm('Remove this step?')) row.remove();
  renumber();
});

box.addEventListener('change', e => {
  if (e.target.matches('[data-decision]')) e.target.closest('[data-step]').querySelector('[data-nogoto]').hidden = !e.target.checked;
});

document.getElementById('add-step').addEventListener('click', () => {
  box.append(document.getElementById('step-template').content.cloneNode(true));
  renumber();
  window.a11y?.(box.lastElementChild);
  box.lastElementChild.querySelector('input').focus();
});

renumber();
