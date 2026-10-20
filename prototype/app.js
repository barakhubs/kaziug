(() => {
  'use strict';

  const PAGE_SIZE = 8;
  // Reads jobs saved by the back office in this browser, falling back to the bundled sample data.
  function loadJobs() {
    try {
      const raw = localStorage.getItem('kazi-jobs-v1');
      if (raw !== null) return JSON.parse(raw);
    } catch { /* fall through to bundled data */ }
    return window.JOBS || [];
  }
  const JOBS = loadJobs().filter((j) => j.published !== false);
  const SAVED_KEY = 'kazi-saved-jobs';

  const state = {
    q: '',
    loc: '',
    types: new Set(),
    category: '',
    exp: '',
    days: '',
    remote: false,
    sort: 'relevance',
    page: 1,
    selected: null,
    savedOnly: false,
  };

  const $ = (sel) => document.querySelector(sel);
  const els = {
    form: $('#searchForm'),
    q: $('#q'),
    loc: $('#locInput'),
    locList: $('#locations'),
    types: $('#typeFilters'),
    category: $('#categoryFilter'),
    exp: $('#expFilter'),
    date: $('#dateFilter'),
    remote: $('#remoteOnly'),
    sort: $('#sortSelect'),
    clear: $('#clearFilters'),
    summary: $('#resultsSummary'),
    list: $('#jobList'),
    pager: $('#pager'),
    detail: $('#detail'),
    savedLink: $('#savedLink'),
    savedCount: $('#savedCount'),
  };

  /* ---------- Saved jobs (localStorage) ---------- */
  function loadSaved() {
    try { return JSON.parse(localStorage.getItem(SAVED_KEY)) || []; } catch { return []; }
  }
  function persistSaved() {
    try { localStorage.setItem(SAVED_KEY, JSON.stringify([...saved])); } catch { /* storage unavailable */ }
  }
  let saved = new Set(loadSaved());

  /* ---------- Formatting helpers ---------- */
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  const ugx = (n) => (n >= 1e6 ? `UGX ${+(n / 1e6).toFixed(1)}M` : `UGX ${n.toLocaleString('en-US')}`);

  const salaryText = (j) => {
    if (!j.salaryMin) return 'Salary not disclosed';
    const range = j.salaryMax && j.salaryMax !== j.salaryMin ? ` – ${ugx(j.salaryMax)}` : '';
    return `${ugx(j.salaryMin)}${range} / month`;
  };

  const postedText = (d) => (d === 0 ? 'Posted today' : d === 1 ? 'Posted 1 day ago' : `Posted ${d} days ago`);
  const locationText = (j) => `${j.location}${j.remote ? ' · Remote' : ''}`;

  /* ---------- Matching and sorting ---------- */
  const terms = (s) => s.toLowerCase().split(/\s+/).filter(Boolean);
  const haystack = (j) =>
    [j.title, j.company, j.category, j.type, j.location, j.summary, ...j.skills].join(' ').toLowerCase();

  function matches(j) {
    if (state.savedOnly && !saved.has(j.id)) return false;

    const hay = haystack(j);
    if (terms(state.q).some((t) => !hay.includes(t))) return false;

    const loc = state.loc.trim().toLowerCase();
    if (loc === 'remote') {
      if (!j.remote) return false;
    } else if (loc && !j.location.toLowerCase().includes(loc)) {
      return false;
    }

    if (state.remote && !j.remote) return false;
    if (state.types.size && !state.types.has(j.type)) return false;
    if (state.category && j.category !== state.category) return false;
    if (state.exp && j.exp !== state.exp) return false;
    if (state.days && j.daysAgo > Number(state.days)) return false;
    return true;
  }

  function score(j) {
    const ts = terms(state.q);
    if (!ts.length) return 0;
    const title = j.title.toLowerCase();
    const skills = j.skills.map((s) => s.toLowerCase());
    return ts.reduce((acc, t) =>
      acc + (title.includes(t) ? 3 : 0) + (skills.some((s) => s.includes(t)) ? 2 : 0) + 1, 0);
  }

  const SORTERS = {
    relevance: (a, b) => score(b) - score(a) || a.daysAgo - b.daysAgo,
    date: (a, b) => a.daysAgo - b.daysAgo,
    salary: (a, b) => (b.salaryMin || 0) - (a.salaryMin || 0),
  };

  const getResults = () => JOBS.filter(matches).sort(SORTERS[state.sort]);

  /* ---------- Rendering ---------- */
  function cardHTML(j) {
    const isSaved = saved.has(j.id);
    return `
      <li class="card${j.id === state.selected ? ' active' : ''}" data-id="${esc(j.id)}" tabindex="0">
        <div class="card-top">
          <div>
            <h3 class="title">${esc(j.title)}</h3>
            <p class="company">${esc(j.company)} · ${esc(locationText(j))}</p>
          </div>
          <button class="save${isSaved ? ' on' : ''}" type="button" data-save="${esc(j.id)}"
            aria-pressed="${isSaved}" aria-label="${isSaved ? 'Unsave' : 'Save'} ${esc(j.title)}">${isSaved ? '★' : '☆'}</button>
        </div>
        <p class="salary">${esc(salaryText(j))}</p>
        <p class="snippet">${esc(j.summary)}</p>
        <div class="tags"><span>${esc(j.type)}</span><span>${esc(j.exp)} level</span><span>${esc(j.category)}</span></div>
        <p class="posted">${postedText(j.daysAgo)}</p>
      </li>`;
  }

  function renderPager(pages) {
    if (pages <= 1) { els.pager.innerHTML = ''; return; }
    const btn = (p, label, disabled = false, active = false) =>
      `<button type="button" data-page="${p}"${disabled ? ' disabled' : ''}` +
      `${active ? ' class="on" aria-current="page"' : ''}>${label}</button>`;
    let html = btn(state.page - 1, '‹ Prev', state.page === 1);
    for (let p = 1; p <= pages; p++) html += btn(p, String(p), false, p === state.page);
    html += btn(state.page + 1, 'Next ›', state.page === pages);
    els.pager.innerHTML = html;
  }

  function renderList() {
    const all = getResults();
    const pages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
    state.page = Math.min(state.page, pages);
    const slice = all.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE);

    if (!all.some((j) => j.id === state.selected)) state.selected = all[0] ? all[0].id : null;

    const bits = [];
    if (state.q.trim()) bits.push(`for “${esc(state.q.trim())}”`);
    if (state.loc.trim()) bits.push(`in ${esc(state.loc.trim())}`);
    if (state.savedOnly) bits.push('(saved)');
    els.summary.innerHTML = `<strong>${all.length}</strong> job${all.length === 1 ? '' : 's'} ${bits.join(' ')}`.trim();

    els.list.innerHTML = slice.length
      ? slice.map(cardHTML).join('')
      : '<li class="empty">No jobs match these filters. Try removing a filter or broadening your search.</li>';

    renderPager(pages);
  }

  function renderDetail() {
    const j = JOBS.find((x) => x.id === state.selected);
    if (!j) {
      els.detail.innerHTML = '<div class="detail-empty">Select a job to see its details.</div>';
      return;
    }
    const isSaved = saved.has(j.id);
    const items = (arr) => `<ul>${arr.map((x) => `<li>${esc(x)}</li>`).join('')}</ul>`;

    els.detail.innerHTML = `
      <button class="back" type="button" data-back>← Back to results</button>
      <h2>${esc(j.title)}</h2>
      <p class="company">${esc(j.company)} · ${esc(locationText(j))}</p>
      <p class="salary">${esc(salaryText(j))}</p>
      <div class="actions">
        <button class="btn-primary" type="button" data-apply>Apply now</button>
        <button class="btn-ghost" type="button" data-save="${esc(j.id)}" aria-pressed="${isSaved}">
          ${isSaved ? '★ Saved' : '☆ Save job'}
        </button>
      </div>
      <p class="muted">${esc(j.type)} · ${esc(j.exp)} level · ${esc(j.category)} · ${postedText(j.daysAgo)}</p>
      <h3>About the role</h3>
      <p>${esc(j.summary)}</p>
      <h3>Responsibilities</h3>
      ${items(j.responsibilities)}
      <h3>Requirements</h3>
      ${items(j.requirements)}
      <h3>Skills</h3>
      <div class="tags">${j.skills.map((s) => `<span>${esc(s)}</span>`).join('')}</div>`;
  }

  function refresh() {
    renderList();
    renderDetail();
    els.savedCount.textContent = saved.size;
    els.savedLink.classList.toggle('active', state.savedOnly);
  }

  /* ---------- Actions ---------- */
  let toastTimer;
  function toast(msg) {
    let t = document.querySelector('.toast');
    if (!t) {
      t = document.createElement('div');
      t.className = 'toast';
      t.setAttribute('role', 'status');
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
  }

  function select(id) {
    state.selected = id;
    refresh();
    if (window.matchMedia('(max-width: 1100px)').matches) els.detail.classList.add('open');
  }

  function toggleSave(id) {
    if (saved.has(id)) {
      saved.delete(id);
      toast('Removed from saved jobs');
    } else {
      saved.add(id);
      toast('Job saved');
    }
    persistSaved();
    refresh();
  }

  function updateURL() {
    const p = new URLSearchParams();
    if (state.q.trim()) p.set('q', state.q.trim());
    if (state.loc.trim()) p.set('loc', state.loc.trim());
    const qs = p.toString();
    history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
  }

  function applySearch() {
    state.q = els.q.value;
    state.loc = els.loc.value;
    state.page = 1;
    state.savedOnly = false;
    updateURL();
    refresh();
  }

  const change = (fn) => { fn(); state.page = 1; refresh(); };

  /* ---------- Event wiring ---------- */
  function bind() {
    els.form.addEventListener('submit', (e) => { e.preventDefault(); applySearch(); });

    let debounceId;
    els.q.addEventListener('input', () => {
      clearTimeout(debounceId);
      debounceId = setTimeout(applySearch, 250);
    });

    els.types.addEventListener('change', (e) => {
      if (e.target.type !== 'checkbox') return;
      change(() => {
        if (e.target.checked) state.types.add(e.target.value);
        else state.types.delete(e.target.value);
      });
    });

    els.category.addEventListener('change', () => change(() => { state.category = els.category.value; }));
    els.exp.addEventListener('change', () => change(() => { state.exp = els.exp.value; }));
    els.date.addEventListener('change', () => change(() => { state.days = els.date.value; }));
    els.remote.addEventListener('change', () => change(() => { state.remote = els.remote.checked; }));
    els.sort.addEventListener('change', () => change(() => { state.sort = els.sort.value; }));

    els.clear.addEventListener('click', () => {
      Object.assign(state, {
        q: '', loc: '', types: new Set(), category: '', exp: '', days: '',
        remote: false, sort: 'relevance', page: 1, savedOnly: false,
      });
      els.q.value = '';
      els.loc.value = '';
      els.category.value = '';
      els.exp.value = '';
      els.date.value = '';
      els.remote.checked = false;
      els.sort.value = 'relevance';
      els.types.querySelectorAll('input').forEach((i) => { i.checked = false; });
      updateURL();
      refresh();
    });

    els.list.addEventListener('click', (e) => {
      const saveBtn = e.target.closest('[data-save]');
      if (saveBtn) { toggleSave(saveBtn.dataset.save); return; }
      const card = e.target.closest('.card');
      if (card) select(card.dataset.id);
    });

    els.list.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('card')) {
        select(e.target.dataset.id);
      }
    });

    els.detail.addEventListener('click', (e) => {
      if (e.target.closest('[data-back]')) { els.detail.classList.remove('open'); return; }
      if (e.target.closest('[data-apply]')) { toast('Applications are not connected in this demo.'); return; }
      const saveBtn = e.target.closest('[data-save]');
      if (saveBtn) toggleSave(saveBtn.dataset.save);
    });

    els.pager.addEventListener('click', (e) => {
      const b = e.target.closest('button[data-page]');
      if (!b || b.disabled) return;
      state.page = Number(b.dataset.page);
      refresh();
      els.list.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    els.savedLink.addEventListener('click', (e) => {
      e.preventDefault();
      change(() => { state.savedOnly = !state.savedOnly; });
    });
  }

  /* ---------- Init ---------- */
  function init() {
    if (!JOBS.length) { els.summary.textContent = 'No job data loaded.'; return; }

    const cats = [...new Set(JOBS.map((j) => j.category))].sort();
    els.category.insertAdjacentHTML('beforeend',
      cats.map((c) => `<option value="${esc(c)}">${esc(c)}</option>`).join(''));

    const locs = [...new Set(JOBS.map((j) => j.location))].sort();
    els.locList.innerHTML = ['Remote', ...locs].map((l) => `<option value="${esc(l)}"></option>`).join('');

    const types = [...new Set(JOBS.map((j) => j.type))];
    els.types.innerHTML = types.map((t) =>
      `<label class="check"><input type="checkbox" value="${esc(t)}"> ${esc(t)}</label>`).join('');

    const params = new URLSearchParams(location.search);
    state.q = params.get('q') || '';
    state.loc = params.get('loc') || '';
    els.q.value = state.q;
    els.loc.value = state.loc;

    bind();
    refresh();
  }

  init();
})();
