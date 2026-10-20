(() => {
  'use strict';

  /*
   * Back office for a single admin. Front-end only: there is no server.
   * Jobs live in this browser's localStorage and are shared with the public
   * site (../app.js) only when both pages are opened in the same browser.
   * The password gate is a UI lock, not real security.
   */

  const JOBS_KEY = 'kazi-jobs-v1';
  const AUTH_KEY = 'kazi-admin-hash';
  const SESSION_KEY = 'kazi-admin-session';

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => [...document.querySelectorAll(sel)];

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));

  let jobs = [];
  let editingId = null;

  /* ---------- Job storage ---------- */
  const seedJobs = () => (window.JOBS || []).map((j) => ({ ...j, published: true }));

  function loadJobs() {
    try {
      const raw = localStorage.getItem(JOBS_KEY);
      if (raw !== null) return JSON.parse(raw);
    } catch { /* corrupt data: fall back to seed */ }
    return seedJobs();
  }

  function saveJobs() {
    try { localStorage.setItem(JOBS_KEY, JSON.stringify(jobs)); } catch { toast('Could not save: browser storage is full or blocked.'); }
  }

  function persist() {
    saveJobs();
    renderAll();
  }

  /* ---------- Password gate ---------- */
  async function sha256(text) {
    if (window.crypto && crypto.subtle) {
      const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
      return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
    }
    // Fallback when crypto.subtle is unavailable (e.g. file:// in some browsers). Weak hash.
    let h = 2166136261;
    for (const c of text) { h ^= c.charCodeAt(0); h = Math.imul(h, 16777619); }
    return 'fnv-' + (h >>> 0).toString(16);
  }

  function showAuth() {
    $('#appView').classList.add('hidden');
    $('#authView').classList.remove('hidden');
    const hasPassword = !!localStorage.getItem(AUTH_KEY);
    $('#setupForm').classList.toggle('hidden', hasPassword);
    $('#loginForm').classList.toggle('hidden', !hasPassword);
  }

  function showApp() {
    $('#authView').classList.add('hidden');
    $('#appView').classList.remove('hidden');
    renderAll();
  }

  /* ---------- Rendering ---------- */
  const isPublished = (j) => j.published !== false;

  function renderDashboard() {
    const published = jobs.filter(isPublished).length;
    const remote = jobs.filter((j) => j.remote).length;
    const cats = {};
    jobs.forEach((j) => { cats[j.category] = (cats[j.category] || 0) + 1; });

    $('#stats').innerHTML = [
      ['Total jobs', jobs.length],
      ['Published', published],
      ['Drafts', jobs.length - published],
      ['Remote-friendly', remote],
    ].map(([label, val]) => `<div class="stat"><span>${label}</span><strong>${val}</strong></div>`).join('');

    const entries = Object.entries(cats).sort((a, b) => b[1] - a[1]);
    const max = Math.max(1, ...entries.map((e) => e[1]));
    $('#categoryBars').innerHTML = entries.length
      ? entries.map(([c, n]) => `
          <div class="bar-row">
            <span>${esc(c)}</span>
            <div class="bar"><i style="width:${(n / max) * 100}%"></i></div>
            <b>${n}</b>
          </div>`).join('')
      : '<p class="muted">No jobs yet.</p>';
  }

  function renderJobs() {
    const q = $('#jobSearch').value.trim().toLowerCase();
    const status = $('#statusFilter').value;

    const rows = jobs.filter((j) => {
      if (status === 'published' && !isPublished(j)) return false;
      if (status === 'draft' && isPublished(j)) return false;
      if (q && !`${j.title} ${j.company} ${j.location} ${j.category}`.toLowerCase().includes(q)) return false;
      return true;
    });

    $('#jobCount').textContent = `${rows.length} of ${jobs.length} jobs`;

    $('#jobRows').innerHTML = rows.length
      ? rows.map((j) => {
          const pub = isPublished(j);
          return `
            <tr>
              <td><strong>${esc(j.title)}</strong><div class="muted small">${esc(j.category)}</div></td>
              <td>${esc(j.company)}</td>
              <td>${esc(j.location)}${j.remote ? ' · Remote' : ''}</td>
              <td>${esc(j.type)}</td>
              <td><span class="pill ${pub ? 'pub' : 'draft'}">${pub ? 'Published' : 'Draft'}</span></td>
              <td>${j.daysAgo === 0 ? 'Today' : `${j.daysAgo}d ago`}</td>
              <td class="row-actions">
                <button type="button" data-edit="${esc(j.id)}">Edit</button>
                <button type="button" data-toggle="${esc(j.id)}">${pub ? 'Unpublish' : 'Publish'}</button>
                <button type="button" class="danger" data-delete="${esc(j.id)}">Delete</button>
              </td>
            </tr>`;
        }).join('')
      : '<tr><td colspan="7" class="muted center">No jobs match these filters.</td></tr>';
  }

  function renderCategoryOptions() {
    const cats = [...new Set(jobs.map((j) => j.category).filter(Boolean))].sort();
    $('#catList').innerHTML = cats.map((c) => `<option value="${esc(c)}"></option>`).join('');
  }

  function renderAll() {
    renderDashboard();
    renderJobs();
    renderCategoryOptions();
  }

  /* ---------- Job form ---------- */
  const dialog = $('#jobDialog');
  const form = $('#jobForm');
  const F = form.elements; // use .elements, since form.title/type clash with DOM properties

  function openForm(job) {
    editingId = job ? job.id : null;
    $('#dialogTitle').textContent = job ? 'Edit job' : 'New job';
    $('#formError').textContent = '';
    const v = job || {};
    F.title.value = v.title || '';
    F.company.value = v.company || '';
    F.location.value = v.location || '';
    F.type.value = v.type || 'Full-time';
    F.category.value = v.category || '';
    F.exp.value = v.exp || 'Entry';
    F.salaryMin.value = v.salaryMin ?? '';
    F.salaryMax.value = v.salaryMax ?? '';
    F.skills.value = (v.skills || []).join(', ');
    F.summary.value = v.summary || '';
    F.responsibilities.value = (v.responsibilities || []).join('\n');
    F.requirements.value = (v.requirements || []).join('\n');
    F.remote.checked = !!v.remote;
    F.published.checked = job ? isPublished(job) : true;
    dialog.showModal();
    F.title.focus();
  }

  const toNumber = (s) => {
    const n = parseInt(s, 10);
    return Number.isFinite(n) && n > 0 ? n : null;
  };
  const splitList = (s, sep) => s.split(sep).map((x) => x.trim()).filter(Boolean);

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = {
      title: F.title.value.trim(),
      company: F.company.value.trim(),
      location: F.location.value.trim(),
      type: F.type.value,
      category: F.category.value.trim(),
      exp: F.exp.value,
      salaryMin: toNumber(F.salaryMin.value),
      salaryMax: toNumber(F.salaryMax.value),
      skills: splitList(F.skills.value, ','),
      summary: F.summary.value.trim(),
      responsibilities: splitList(F.responsibilities.value, '\n'),
      requirements: splitList(F.requirements.value, '\n'),
      remote: F.remote.checked,
      published: F.published.checked,
    };

    let error = '';
    if (!data.title) error = 'Job title is required.';
    else if (!data.company) error = 'Company is required.';
    else if (!data.location) error = 'Location is required.';
    else if (!data.category) error = 'Category is required.';
    else if (!data.summary) error = 'Summary is required.';
    else if (data.salaryMin && data.salaryMax && data.salaryMax < data.salaryMin) error = 'Salary max must be above salary min.';

    if (error) { $('#formError').textContent = error; return; }

    if (editingId) {
      const i = jobs.findIndex((j) => j.id === editingId);
      if (i !== -1) jobs[i] = { ...jobs[i], ...data };
      toast('Job updated');
    } else {
      jobs.unshift({ id: 'j' + Date.now().toString(36), daysAgo: 0, ...data });
      toast('Job created');
    }

    editingId = null;
    dialog.close();
    persist();
  });

  /* ---------- Actions ---------- */
  function exportJobs() {
    const blob = new Blob([JSON.stringify(jobs, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'kaziug-jobs.json';
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  let toastTimer;
  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
  }

  /* ---------- Event wiring ---------- */
  function bind() {
    // Sign-in and setup
    $('#setupForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const a = $('#setupPass').value;
      const b = $('#setupPass2').value;
      if (a.length < 8) { toast('Use at least 8 characters.'); return; }
      if (a !== b) { toast('Passwords do not match.'); return; }
      localStorage.setItem(AUTH_KEY, await sha256(a));
      sessionStorage.setItem(SESSION_KEY, '1');
      showApp();
    });

    $('#loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const ok = (await sha256($('#loginPass').value)) === localStorage.getItem(AUTH_KEY);
      if (ok) {
        sessionStorage.setItem(SESSION_KEY, '1');
        $('#loginPass').value = '';
        $('#loginError').textContent = '';
        showApp();
      } else {
        $('#loginError').textContent = 'Incorrect password.';
      }
    });

    $('#logoutBtn').addEventListener('click', () => {
      sessionStorage.removeItem(SESSION_KEY);
      showAuth();
    });

    // Navigation
    $$('.nav-btn[data-view]').forEach((btn) => {
      btn.addEventListener('click', () => {
        $$('.nav-btn[data-view]').forEach((b) => b.classList.toggle('active', b === btn));
        $$('.view').forEach((v) => v.classList.toggle('hidden', v.id !== btn.dataset.view));
      });
    });

    // Jobs toolbar
    $('#jobSearch').addEventListener('input', renderJobs);
    $('#statusFilter').addEventListener('change', renderJobs);
    $('#newJobBtn').addEventListener('click', () => openForm(null));
    $('#exportBtn').addEventListener('click', exportJobs);
    $('#resetBtn').addEventListener('click', () => {
      if (confirm('Replace all jobs with the sample data? Your changes will be lost.')) {
        jobs = seedJobs();
        persist();
        toast('Sample data restored');
      }
    });

    // Row actions
    $('#jobRows').addEventListener('click', (e) => {
      const b = e.target.closest('button');
      if (!b) return;
      const id = b.dataset.edit || b.dataset.toggle || b.dataset.delete;
      const job = jobs.find((j) => j.id === id);
      if (!job) return;

      if ('edit' in b.dataset) {
        openForm(job);
      } else if ('toggle' in b.dataset) {
        job.published = !isPublished(job);
        persist();
        toast(job.published ? 'Published' : 'Unpublished');
      } else if ('delete' in b.dataset) {
        if (confirm(`Delete "${job.title}"? This cannot be undone.`)) {
          jobs = jobs.filter((j) => j.id !== id);
          persist();
          toast('Job deleted');
        }
      }
    });

    // Dialog close buttons
    $$('[data-close]').forEach((btn) => btn.addEventListener('click', () => {
      editingId = null;
      dialog.close();
    }));
  }

  /* ---------- Init ---------- */
  function init() {
    const hasStored = localStorage.getItem(JOBS_KEY) !== null;
    jobs = loadJobs();
    if (!hasStored) saveJobs(); // store the seed so the public site reads the same data
    bind();

    const signedIn = sessionStorage.getItem(SESSION_KEY) === '1' && localStorage.getItem(AUTH_KEY);
    if (signedIn) showApp();
    else showAuth();
  }

  init();
})();
