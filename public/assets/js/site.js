/*
 * Public-site progressive enhancement. Every page works without it; this adds:
 *  - saved jobs in localStorage (same key as the original prototype),
 *  - filters that apply as soon as they change,
 *  - the desktop detail pane (cards open in the pane instead of the full page),
 *  - small form helpers (subscribe form timing).
 */
(() => {
  'use strict';

  const SAVED_KEY = 'kazi-saved-jobs';
  const WIDE = window.matchMedia('(min-width: 1101px)');

  /* ---------- Saved jobs ---------- */
  function loadSaved() {
    try {
      const list = JSON.parse(localStorage.getItem(SAVED_KEY)) || [];
      // Only numeric ids are server jobs; older prototype ids ("j01") are dropped.
      return new Set(list.map(String).filter((id) => /^\d+$/.test(id)));
    } catch { return new Set(); }
  }
  let saved = loadSaved();
  function persist() {
    try { localStorage.setItem(SAVED_KEY, JSON.stringify([...saved])); } catch { /* storage blocked */ }
  }

  function savedUrl() {
    return saved.size ? `/saved?ids=${[...saved].join(',')}` : '/saved';
  }

  function renderSaved() {
    const count = document.getElementById('savedCount');
    if (count) count.textContent = String(saved.size);
    const link = document.getElementById('savedLink');
    if (link) {
      link.href = savedUrl();
      link.classList.toggle('active', location.pathname === '/saved');
    }
    document.querySelectorAll('[data-save]').forEach((button) => {
      const on = saved.has(button.dataset.save);
      button.hidden = false;
      button.setAttribute('aria-pressed', String(on));
      if (button.classList.contains('save')) {
        button.classList.toggle('on', on);
        button.textContent = on ? '★' : '☆';
        const title = button.closest('.card')?.querySelector('.title')?.textContent?.trim() || 'job';
        button.setAttribute('aria-label', `${on ? 'Unsave' : 'Save'} ${title}`);
      } else {
        button.textContent = on ? '★ Saved' : '☆ Save job';
      }
    });
  }

  function toggleSave(id) {
    if (saved.has(id)) {
      saved.delete(id);
      toast('Removed from saved jobs');
      if (document.querySelector('[data-saved-page]')) {
        document.querySelector(`.card [data-save="${CSS.escape(id)}"]`)?.closest('.card')?.remove();
        history.replaceState(null, '', savedUrl());
      }
    } else {
      saved.add(id);
      toast('Job saved');
    }
    persist();
    renderSaved();
  }

  /* ---------- Toast ---------- */
  let toastTimer;
  function toast(message) {
    const el = document.querySelector('.toast');
    if (!el) return;
    el.textContent = message;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2600);
  }

  /* ---------- Share / copy link ---------- */
  // Phones get the system share sheet (WhatsApp, SMS…); everything else copies the link.
  const TOUCH = window.matchMedia('(pointer: coarse)');

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      // Clipboard API needs HTTPS and permission; fall back to a temporary text field.
      const field = document.createElement('textarea');
      field.value = text;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.append(field);
      field.select();
      const ok = document.execCommand('copy');
      field.remove();
      return ok;
    }
  }

  async function shareJob(button) {
    const url = button.dataset.shareUrl;
    const title = button.dataset.shareTitle || document.title;
    if (TOUCH.matches && navigator.share) {
      try {
        await navigator.share({ title, url });
        return;
      } catch (error) {
        if (error && error.name === 'AbortError') return;
      }
    }
    toast(await copyText(url) ? 'Link copied' : url);
  }

  document.querySelectorAll('[data-share-url]').forEach((button) => { button.hidden = false; });

  /* ---------- Wiring ---------- */
  document.addEventListener('click', (event) => {
    const shareButton = event.target.closest('[data-share-url]');
    if (shareButton) {
      event.preventDefault();
      shareJob(shareButton);
      return;
    }

    const saveButton = event.target.closest('[data-save]');
    if (saveButton) {
      event.preventDefault();
      toggleSave(saveButton.dataset.save);
      return;
    }

    // On wide screens a card opens in the detail pane; links inside the card still work normally.
    const card = event.target.closest('.card[data-pane-url]');
    if (card && WIDE.matches && !document.querySelector('[data-saved-page]')) {
      if (event.target.closest('a') && !event.target.closest('.title')) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1) return;
      event.preventDefault();
      location.assign(card.dataset.paneUrl);
    }
  });

  /* ---------- Mobile filter panel ---------- */
  const PHONE = window.matchMedia('(max-width: 760px)');
  const panel = document.getElementById('filters');
  const toggle = document.querySelector('.filters-toggle');
  const backdrop = document.querySelector('.filters-backdrop');

  function openFilters() {
    if (!panel || !toggle) return;
    panel.classList.add('open');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'filters-title');
    if (backdrop) backdrop.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('filters-open');
    panel.querySelector('[data-filters-close]')?.focus();
  }

  function closeFilters(returnFocus = true) {
    if (!panel || !panel.classList.contains('open')) return;
    panel.classList.remove('open');
    panel.removeAttribute('role');
    panel.removeAttribute('aria-modal');
    if (backdrop) backdrop.hidden = true;
    toggle?.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('filters-open');
    if (returnFocus) toggle?.focus();
  }

  toggle?.addEventListener('click', openFilters);
  document.querySelectorAll('[data-filters-close]').forEach((el) => el.addEventListener('click', () => closeFilters()));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeFilters();
  });
  // Rotating to a wider screen puts the filters back in the page.
  PHONE.addEventListener('change', (event) => { if (!event.matches) closeFilters(false); });

  // Filters and sort apply immediately; the "Apply filters" button stays for typed salary values.
  // Inside the phone panel, choices wait for "Show results" so the panel doesn't reload on every tap.
  document.addEventListener('change', (event) => {
    const field = event.target.closest('[data-autosubmit]');
    if (!field) return;
    if (PHONE.matches && field.closest('.filters')) return;
    const form = field.form;
    if (!form) return;
    const page = form.querySelector('input[name="page"]');
    if (page) page.remove();
    form.requestSubmit();
  });

  // Drop empty fields so result URLs stay short and readable.
  const searchForm = document.getElementById('jobsearch');
  if (searchForm) {
    searchForm.addEventListener('formdata', (event) => {
      for (const [key, value] of [...event.formData.entries()]) {
        if (value === '' || (key === 'sort' && value === 'relevance')) event.formData.delete(key);
      }
    });
  }

  // The saved page needs the ids from this browser.
  if (document.querySelector('[data-saved-page]') && !new URLSearchParams(location.search).has('ids') && saved.size) {
    location.replace(savedUrl());
    return;
  }

  // Keep the selected card in view after the pane reloads.
  document.querySelector('.card.active')?.scrollIntoView({ block: 'nearest' });

  renderSaved();
})();

/*
 * Marketing popup (home and job pages only): opens once per visitor. The browser remembers the
 * popup's id and version in localStorage; nothing is sent to the server. If storage is blocked we
 * can't remember, so the popup stays closed rather than reappearing on every page.
 */
(() => {
  'use strict';

  const SEEN_KEY = 'kazi-popup-seen';
  const dialog = document.getElementById('promo');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const key = dialog.dataset.promoKey;

  try {
    if (localStorage.getItem(SEEN_KEY) === key) return;
    localStorage.setItem('kazi-storage-test', '1');
    localStorage.removeItem('kazi-storage-test');
  } catch { return; }

  // On phones it slides up from the bottom and leaves the page usable (Google demotes pages
  // whose content is covered by a popup on mobile); on larger screens it is a centred modal.
  const phone = window.matchMedia('(max-width: 640px)').matches;
  const hasMouse = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  let opened = false;
  let returnFocus = null;

  function open() {
    if (opened) return;
    opened = true;
    try { localStorage.setItem(SEEN_KEY, key); } catch { /* shown once this visit at least */ }
    returnFocus = document.activeElement;
    dialog.classList.toggle('promo-sheet', phone);
    if (phone) { dialog.show(); } else { dialog.showModal(); }
    dialog.querySelector('.promo-close').focus();
  }

  function close() {
    if (dialog.open) dialog.close();
  }

  dialog.addEventListener('close', () => { if (returnFocus instanceof HTMLElement) returnFocus.focus(); });
  // Buttons close it; the call-to-action link also closes it and then follows its href as usual.
  dialog.querySelectorAll('[data-promo-close]').forEach((el) => el.addEventListener('click', close));
  // A click on the dimmed backdrop (the dialog element itself, outside its content) closes the modal.
  dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
  // A non-modal dialog (phones) doesn't close on Escape by itself.
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && dialog.open) close(); });

  const delay = Math.max(0, Number(dialog.dataset.delay) || 0) * 1000;
  const trigger = dialog.dataset.trigger;

  if (trigger === 'scroll') {
    const check = () => {
      const scrollable = document.documentElement.scrollHeight - window.innerHeight;
      // Short pages can't be scrolled halfway: open after the delay instead.
      if (scrollable <= 0) { window.removeEventListener('scroll', check); setTimeout(open, delay); return; }
      if (window.scrollY / scrollable >= 0.5) { window.removeEventListener('scroll', check); open(); }
    };
    window.addEventListener('scroll', check, { passive: true });
    check();
  } else if (trigger === 'exit' && hasMouse) {
    // Exit intent: the pointer leaves through the top of the window (towards the tabs or address bar).
    // Ignored for the first few seconds so it doesn't fire while the visitor is still arriving.
    const armedAt = Date.now() + 3000;
    const onLeave = (event) => {
      if (event.relatedTarget === null && event.clientY <= 0 && Date.now() >= armedAt) {
        document.removeEventListener('mouseout', onLeave);
        open();
      }
    };
    document.addEventListener('mouseout', onLeave);
  } else {
    // "delay", and "exit" on touch devices, which have no pointer to leave the window.
    setTimeout(open, delay);
  }
})();
