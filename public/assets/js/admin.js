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
      // setRangeText doesn't fire "input"; the live preview listens for it.
      area.dispatchEvent(new Event('input', { bubbles: true }));
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

(() => {
  'use strict';

  // Analytics charts: a crosshair and tooltip that snap to the nearest day. The same numbers are
  // in the "Daily figures as a table" view, so nothing depends on this.
  document.querySelectorAll('[data-chart]').forEach((figure) => {
    const svg = figure.querySelector('svg');
    const tip = figure.querySelector('.chart-tip');
    const crosshair = svg.querySelector('.chart-crosshair');
    const dot = svg.querySelector('.chart-dot');
    const points = [...svg.querySelectorAll('.chart-point')].map((el) => ({
      x: Number(el.getAttribute('cx')), y: Number(el.getAttribute('cy')), label: el.dataset.label, value: el.dataset.value,
    }));
    if (points.length === 0) return;
    const viewWidth = svg.viewBox.baseVal.width;
    const viewHeight = svg.viewBox.baseVal.height;

    const hide = () => { tip.hidden = true; crosshair.setAttribute('hidden', ''); dot.setAttribute('hidden', ''); };
    svg.addEventListener('pointermove', (event) => {
      const box = svg.getBoundingClientRect();
      const x = ((event.clientX - box.left) / box.width) * viewWidth;
      const point = points.reduce((best, p) => (Math.abs(p.x - x) < Math.abs(best.x - x) ? p : best));
      crosshair.setAttribute('x1', point.x);
      crosshair.setAttribute('x2', point.x);
      crosshair.removeAttribute('hidden');
      dot.setAttribute('cx', point.x);
      dot.setAttribute('cy', point.y);
      dot.removeAttribute('hidden');
      tip.querySelector('strong').textContent = point.value;
      tip.querySelector('span').textContent = point.label;
      tip.hidden = false;
      const left = (point.x / viewWidth) * box.width;
      tip.style.left = `${Math.min(Math.max(left, 60), box.width - 60)}px`;
      tip.style.top = `${(point.y / viewHeight) * box.height}px`;
    });
    svg.addEventListener('pointerleave', hide);
  });
})();

(() => {
  'use strict';

  // Live newsletter preview: copies the editor's fields into a hidden form that posts to the
  // preview endpoint with the frame as its target, so the email renders in the sandboxed frame
  // under its own locked-down CSP. Refreshes shortly after typing stops.
  const frame = document.querySelector('[data-live-preview]');
  const editor = document.querySelector('form.newsletter-form');
  if (!frame || !editor) return;

  const FIELDS = ['_token', 'subject', 'preheader', 'body_html'];
  const poster = document.createElement('form');
  poster.method = 'post';
  poster.action = '/admin/newsletters/preview';
  poster.target = frame.name;
  poster.hidden = true;
  FIELDS.forEach((name) => {
    const field = document.createElement('textarea');
    field.name = name;
    poster.append(field);
  });
  document.body.append(poster);

  const refresh = () => {
    FIELDS.forEach((name) => { poster.elements[name].value = editor.elements[name]?.value ?? ''; });
    poster.submit();
  };
  let timer;
  editor.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(refresh, 700);
  });

  const panel = document.querySelector('[data-live-preview-panel]');
  panel.hidden = false;
  panel.querySelectorAll('[data-preview-width]').forEach((button) => {
    button.addEventListener('click', () => {
      frame.style.width = button.dataset.previewWidth;
      panel.querySelectorAll('[data-preview-width]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
    });
  });
  refresh();
})();

(() => {
  'use strict';

  // Company picker on the job form: type to search, pick with mouse or keyboard. The visible
  // input posts company_name; the hidden company_id is set only when a listed company is chosen.
  const picker = document.querySelector('[data-company-picker]');
  if (!picker) return;
  const input = picker.querySelector('input[role="combobox"]');
  const hidden = picker.querySelector('input[name="company_id"]');
  const list = picker.querySelector('[role="listbox"]');
  const status = picker.parentElement.querySelector('.combo-status');
  let results = [];
  let active = -1;
  let timer = null;
  let request = 0;

  const close = () => {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    active = -1;
  };

  const choose = (company) => {
    input.value = company.name;
    hidden.value = String(company.id);
    status.textContent = '';
    close();
  };

  const highlight = (index) => {
    active = index;
    list.querySelectorAll('[role="option"]').forEach((option, i) => {
      option.setAttribute('aria-selected', i === index ? 'true' : 'false');
      if (i === index) {
        input.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
      }
    });
  };

  const render = () => {
    list.replaceChildren(...results.map((company) => {
      const option = document.createElement('li');
      option.id = `companyOption${company.id}`;
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      if (company.logoUrl) {
        const img = document.createElement('img');
        img.src = company.logoUrl;
        img.alt = '';
        img.width = 24;
        img.height = 24;
        option.append(img);
      } else {
        const blank = document.createElement('span');
        blank.className = 'combo-nologo';
        option.append(blank);
      }
      option.append(document.createTextNode(company.name));
      option.addEventListener('mousedown', (event) => { event.preventDefault(); choose(company); });
      return option;
    }));
    const open = results.length > 0 && document.activeElement === input;
    list.hidden = !open;
    input.setAttribute('aria-expanded', open ? 'true' : 'false');
    active = -1;
  };

  const updateStatus = () => {
    const typed = input.value.trim();
    const exact = results.find((c) => c.name.toLowerCase() === typed.toLowerCase());
    if (exact && hidden.value === '') hidden.value = String(exact.id);
    status.textContent = typed !== '' && hidden.value === ''
      ? `No company called “${typed}” yet. Use + to add it.`
      : '';
  };

  const search = async () => {
    const mine = ++request;
    try {
      const url = `${picker.dataset.searchUrl}?q=${encodeURIComponent(input.value.trim())}`;
      const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok || mine !== request) return;
      results = (await response.json()).companies || [];
      render();
      updateStatus();
    } catch (error) {
      // Leave the field as plain text; the server matches the typed name on save.
    }
  };

  input.addEventListener('input', () => {
    hidden.value = '';
    clearTimeout(timer);
    timer = setTimeout(search, 200);
  });
  input.addEventListener('focus', search);
  input.addEventListener('blur', () => { close(); updateStatus(); });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (list.hidden) { search(); return; }
      const step = event.key === 'ArrowDown' ? 1 : -1;
      highlight((active + step + results.length) % results.length);
    } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
      event.preventDefault();
      choose(results[active]);
    } else if (event.key === 'Escape' && !list.hidden) {
      event.preventDefault();
      close();
    }
  });

  // "+" opens the add-company dialog; without JavaScript it is a link to the full form.
  const dialog = document.getElementById('companyDialog');
  const opener = picker.querySelector('[data-open-company-dialog]');
  if (!dialog || !opener || typeof dialog.showModal !== 'function') return;
  const form = dialog.querySelector('[data-company-form]');
  const formError = form.querySelector('[data-form-error]');

  const clearErrors = () => {
    formError.hidden = true;
    form.querySelectorAll('[data-field]').forEach((field) => {
      field.classList.remove('has-error');
      const message = field.querySelector('.field-error');
      message.hidden = true;
      message.textContent = '';
    });
  };

  const showError = (text) => {
    formError.textContent = text;
    formError.hidden = false;
  };

  opener.addEventListener('click', (event) => {
    event.preventDefault();
    form.reset();
    clearErrors();
    if (hidden.value === '') form.elements.name.value = input.value.trim();
    dialog.showModal();
    form.elements.name.focus();
  });
  dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => dialog.close()));

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();
    const submit = form.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data = await response.json().catch(() => ({}));
      if (response.status === 201 && data.company) {
        choose(data.company);
        dialog.close();
        opener.focus();
        return;
      }
      let shown = false;
      Object.entries(data.errors || {}).forEach(([name, text]) => {
        const field = form.querySelector(`[data-field="${name}"]`);
        if (!field) return;
        field.classList.add('has-error');
        const message = field.querySelector('.field-error');
        message.textContent = text;
        message.hidden = false;
        shown = true;
      });
      if (!shown) {
        showError(response.status === 419
          ? 'Your session expired. Reload the page and try again.'
          : 'The company could not be saved. Please try again.');
      }
    } catch (error) {
      showError('The company could not be saved. Check your connection and try again.');
    } finally {
      submit.disabled = false;
    }
  });
})();

(() => {
  'use strict';

  // Live character counts: <input data-count-ideal="155"> shows "120 / 155" and warns past the ideal length.
  document.querySelectorAll('[data-count-ideal]').forEach((field) => {
    const ideal = Number(field.dataset.countIdeal);
    const count = document.createElement('span');
    count.className = 'char-count';
    count.setAttribute('aria-live', 'polite');
    field.insertAdjacentElement('afterend', count);
    const update = () => {
      const length = field.value.trim().length;
      count.textContent = `${length} / ${ideal}`;
      count.classList.toggle('over', length > ideal);
    };
    field.addEventListener('input', update);
    update();
  });
})();

(() => {
  'use strict';

  // Blog editor: Quill (public/assets/vendor/quill) edits the article; its HTML is copied into the
  // textarea on submit. Without JavaScript, or if Quill fails to load, the textarea takes plain HTML.
  const holder = document.querySelector('[data-rich-editor]');
  if (!holder || typeof window.Quill !== 'function') return;
  const textarea = document.getElementById(holder.dataset.richEditor);
  const form = textarea.form;

  holder.hidden = false;
  textarea.hidden = true;
  textarea.required = false;
  const quill = new window.Quill(holder, {
    theme: 'snow',
    placeholder: 'Write the article…',
    modules: {
      // Only formats the server-side sanitiser keeps: headings, emphasis, links, lists and quotes.
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        ['link', 'blockquote'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['clean'],
      ],
    },
    formats: ['header', 'bold', 'italic', 'underline', 'link', 'blockquote', 'list'],
  });
  if (textarea.value.trim() !== '') {
    quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
  }

  form.addEventListener('submit', () => {
    // Apply edits Quill hasn't processed yet, so a word typed just before "Save" isn't lost.
    quill.update('user');
    // Quill 2 writes every space as &nbsp; in its semantic HTML; plain spaces let text wrap normally.
    const html = quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    textarea.value = quill.getText().trim() === '' ? '' : html;
  });
})();
