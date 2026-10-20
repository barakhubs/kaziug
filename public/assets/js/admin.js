/*
 * Back-office progressive enhancement. Every action also works without JavaScript.
 */
(() => {
  'use strict';

  // Ask before destructive form submissions: <form data-confirm="Delete this?">.
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = event.submitter;
    const message = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
  });
})();

(() => {
  'use strict';

  // "Select all" checkbox for bulk actions: <input type="checkbox" data-check-all="ids[]">.
  document.querySelectorAll('[data-check-all]').forEach((master) => {
    master.addEventListener('change', () => {
      document.querySelectorAll(`input[type="checkbox"][name="${master.dataset.checkAll}"]`)
        .forEach((box) => { box.checked = master.checked; });
    });
  });
})();

(() => {
  'use strict';

  // Minimal formatting toolbar for the newsletter body (a plain textarea of HTML).
  // It only wraps the selection in tags; the server sanitises whatever is saved.
  document.querySelectorAll('[data-editor]').forEach((toolbar) => {
    const area = document.getElementById(toolbar.dataset.editor);
    if (!area) return;
    toolbar.hidden = false;

    const escapeAttr = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const replaceSelection = (before, inner, after) => {
      const { selectionStart: start, selectionEnd: end, value } = area;
      const text = inner(value.slice(start, end));
      area.setRangeText(before + text + after, start, end, 'end');
      area.focus();
    };

    toolbar.addEventListener('click', (event) => {
      const button = event.target.closest('button');
      if (!button) return;
      if (button.dataset.wrap) {
        const tag = button.dataset.wrap;
        replaceSelection(`<${tag}>`, (s) => s || 'text', `</${tag}>`);
      } else if (button.dataset.list) {
        const tag = button.dataset.list;
        replaceSelection(`<${tag}>\n`, (s) => (s || 'Item').split(/\r?\n/).filter(Boolean)
          .map((line) => `  <li>${line.trim()}</li>`).join('\n'), `\n</${tag}>`);
      } else if ('link' in button.dataset) {
        const url = window.prompt('Link address (https://… or mailto:…)', 'https://');
        if (!url || !/^(https?:\/\/|mailto:)/i.test(url.trim())) return;
        replaceSelection(`<a href="${escapeAttr(url.trim())}">`, (s) => s || url.trim(), '</a>');
      } else if (button.dataset.insert) {
        replaceSelection('', () => button.dataset.insert, '\n');
      }
    });
  });
})();

(() => {
  'use strict';

  // Selects marked data-autosubmit (rows per page, filters) apply immediately.
  document.addEventListener('change', (event) => {
    const field = event.target.closest('[data-autosubmit]');
    if (field && field.form) field.form.requestSubmit();
  });

  // "How to apply": show only the fields for the chosen method.
  document.querySelectorAll('[data-apply-method]').forEach((group) => {
    const sync = () => {
      const chosen = group.querySelector('input[name="apply_method"]:checked')?.value || '';
      group.querySelectorAll('[data-apply-field]').forEach((field) => {
        const kind = field.dataset.applyField;
        field.hidden = !(chosen === 'both' || chosen === kind);
      });
    };
    group.addEventListener('change', sync);
    sync();
  });
})();
